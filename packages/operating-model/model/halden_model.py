"""Halden Energy quarterly operating model, reference implementation v0.2 (Quarters 1-6).

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
            "usdsgd": float(r["usdsgd"]), "fx_live": r["fx_live"] == "1",
            "existing_eur_hedge": r["existing_eur_hedge"] == "1",
        })
    return rows


def load_clusters(name):
    return [{
        "key": r[list(r.keys())[0]], "label": r["label"], "share": float(r["volume_share"]),
        "e": float(r["elasticity"]), "pt": float(r["pass_through"]), "base": float(r["base_offset_cents"]),
    } for r in _read(name)]


def load_projects():
    out = {}
    for r in _read("projects.csv"):
        out[r["key"]] = {"label": r["label"], "segment": r["segment"], "haircut": float(r["haircut"]),
                         "outlay": float(r["outlay"]), "cf": [float(r[f"cf{i}"]) for i in range(1, 11)]}
    return out


def load_cohort_capital():
    return [{"behaviour": r["behaviour"], "min": float(r["score_min"]), "max": float(r["score_max"]),
             "rate": float(r["discount_rate"]), "envelope": float(r["capital_envelope"])} for r in _read("cohort_capital.csv")]


C = load_constants()
PROJECTS = load_projects()
COHORT_CAPITAL = load_cohort_capital()
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
    crude_hedge_pct: float = 0.0   # percent of next quarter's crude output sold forward at this quarter's price
    eur_hedge: float = 0.0         # USD m of euros sold forward for next quarter
    nok_hedge: float = 0.0         # USD m of kroner bought forward for next quarter
    projects: dict = field(default_factory=dict)  # project key -> "commit" | "hold"


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
    hedges: dict = field(default_factory=dict)     # hedges opened last quarter, settled this quarter
    projects: dict = field(default_factory=dict)   # committed project key -> quarters since commitment


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


# ---------------- Effects of what the whole class does (hidden until they land) ----------------

def window1_nwe(avg_util):
    """Window 1: the class's average European run rate in Q3 2027 sets the NWE margin in Q1 2028."""
    v = C["window1_base_crack"] + C["window1_slope"] * (C["window1_base_util"] - avg_util)
    return max(C["window1_floor"], v)


def european_util(dec: Decisions):
    """Share of Rotterdam's capacity a team ran (0 when paused or closed)."""
    return dec.rot_run / 100.0 if dec.rot_posture == "run" else 0.0


def crude_price_discipline(dec: Decisions, wti):
    """1 = crude price at cost, 0.5 = at market, 0 = somewhere a trader can exploit."""
    market, cost = transfer_prices(wti)
    if dec.tp_method == "cost":
        return 1.0
    if dec.tp_method == "market":
        return 0.5
    tp = float(dec.tp_value)
    if abs(tp - cost) <= C["geneva_band"] * cost:
        return 1.0
    if abs(tp - market) <= C["geneva_band"] * market:
        return 0.5
    return 0.0


def capital_terms(avg_discipline):
    """Q2 2028 cost of capital and spending envelope, from the class's Q4 2027 crude-price choices."""
    if avg_discipline > 2 / 3 + 1e-9:
        b = "disciplined"
    elif avg_discipline < 1 / 3 - 1e-9:
        b = "lax"
    else:
        b = "base"
    return next(c for c in COHORT_CAPITAL if c["behaviour"] == b)


def step(state: State, dec: Decisions, mkt: dict):
    """Run one quarter. Returns (lines dict in USD m, kpi inputs, new state)."""
    lines = {}
    notes = {}
    wti = mkt["wti"]
    brent = wti + C["brent_spread"]
    fx = mkt.get("fx_live", False)
    eur_f = mkt["eurusd"] / C["fx_ref_eurusd"] if fx else 1.0      # euro earnings in dollars
    nok_f = C["fx_ref_usdnok"] / mkt["usdnok"] if fx else 1.0      # kroner costs in dollars
    sgd_f = C["fx_ref_usdsgd"] / mkt["usdsgd"] if fx else 1.0
    nor_lifting = C["norway_lifting"] * nok_f

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
    nor_margin_full = brent - C["norway_discount_to_brent"] - nor_lifting - C["norway_transport"]
    nor_vol = C["norway_op_volume"]
    nor_saved_per_bbl = nor_lifting * C["norway_lifting_variable_share"] + C["norway_transport"]
    lines["norway_operated"] = (nor_vol * nor_margin_full
                                - nor_vol * cut * (brent - C["norway_discount_to_brent"] - nor_saved_per_bbl)) * D / 1e6
    lines["norway_cutback_effect"] = -nor_vol * cut * (brent - C["norway_discount_to_brent"] - nor_saved_per_bbl) * D / 1e6
    lines["norway_partner_run"] = C["norway_nonop_volume"] * nor_margin_full * D / 1e6
    kes_net = (brent - C["kessana_discount_to_brent"] - C["kessana_lifting"]) * C["kessana_company_share_profit_oil"]
    lines["kessana"] = C["kessana_volume"] * kes_net * D / 1e6
    lines["gas_other"] = C["gas_other_ebitda"]
    upstream_base = lines["permian"] + lines["norway_operated"] + lines["norway_partner_run"] + lines["kessana"] + lines["gas_other"]

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
    lines["rotterdam"] = rot * eur_f   # Rotterdam earns and spends in euros: a natural hedge
    lines["rotterdam_one_time"] = one_time

    sg_run = min(max(dec.sg_request, C["sg_accept_min"]), C["sg_accept_max"])
    notes["sg_accepted"] = sg_run
    sg_tp = C["sg_capacity"] * C["sg_halden_share"] * sg_run / 100.0
    lines["singapore"] = sg_tp * (mkt["sg"] + C["sg_complexity"] - C["sg_opex"]) * D / 1e6 * sgd_f
    # Projects committed in earlier quarters pay a quarter of each year's cash flow, cut to what such
    # projects really deliver. A Rotterdam project stops if Rotterdam closes.
    proj_age = {}
    proj_lines = {"refineries": 0.0, "oil_fields": 0.0}
    for key, age in state.projects.items():
        p = PROJECTS[key]
        age = age + 1
        proj_age[key] = age
        year = (age - 1) // 4 + 1
        if year > len(p["cf"]) or (key == "rot_upgrade" and rot_status == "closed"):
            continue
        proj_lines[p["segment"]] += p["cf"][year - 1] * p["haircut"] / 4
    commit_outlay = 0.0
    for key, choice in dec.projects.items():
        if choice == "commit" and key not in state.projects:
            proj_age[key] = 0
            commit_outlay += PROJECTS[key]["outlay"]
    lines["projects_refining"] = proj_lines["refineries"]
    lines["projects_upstream"] = proj_lines["oil_fields"]
    refining = lines["baton_rouge"] + lines["rotterdam"] + lines["rotterdam_one_time"] + lines["singapore"] + lines["projects_refining"]

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
    lines["europe_stations"] = eu / 1e6 * eur_f
    lines["retail_fixed"] = -C["retail_fixed_cost"]
    retail = lines["cordell_fuel"] + lines["cordell_shop"] + lines["europe_stations"] + lines["retail_fixed"]

    # ---------------- Head office ----------------
    lines["head_office"] = -C["corporate_ga"]
    lines["advisor_time"] = -C["advisor_cost_per_answer"] * dec.advisor_answers
    h = state.hedges
    settle = 0.0
    if h:
        settle += h.get("crude_bbl_day", 0.0) * D * (h.get("crude_price", wti) - wti) / 1e6
        if h.get("eur", 0.0):
            settle += h["eur"] * (h["eur_rate"] - mkt["eurusd"]) / h["eur_rate"]
        if h.get("nok", 0.0):
            settle += h["nok"] * (h["nok_rate"] / mkt["usdnok"] - 1)
    if mkt.get("existing_eur_hedge", False):
        r0 = C["existing_eur_hedge_rate"]
        settle += C["existing_eur_hedge_notional"] * (r0 - mkt["eurusd"]) / r0
    lines["hedges"] = settle
    corporate = lines["head_office"] + lines["advisor_time"] + lines["hedges"]

    upstream = upstream_base + lines["projects_upstream"]
    seg = {"oil_fields": upstream, "refineries": refining, "geneva": trading, "gas_stations": retail, "head_office": corporate}
    ebitda = sum(seg.values())

    # ---------------- Money ----------------
    da = state.capital_employed * C["da_rate_annual"] / 4
    tax = C["tax_rate"] * max(0.0, ebitda - da)
    capex = C["other_sustaining_capex"] + dec.rigs * C["rig_capex_per_qtr"] + commit_outlay
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
        # Before growth projects (decision S2, proposed): sustaining cash generation, so a sound project
        # isn't punished in the score in the quarter its money goes out. Net debt still carries it.
        "free_cash_flow": fcf + commit_outlay,
        "refining_vs_industry": (refining_ex_internal * 1e6 / refined_bbl - bench_margin) if refined_bbl > 0 else -bench_margin,
        "shop_profit_per_station_k": cord_nonfuel / C["cordell_sites"] / 1e3,
        "debt_to_earnings": new_nd / (4 * ebitda) if ebitda > 0 else 99.0,
        "plant_condition": health,
    }

    # Next quarter's Permian production (rigs this quarter -> wells next quarter)
    next_prod = prod * (1 - C["permian_decline_qtr"]) + permian_adds(dec.rigs)

    # Hedges opened now settle next quarter: crude at this quarter's price, currencies at today's rates.
    next_crude = next_prod + nor_vol * (1 - cut) + C["norway_nonop_volume"]
    new_hedges = {}
    if dec.crude_hedge_pct > 0:
        new_hedges["crude_bbl_day"] = dec.crude_hedge_pct / 100.0 * next_crude
        new_hedges["crude_price"] = wti
    if dec.eur_hedge > 0:
        new_hedges["eur"], new_hedges["eur_rate"] = float(dec.eur_hedge), mkt["eurusd"]
    if dec.nok_hedge > 0:
        new_hedges["nok"], new_hedges["nok_rate"] = float(dec.nok_hedge), mkt["usdnok"]
    fx_effect = 0.0
    if fx:
        fx_effect = (lines["rotterdam"] * (1 - 1 / eur_f) + lines["europe_stations"] * (1 - 1 / eur_f)
                     + lines["singapore"] * (1 - 1 / sgd_f)
                     + (C["norway_lifting"] - nor_lifting) * (nor_vol * (1 - cut * C["norway_lifting_variable_share"]) + C["norway_nonop_volume"]) * D / 1e6)

    new_state = State(permian_prod=next_prod, prev_rigs=dec.rigs, rot_status=rot_status,
                      capital_employed=new_ce, net_debt=new_nd, asset_health=health,
                      europe_volume_factor=eu_factor, held_up=held_up, held_down=held_down,
                      hedges=new_hedges, projects=proj_age)
    money = {"ebitda": ebitda, "da": da, "tax": tax, "capex": capex, "fcf": fcf,
             "capital_employed_end": new_ce, "net_debt_end": new_nd}
    ops = {"tp": tp, "market_tp": market_tp, "cost_tp": cost_tp, "permian_prod": prod,
           "br_throughput": br_tp_bbl, "rot_throughput": rot_tp, "sg_accepted": sg_run, "rot_status": rot_status,
           "fx_effect": fx_effect, "project_outlay": commit_outlay, "nwe": mkt["nwe"]}
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
