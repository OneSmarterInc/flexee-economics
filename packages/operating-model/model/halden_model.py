"""Halden Energy quarterly operating model, reference implementation v0.1 (Quarters 1-4).

This is the authoritative economics for the quarterly play-through. The Laravel engine must
reproduce fixtures/golden_quarters.csv within tolerance (rel 1e-6, abs 1e-4).

Conventions
- Money in USD millions per quarter unless named otherwise. Volumes in bbl (or boe) per day.
- A team's decisions for quarter t are applied to quarter t. Rig decisions change production
  from quarter t+1 (new wells take a quarter to start pumping).
- Every constant comes from data/constants.csv; nothing numeric is hard-coded below except
  structure (min, max, rounding).
"""
from __future__ import annotations

import csv
import math
from dataclasses import dataclass, field, asdict
from pathlib import Path

ROOT = Path(__file__).resolve().parent.parent
DATA = ROOT / "data"


def _read(name):
    with open(DATA / name, newline="") as f:
        return list(csv.DictReader(f))


def load_constants():
    out = {}
    for r in _read("constants.csv"):
        out[r["key"]] = float(r["value"])
    return out


def load_market():
    rows = []
    for r in _read("market_path.csv"):
        rows.append({
            "quarter": r["quarter"], "label": r["label"], "is_history": r["is_history"] == "1",
            "wti": float(r["wti"]), "gc": float(r["gc_crack"]), "nwe": float(r["nwe_crack"]),
            "sg": float(r["sg_crack"]), "eurusd": float(r["eurusd"]), "usdnok": float(r["usdnok"]),
            "usdsgd": float(r["usdsgd"]),
        })
    return rows


def load_clusters(name):
    return [{
        "key": r[list(r.keys())[0]], "label": r["label"], "share": float(r["volume_share"]),
        "e": float(r["elasticity"]), "pt": float(r["pass_through"]), "base": float(r["base_offset_cents"]),
    } for r in _read(name)]


C = load_constants()
CORDELL = load_clusters("cordell_clusters.csv")
EUROPE = load_clusters("europe_countries.csv")
D = C["days_per_quarter"]


@dataclass
class Decisions:
    rigs: int = 14
    norway: str = "run"            # run | cut
    br_run: float = 96.0           # percent
    rot_run: float = 82.0          # percent, used when rot_posture == run
    rot_posture: str = "run"       # run | idle | close
    sg_request: float = 90.0       # percent
    offsets: dict = field(default_factory=lambda: {c["key"]: c["base"] for c in CORDELL + EUROPE})
    tp_method: str = "market"      # market | cost | other
    tp_value: float | None = None  # used when tp_method == other
    advisor_answers: int = 0


@dataclass
class State:
    permian_prod: float
    prev_rigs: int
    rot_status: str               # running | idle | closed
    capital_employed: float
    net_debt: float
    asset_health: float
    europe_volume_factor: float = 1.0
    held_up: dict = field(default_factory=dict)    # consecutive quarters each price held above base
    held_down: dict = field(default_factory=dict)  # consecutive quarters each price held below base


def rig_productivity(k):
    """Productivity of the k-th rig (1-based). Each rig beyond the best acreage drills poorer rock."""
    extra = max(0.0, k - C["permian_productivity_free_rigs"])
    return max(C["permian_productivity_floor"], 1.0 - C["permian_productivity_slope"] * extra)


def permian_adds(rigs):
    """New production (bbl/day) from a quarter of drilling with this many rigs; rises with every rig."""
    return C["permian_adds_per_rig"] * sum(rig_productivity(k) for k in range(1, int(rigs) + 1))


def opening_state():
    rigs = 14
    steady = permian_adds(rigs) / C["permian_decline_qtr"]
    return State(permian_prod=steady, prev_rigs=rigs, rot_status="running",
                 capital_employed=C["opening_capital_employed"], net_debt=C["opening_net_debt"],
                 asset_health=C["opening_asset_health"])


def transfer_prices(wti):
    market = wti - C["permian_wellhead_discount"] + C["transport_permian_br"]
    cost = C["delivered_marginal_cost"] + C["sr_capital_charge"]
    return market, cost


def geneva_capture_per_bbl(tp, market, cost):
    band = C["geneva_band"]
    near = lambda v, a: a * (1 - band) <= v <= a * (1 + band)
    if near(tp, cost) or near(tp, market) or tp >= market:
        return 0.0
    return C["geneva_capture_rate"] * (market - tp)


def step(state: State, dec: Decisions, mkt: dict):
    """Run one quarter. Returns (lines dict in USD m, kpi inputs, new state)."""
    lines = {}
    notes = {}
    wti = mkt["wti"]
    brent = wti + C["brent_spread"]

    # ---------------- Oil fields ----------------
    prod = state.permian_prod
    internal = min(C["internal_volume_to_br"], prod)
    market_tp, cost_tp = transfer_prices(wti)
    if dec.tp_method == "market":
        tp = market_tp
    elif dec.tp_method == "cost":
        tp = cost_tp
    else:
        tp = float(dec.tp_value)
    wellhead = wti - C["permian_wellhead_discount"]
    permian_rev = (internal * tp + (prod - internal) * wellhead) * D / 1e6
    permian_cost = (prod * (C["permian_lifting_avg"] + C["permian_gathering"])
                    + internal * C["transport_permian_br"]) * D / 1e6
    lines["permian"] = permian_rev - permian_cost

    cut = C["norway_cut_share"] if dec.norway == "cut" else 0.0
    nor_margin_full = brent - C["norway_discount_to_brent"] - C["norway_lifting"] - C["norway_transport"]
    nor_vol = C["norway_op_volume"]
    nor_saved_per_bbl = C["norway_lifting"] * C["norway_lifting_variable_share"] + C["norway_transport"]
    lines["norway_operated"] = (nor_vol * nor_margin_full
                                - nor_vol * cut * (brent - C["norway_discount_to_brent"] - nor_saved_per_bbl)) * D / 1e6
    lines["norway_cutback_effect"] = -nor_vol * cut * (brent - C["norway_discount_to_brent"] - nor_saved_per_bbl) * D / 1e6
    lines["norway_partner_run"] = C["norway_nonop_volume"] * nor_margin_full * D / 1e6
    kes_net = (brent - C["kessana_discount_to_brent"] - C["kessana_lifting"]) * C["kessana_company_share_profit_oil"]
    lines["kessana"] = C["kessana_volume"] * kes_net * D / 1e6
    lines["gas_other"] = C["gas_other_ebitda"]
    upstream = lines["permian"] + lines["norway_operated"] + lines["norway_partner_run"] + lines["kessana"] + lines["gas_other"]

    # ---------------- Refineries ----------------
    br_tp_bbl = C["br_capacity"] * dec.br_run / 100.0
    br_crack_margin = br_tp_bbl * (mkt["gc"] + C["br_complexity"] - C["br_variable_opex"]) * D / 1e6
    br_fixed = C["br_capacity"] * C["br_fixed_opex_per_bbl_capacity"] * D / 1e6
    internal_shift = (market_tp - tp) * internal * D / 1e6   # refinery gain from paying below market
    g_bbl = geneva_capture_per_bbl(tp, market_tp, cost_tp)
    geneva = g_bbl * C["geneva_max_volume"] * D / 1e6 if g_bbl > 0 else 0.0
    lines["internal_crude_shift"] = internal_shift          # oil fields -> refinery (zero-sum inside Halden)
    lines["geneva_gap_trading"] = geneva                    # refinery -> Geneva (zero-sum inside Halden)
    lines["baton_rouge"] = br_crack_margin - br_fixed + internal_shift - geneva

    rot_fixed = C["rot_capacity"] * C["rot_fixed_opex_per_bbl_capacity"] * D / 1e6
    one_time = 0.0
    rot_status = state.rot_status
    if rot_status == "closed" or dec.rot_posture == "close":
        if rot_status != "closed":
            one_time -= C["rot_closure_cost"]
            notes["rot_event"] = "closed"
        rot_status = "closed"
        rot_tp = 0.0
        rot = -C["rot_closed_cost"]
    elif dec.rot_posture == "idle":
        rot_status = "idle"
        rot_tp = 0.0
        rot = -rot_fixed - C["rot_idle_care_cost"]
    else:
        if state.rot_status == "idle":
            one_time -= C["rot_restart_cost"]
            notes["rot_event"] = "restarted"
        rot_status = "running"
        rot_tp = C["rot_capacity"] * dec.rot_run / 100.0
        rot = rot_tp * (mkt["nwe"] + C["rot_complexity"] - C["rot_variable_opex"]) * D / 1e6 - rot_fixed
    lines["rotterdam"] = rot
    lines["rotterdam_one_time"] = one_time

    sg_run = min(max(dec.sg_request, C["sg_accept_min"]), C["sg_accept_max"])
    notes["sg_accepted"] = sg_run
    sg_tp = C["sg_capacity"] * C["sg_halden_share"] * sg_run / 100.0
    lines["singapore"] = sg_tp * (mkt["sg"] + C["sg_complexity"] - C["sg_opex"]) * D / 1e6
    refining = lines["baton_rouge"] + lines["rotterdam"] + lines["rotterdam_one_time"] + lines["singapore"]

    # ---------------- Trading ----------------
    lines["geneva_desk"] = C["geneva_base_desk"]
    trading = lines["geneva_desk"] + geneva

    # ---------------- Gas stations ----------------
    held_up, held_down = dict(state.held_up), dict(state.held_down)

    def volume_factor(c, offset):
        delta = (offset - c["base"]) / 100.0  # USD per gallon change in what we charge
        street = c["pt"] * delta
        k = c["key"]
        if delta > 1e-12:
            n = held_up.get(k, 0)
            e = c["e"] * (1 + C["longrun_elasticity_step"] * min(n, 4))
            held_up[k] = n + 1
            held_down[k] = 0
        elif delta < -1e-12:
            n = held_down.get(k, 0)
            e = c["e"] * max(0.0, 1 - C["longrun_elasticity_step"] * n)  # rivals match cuts over time
            held_down[k] = n + 1
            held_up[k] = 0
        else:
            e = c["e"]
            held_up[k] = 0
            held_down[k] = 0
        return 1 + e * street / C["pump_base"], delta

    cord_gal_total = C["cordell_sites"] * C["cordell_gal_per_site_qtr"]
    cord_fuel = cord_nonfuel = 0.0
    for c in CORDELL:
        vf, delta = volume_factor(c, dec.offsets[c["key"]])
        gal = cord_gal_total * c["share"] * vf
        cord_fuel += gal * (C["cordell_fuel_margin"] + delta)
        cord_nonfuel += gal * C["cordell_nonfuel_per_gal"] * C["cordell_nonfuel_halden_share"]
    lines["cordell_fuel"] = cord_fuel / 1e6
    lines["cordell_shop"] = cord_nonfuel / 1e6

    eu_factor = state.europe_volume_factor * (1 - C["europe_volume_decline_qtr"])
    eu_gal_total = C["europe_sites"] * C["europe_gal_per_site_qtr"] * eu_factor
    eu = 0.0
    for c in EUROPE:
        vf, delta = volume_factor(c, dec.offsets[c["key"]])
        gal = eu_gal_total * c["share"] * vf
        eu += gal * (C["europe_fuel_margin"] + delta + C["europe_nonfuel_per_gal"])
    lines["europe_stations"] = eu / 1e6
    lines["retail_fixed"] = -C["retail_fixed_cost"]
    retail = lines["cordell_fuel"] + lines["cordell_shop"] + lines["europe_stations"] + lines["retail_fixed"]

    # ---------------- Head office ----------------
    lines["head_office"] = -C["corporate_ga"]
    lines["advisor_time"] = -C["advisor_cost_per_answer"] * dec.advisor_answers
    corporate = lines["head_office"] + lines["advisor_time"]

    seg = {"oil_fields": upstream, "refineries": refining, "geneva": trading, "gas_stations": retail, "head_office": corporate}
    ebitda = sum(seg.values())

    # ---------------- Money ----------------
    da = state.capital_employed * C["da_rate_annual"] / 4
    tax = C["tax_rate"] * max(0.0, ebitda - da)
    capex = C["other_sustaining_capex"] + dec.rigs * C["rig_capex_per_qtr"]
    fcf = ebitda - tax - capex
    new_ce = state.capital_employed + capex - da
    new_nd = state.net_debt - fcf + C["shareholder_payout"]

    # ---------------- Plant condition ----------------
    health = state.asset_health
    health -= 0.5 * max(0.0, dec.br_run - C["br_wear_threshold"])
    if rot_status == "running" and dec.rot_run < C["rot_sticky_threshold"]:
        health -= 0.5
    health = max(0.0, min(100.0, health))

    # ---------------- KPI inputs ----------------
    refined_bbl = (br_tp_bbl + rot_tp + sg_tp) * D
    refining_ex_internal = (br_crack_margin - br_fixed) + (lines["rotterdam"]) + lines["singapore"]
    bench_crack = ((br_tp_bbl * mkt["gc"] + rot_tp * mkt["nwe"] + sg_tp * mkt["sg"]) / max(1.0, br_tp_bbl + rot_tp + sg_tp))
    bench_margin = bench_crack + C["industry_complexity"] - C["industry_opex"]
    kpi = {
        "profit_per_barrel": (ebitda - corporate) * 1e6 / (C["total_production"] * D),
        "roace_pct": 100 * 4 * (ebitda - da) * (1 - C["tax_rate"]) / state.capital_employed,
        "free_cash_flow": fcf,
        "refining_vs_industry": (refining_ex_internal * 1e6 / refined_bbl - bench_margin) if refined_bbl > 0 else -bench_margin,
        "shop_profit_per_station_k": cord_nonfuel / C["cordell_sites"] / 1e3,
        "debt_to_earnings": new_nd / (4 * ebitda) if ebitda > 0 else 99.0,
        "plant_condition": health,
    }

    # Next quarter's Permian production (rigs this quarter -> wells next quarter)
    next_prod = prod * (1 - C["permian_decline_qtr"]) + permian_adds(dec.rigs)

    new_state = State(permian_prod=next_prod, prev_rigs=dec.rigs, rot_status=rot_status,
                      capital_employed=new_ce, net_debt=new_nd, asset_health=health,
                      europe_volume_factor=eu_factor, held_up=held_up, held_down=held_down)
    money = {"ebitda": ebitda, "da": da, "tax": tax, "capex": capex, "fcf": fcf,
             "capital_employed_end": new_ce, "net_debt_end": new_nd}
    ops = {"tp": tp, "market_tp": market_tp, "cost_tp": cost_tp, "permian_prod": prod,
           "br_throughput": br_tp_bbl, "rot_throughput": rot_tp, "sg_accepted": sg_run, "rot_status": rot_status}
    return lines, seg, money, kpi, ops, notes, new_state


KPI_WEIGHTS = {
    "profit_per_barrel": (0.30, "higher"),
    "roace_pct": (0.15, "higher"),
    "free_cash_flow": (0.15, "higher"),
    "refining_vs_industry": (0.10, "higher"),
    "shop_profit_per_station_k": (0.10, "higher"),
    "debt_to_earnings": (0.10, "lower"),
    "plant_condition": (0.10, "higher"),
}

# Smallest spread between best and worst team that counts as a real difference (decision S1, proposed).
# Below this spread, the 0-100 scale is stretched to this width around the middle, so pennies
# do not become a full 100-point swing.
KPI_MATERIALITY = {
    "profit_per_barrel": 0.50,          # USD per barrel
    "roace_pct": 0.25,                  # percentage points
    "free_cash_flow": 50.0,             # USD m per quarter
    "refining_vs_industry": 0.25,       # USD per barrel
    "shop_profit_per_station_k": 0.50,  # USD thousand per station
    "debt_to_earnings": 0.05,           # times
    "plant_condition": 2.0,             # points
}


def composite(kpis_by_team: dict) -> dict:
    """Min-max 0-100 within the section per KPI (lower-is-better reversed; all equal = 50), weighted."""
    teams = list(kpis_by_team)
    scores = {t: 0.0 for t in teams}
    for k, (w, direction) in KPI_WEIGHTS.items():
        vals = {t: kpis_by_team[t][k] for t in teams}
        lo, hi = min(vals.values()), max(vals.values())
        band = KPI_MATERIALITY.get(k, 0.0)
        if hi - lo < band:
            mid = (hi + lo) / 2
            lo, hi = mid - band / 2, mid + band / 2
        for t in teams:
            if math.isclose(hi, lo, rel_tol=0, abs_tol=1e-9):
                s = 50.0
            else:
                s = 100 * (vals[t] - lo) / (hi - lo)
                if direction == "lower":
                    s = 100 - s
            scores[t] += w * s
    return scores


def rank(scores: dict) -> dict:
    ordered = sorted(scores.values(), reverse=True)
    return {t: 1 + sum(1 for v in ordered if v > s + 1e-9) for t, s in scores.items()}


def run_history():
    st = opening_state()
    hist = []
    for m in load_market():
        if not m["is_history"]:
            continue
        out = step(st, Decisions(), m)
        st = out[-1]
        hist.append((m, out))
    return st, hist


def calibrate_gas_other(target=3750.0):
    """Set gas_other so Q4 2026 EBITDA equals the KPI package's opening EBITDA (15,000 a year)."""
    C["gas_other_ebitda"] = 0.0
    _, hist = run_history()
    q4 = hist[-1][1][2]["ebitda"]
    C["gas_other_ebitda"] = round(target - q4, 6)
    return C["gas_other_ebitda"]
