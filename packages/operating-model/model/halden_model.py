"""Halden Energy quarterly operating model, reference implementation v1.0 (Quarters 1-14).

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
            "rival_cut": float(r.get("rival_cut") or 0.0), "rival_builds": r.get("rival_builds") == "1",
            "opec": r.get("opec") == "1", "recession": r.get("recession") == "1",
            "kessana": r.get("kessana") == "1", "carbon": float(r.get("carbon") or 0.0),
            "labor": r.get("labor") == "1",
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


def load_capacity_game():
    """Halden's yearly payoff (USD m) for each answer to the rival's Gulf Coast expansion."""
    out = {}
    for r in _read("capacity_game.csv"):
        out[(r["halden_action"], r["rival_outcome"])] = float(r["payoff_musd_per_year"])
    return out


def load_opec_scenarios():
    out = {}
    for r in _read("opec_scenarios.csv"):
        out[r["key"]] = {"label": r["label"], "p": float(r["probability"]), "dwti": float(r["delta_wti"])}
    return out


def load_rebrand_markets():
    out = {}
    for r in _read("rebrand_markets.csv"):
        out[r["key"]] = {"label": r["label"], "equity": r["equity"], "sites": float(r["sites"]),
                         "keep": float(r["keep_uplift_per_fill"]), "halden": float(r["halden_benefit_per_fill"])}
    return out


def load_product_elasticities():
    return {r["product"]: float(r["income_elasticity"]) for r in _read("product_elasticities.csv")}


def load_refinery_yields():
    return {r["refinery"]: {p: float(r[p]) for p in ("gasoline", "diesel", "jet", "other")} for r in _read("refinery_yields.csv")}


def load_kessana_takes():
    """The government's share of Kessana profit oil under each outcome of the Q3 2029 talks."""
    return {r["scenario"]: float(r["take"]) for r in _read("kessana_takes.csv")}


def load_kessana_comparables():
    return {r["regime"]: float(r["government_take"]) for r in _read("kessana_comparables.csv")}


def load_portfolio_projects():
    """The Q4 2029 portfolio: five things Halden could do with its five-year envelope (one of them brings money in)."""
    out = {}
    for r in _read("portfolio_projects.csv"):
        out[r["key"]] = {"label": r["label"], "bucket": r["bucket"], "cost": float(r["cost"]), "npv_base": float(r["npv_base"]),
                         "carbon_sens": float(r["carbon_sens"]), "demand_sens": float(r["demand_sens"]),
                         "needs_rotterdam": r["needs_rotterdam"] == "1"}
    return out


def load_portfolio_buckets():
    return {r["bucket"]: {"label": r["label"], "floor": float(r["floor"]), "ceiling": float(r["ceiling"])} for r in _read("portfolio_buckets.csv")}


def load_portfolio_scenarios():
    out = {"carbon": {}, "demand": {}}
    for r in _read("portfolio_scenarios.csv"):
        out[r["kind"]][r["key"]] = {"label": r["label"], "value": float(r["value"]), "p": float(r.get("probability") or 0.0)}
    return out


def load_labor_markets():
    return [{"market": r["market"], "structure": r["structure"], "wage_k": float(r["benchmark_wage_k"]), "note": r["note"]} for r in _read("labor_markets.csv")]


def load_cohort_capital():
    return [{"behaviour": r["behaviour"], "min": float(r["score_min"]), "max": float(r["score_max"]),
             "rate": float(r["discount_rate"]), "envelope": float(r["capital_envelope"])} for r in _read("cohort_capital.csv")]


C = load_constants()
PROJECTS = load_projects()
COHORT_CAPITAL = load_cohort_capital()
CAPACITY_GAME = load_capacity_game()
OPEC = load_opec_scenarios()
REBRAND = load_rebrand_markets()
ELASTICITY = load_product_elasticities()
YIELDS = load_refinery_yields()
LABOR = load_labor_markets()
PORTFOLIO = load_portfolio_projects()
BUCKETS = load_portfolio_buckets()
SCENARIOS = load_portfolio_scenarios()
KESSANA_TAKES = load_kessana_takes()
KESSANA_COMPARABLES = load_kessana_comparables()
# Where each negotiating position lands (structure, not numbers): signing takes the demand, a reasoned counter
# settles in the middle, a threat Halden cannot carry out is called and the government goes harsh.
KESSANA_POSITION_OUTCOME = {"accept": "demanded", "counter": "mid", "threaten": "harsh"}
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
    sgd_hedge: float = 0.0         # USD m of Singapore dollars sold forward for next quarter
    projects: dict = field(default_factory=dict)  # project key -> "hold" | "commit" | "pause" | "cancel" (pause and cancel act on a project under way)
    responses: dict = field(default_factory=dict)  # Cordell cluster -> "ignore" | "match" the rival's street cut
    capacity_response: str = "hold"  # hold | match the rival's Gulf Coast expansion
    opec_case: str = "fails"       # fails | partial | full: the OPEC+ outcome Geneva plans for (sets crude bought ahead)
    rebrand: dict = field(default_factory=dict)  # Cordell region -> "keep" | "rebrand" (put the Halden name on the stations)
    # Carried from the team's own history, not set on a page (the runner works them out):
    kessana_position: str = "none"  # none | accept | counter | threaten | exit: Halden's one-time answer to the Kessana government (Q3 2029)
    portfolio: dict = field(default_factory=dict)  # Q4 2029 portfolio: project key -> "go" | "hold" (one-time; the money goes out over five years)
    norway_wage: str = "none"      # none | refuse | half | accept: Halden's answer to the Norwegian union's 8% (Q1 2030, one-time)
    turnaround: str = "none"       # none | now | wait: Baton Rouge's turnaround at the contractor peak now, or off-peak next quarter (Q1 2030, one-time)
    delacroix_cover: bool = False   # the Q4 2027 crude price left Baton Rouge reporting strong, so Marcus can resist run cuts
    straits_strained: bool = False  # the team kept asking Singapore for more than Straits Pacific allows


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
    projects: dict = field(default_factory=dict)   # committed project key -> quarters since commitment (a paused quarter does not count)
    cancelled: list = field(default_factory=list)  # projects cancelled after going ahead: their cash flows stopped; the outlay stays on the books
    rebranded: dict = field(default_factory=dict)  # rebranded region -> quarters since the rebrand
    prev_br_run: float = 96.0                      # how hard Baton Rouge ran last quarter (a run cut in a recession can be resisted)
    kessana_take: float = 0.62                     # the government's share of Kessana profit oil (opening_state sets it from the data)
    kessana_exited: bool = False                   # Halden handed the Kessana block back
    portfolio: dict = field(default_factory=dict)  # portfolio project key -> quarters since the go-ahead
    europe_sold: bool = False                      # the European stations have been sold (the line stops the quarter after)
    norway_wage_uplift: float = 0.0                # the raise settled with the Norwegian union, on the wage bill, for good
    turnaround_pending: bool = False               # Baton Rouge's turnaround was put off to next quarter


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
                 asset_health=C["opening_asset_health"], kessana_take=KESSANA_TAKES["current"])


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


def window3_nonfuel(avg_aggression):
    """Window 3: the class's Q3 2028 price aggression sets the Cordell shop margin in Q1 2029, within 15% of base."""
    base = C["window3_base_nonfuel"]
    v = base - C["window3_slope"] * (avg_aggression - C["window3_pivot"])
    return min(base * (1 + C["window3_bound"]), max(base * (1 - C["window3_bound"]), v))


def price_aggression(dec: Decisions):
    """Share of the Cordell markets where a team matched the rival's cut (0 = held every price, 1 = matched everywhere)."""
    return sum(1 for c in CORDELL if dec.responses.get(c["key"], "ignore") == "match") / len(CORDELL)


def capacity_payoff(action, rival_builds):
    """Quarterly payoff of Halden's answer to the rival's expansion once the rival has shown its hand."""
    if not rival_builds:
        return 0.0
    return CAPACITY_GAME[(action, "builds")] / 4


def window2_shift(br_share):
    """Window 2: the share of the class that went ahead with the Baton Rouge upgrade in Q2 2028 moves the
    Q4 2028 Gulf Coast margin. Overbuilding costs twice what restraint earns."""
    pivot = C["window2_pivot"]
    if br_share > pivot:
        return -C["window2_slope_above"] * (br_share - pivot)
    return C["window2_slope_below"] * (pivot - br_share)


def opec_market(mkt, outcome, br_share=0.5):
    """The Q4 2028 prices once OPEC+ has decided: WTI moves by the outcome, the Gulf Coast margin compresses
    35 cents a dollar and carries the Window 2 shift. Returns a new market row with the shock recorded."""
    d = OPEC[outcome]["dwti"]
    m = dict(mkt)
    m["wti"] = mkt["wti"] + d
    m["gc"] = mkt["gc"] + C["crack_per_wti"] * d + window2_shift(br_share)
    m["wti_shock"] = d
    m["opec_outcome"] = outcome
    return m


def expected_wti(wti_pre):
    return wti_pre + sum(s["p"] * s["dwti"] for s in OPEC.values())


def rebrand_cost(key):
    """Putting the Halden name on one region's stations: the total programme cost, charged per site."""
    return C["rebrand_total_cost"] / sum(m["sites"] for m in REBRAND.values()) * REBRAND[key]["sites"]


def rebrand_gain_per_year(key, nonfuel_per_gal):
    """What a rebranded region earns a year: the Halden name's pull less the Cordell name's, per fill, times fills,
    scaled by the shop margin the class is living with (a price war shrinks what a better name is worth)."""
    m = REBRAND[key]
    return (m["halden"] - m["keep"]) * m["sites"] * C["rebrand_fills_per_site_year"] / 1e6 * nonfuel_per_gal / C["window3_base_nonfuel"]


def demand_hit(product):
    """How far demand for one product falls in the recession quarter: its income elasticity times the fall in the economy."""
    return ELASTICITY[product] * C["recession_gdp_change"]


def refinery_hit(key):
    """How far a refinery's runs fall in the recession: its product mix, product by product. Jet-heavy plants fall furthest."""
    return sum(YIELDS[key][p] * demand_hit(p) for p in ("gasoline", "diesel", "jet"))


def delacroix_has_cover(q4_decision: Decisions, q4_wti):
    """Marcus can resist a run cut if the Q4 2027 crude price left Baton Rouge reporting above target: at cost, or a
    price well below market. A market price gives him nothing to point to."""
    market, cost = transfer_prices(q4_wti)
    if q4_decision.tp_method == "cost":
        return True
    if q4_decision.tp_method == "market":
        return False
    return float(q4_decision.tp_value) < market * (1 - C["geneva_band"])


def straits_is_strained(recent_decisions):
    """Asking Singapore for more than Straits Pacific allows, again and again, strains the partnership."""
    over = sum(1 for d in recent_decisions if d.sg_request > C["sg_accept_max"])
    return over >= C["straits_strained_quarters"]


def kessana_profit_oil():
    """Profit oil per barrel at the valuation's long-run Brent: what the government and Halden split."""
    return C["kessana_planning_brent"] - C["kessana_discount_to_brent"] - C["kessana_lifting"]


def kessana_annual_mbbl():
    return C["kessana_remaining_mbbl"] / C["kessana_production_years"]


def kessana_annuity_factor():
    r, n = C["kessana_risk_rate"], int(C["kessana_production_years"])
    return (1 - (1 + r) ** -n) / r


def kessana_pv_stay(take):
    """What staying in Kessana is worth (USD m) at a given government take. Sunk capital is not an input."""
    return kessana_profit_oil() * (1 - take) * kessana_annual_mbbl() * kessana_annuity_factor()


def kessana_indifference_take():
    """The take at which staying is worth exactly the exit value: how far the government could push on economics alone."""
    return 1 - C["kessana_exit_value"] / (kessana_profit_oil() * kessana_annual_mbbl() * kessana_annuity_factor())


def portfolio_discretionary():
    """What is left of the five-year envelope after the sustaining floor."""
    return C["portfolio_envelope"] - C["portfolio_sustaining_floor"]


def portfolio_npv(key, carbon, demand_code):
    """A project's value in one world: its base value at $40 carbon, moved by the carbon price and by how fast oil demand falls."""
    p = PORTFOLIO[key]
    return p["npv_base"] + p["carbon_sens"] * (carbon - C["portfolio_carbon_base"]) + p["demand_sens"] * demand_code


def portfolio_value_in_world(chosen, carbon_key, demand_key):
    """What a set of projects is worth in one world (USD m): the sum of each project's value there."""
    carbon = SCENARIOS["carbon"][carbon_key]["value"]
    demand = SCENARIOS["demand"][demand_key]["value"]
    return sum(portfolio_npv(k, carbon, demand) for k in chosen)


def portfolio_check(chosen, rotterdam_closed=False):
    """Why a set of projects can't be funded, in order: [] when it can. The divestment's proceeds widen the envelope."""
    problems = []
    cost = sum(PORTFOLIO[k]["cost"] for k in chosen)
    if cost > portfolio_discretionary() + 1e-9:
        problems.append("envelope")
    for b in BUCKETS:
        spend = sum(PORTFOLIO[k]["cost"] for k in chosen if PORTFOLIO[k]["bucket"] == b and PORTFOLIO[k]["cost"] > 0)
        if spend > BUCKETS[b]["ceiling"] + 1e-9:
            problems.append(f"bucket:{b}")
    if rotterdam_closed and any(PORTFOLIO[k]["needs_rotterdam"] for k in chosen):
        problems.append("rotterdam_closed")
    return problems


def portfolio_feasible_sets(rotterdam_closed=False):
    """Every non-empty set of projects that can be funded."""
    keys = list(PORTFOLIO)
    out = []
    for mask in range(1, 1 << len(keys)):
        chosen = [k for i, k in enumerate(keys) if mask >> i & 1]
        if not portfolio_check(chosen, rotterdam_closed):
            out.append(chosen)
    return out


def norway_wage_outcome(answer):
    """(stoppage weeks this quarter, raise for good). The union strikes over anything less than its demand, and the
    government ends a long stoppage by arbitration at the union's rate; a half offer is taken after a short one."""
    if answer == "refuse":
        return C["strike_weeks_refuse"], C["norway_union_demand"]
    if answer == "half":
        return C["strike_weeks_half"], C["norway_half_offer"]
    if answer == "accept":
        return 0.0, C["norway_union_demand"]
    return 0.0, 0.0


def norway_concession_after_tax(gross):
    """What a Norwegian wage concession costs Halden after the 78% petroleum tax (the Week 13 tax shield)."""
    return gross * (1 - C["norway_tax_rate"])


def permian_marginal_revenue_product_k():
    """What one more Permian worker earns Halden a year, in $k: barrels times margin."""
    return C["permian_marginal_worker_bbl"] * C["permian_margin_per_bbl"] / 1e3


def turnaround_peak_cost():
    return C["turnaround_labor_base"] * C["turnaround_peak_multiplier"]


def turnaround_delay_expected_cost():
    return C["turnaround_labor_base"] + C["turnaround_outage_probability"] * C["turnaround_outage_cost"]


def kessana_outcome(position, state: State):
    """(take, exited) after the one-time talks: the position only acts in the quarter the government asks."""
    if position == "exit":
        return state.kessana_take, True
    if position in KESSANA_POSITION_OUTCOME:
        return KESSANA_TAKES[KESSANA_POSITION_OUTCOME[position]], False
    return state.kessana_take, state.kessana_exited


def inventory_days(case):
    return C[f"opec_inventory_days_{case}"]


def step(state: State, dec: Decisions, mkt: dict):
    """Run one quarter. Returns (lines dict in USD m, kpi inputs, new state)."""
    lines = {}
    notes = {}
    wti = mkt["wti"]
    shock = mkt.get("wti_shock", 0.0)   # how far an OPEC+ outcome moved WTI this quarter (0 otherwise)
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
    # The Norwegian union (Q1 2030, one-time): a stoppage costs the operated fields' margin for its weeks (the barrels stay in
    # the ground; the variable lifting is saved); the settled raise lands on the wage bill for good, and Norway's 78% tax
    # absorbs most of it in the tax line.
    wage_uplift = state.norway_wage_uplift
    stoppage_weeks = 0.0
    if mkt.get("labor", False) and dec.norway_wage != "none":
        stoppage_weeks, wage_uplift = norway_wage_outcome(dec.norway_wage)
        if stoppage_weeks > 0:
            notes["norway_stoppage_weeks"] = stoppage_weeks
    stoppage_share = stoppage_weeks / C["weeks_per_quarter"]
    lines["norway_stoppage"] = -nor_vol * (1 - cut) * stoppage_share * (brent - C["norway_discount_to_brent"] - nor_saved_per_bbl) * D / 1e6
    lines["norway_operated"] += lines["norway_stoppage"]
    lines["norway_wages"] = -C["norway_wage_bill"] * wage_uplift / 4
    lines["norway_operated"] += lines["norway_wages"]
    lines["norway_partner_run"] = C["norway_nonop_volume"] * nor_margin_full * D / 1e6
    # Kessana: the government's share of profit oil is set by the contract until the Q3 2029 talks, then by what Halden
    # answered. Handing the block back ends the line, returns the exit value and takes the book value off capital.
    kes_take, kes_exited = state.kessana_take, state.kessana_exited
    kes_exit_proceeds = 0.0
    if mkt.get("kessana", False) and dec.kessana_position != "none":
        kes_take, kes_exited = kessana_outcome(dec.kessana_position, state)
        if kes_exited and not state.kessana_exited:
            kes_exit_proceeds = C["kessana_exit_value"]
            notes["kessana_exit"] = True
    kes_profit_oil = brent - C["kessana_discount_to_brent"] - C["kessana_lifting"]
    lines["kessana"] = 0.0 if kes_exited else C["kessana_volume"] * kes_profit_oil * (1 - kes_take) * D / 1e6
    # Already inside kessana; shown on its own: what the bigger share costs Halden against the original contract.
    lines["kessana_take_change"] = 0.0 if kes_exited else -C["kessana_volume"] * kes_profit_oil * (kes_take - KESSANA_TAKES["current"]) * D / 1e6
    # What the field would have earned this quarter at the carried take, once Halden has left (for the story; not a line)
    kes_forgone = C["kessana_volume"] * kes_profit_oil * (1 - state.kessana_take) * D / 1e6 if kes_exited else 0.0
    lines["gas_other"] = C["gas_other_ebitda"]
    upstream_base = lines["permian"] + lines["norway_operated"] + lines["norway_partner_run"] + lines["kessana"] + lines["gas_other"]

    # ---------------- Refineries ----------------
    recession = mkt.get("recession", False)
    # In the recession, a Baton Rouge run cut can be resisted: Marcus delivers only part of it if he has cover.
    br_run = dec.br_run
    if recession and dec.delacroix_cover and dec.br_run < state.prev_br_run:
        br_run = dec.br_run + C["delacroix_cover_share"] * (state.prev_br_run - dec.br_run)
        notes["br_run_resisted"] = br_run
    br_tp_bbl = C["br_capacity"] * br_run / 100.0
    if recession:
        br_tp_bbl *= 1 + refinery_hit("br")   # product demand falls by the plant's mix; unsold barrels aren't run
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
        if recession:
            rot_tp *= 1 + refinery_hit("rot")
        rot = rot_tp * (mkt["nwe"] + C["rot_complexity"] - C["rot_variable_opex"]) * D / 1e6 - rot_fixed
    lines["rotterdam"] = rot * eur_f   # Rotterdam earns and spends in euros: a natural hedge
    lines["rotterdam_one_time"] = one_time

    sg_run = min(max(dec.sg_request, C["sg_accept_min"]), C["sg_accept_max"])
    if recession and dec.straits_strained:
        sg_run = C["sg_accept_min"]   # a strained partner protects itself and runs Singapore at the minimum, whatever Halden asks
        notes["sg_cut_by_partner"] = True
    notes["sg_accepted"] = sg_run
    sg_tp = C["sg_capacity"] * C["sg_halden_share"] * sg_run / 100.0
    if recession:
        sg_tp *= 1 + refinery_hit("sg")
    lines["singapore"] = sg_tp * (mkt["sg"] + C["sg_complexity"] - C["sg_opex"]) * D / 1e6 * sgd_f
    # Projects committed in earlier quarters pay a quarter of each year's cash flow, cut to what such
    # projects really deliver. A Rotterdam project stops if Rotterdam closes.
    # A project under way can be paused (its clock stops for the quarter and Halden pays the cost of capital on the
    # outlay) or cancelled (its cash flows stop for good; nothing comes back, and the outlay stays in capital employed).
    proj_age = {}
    proj_lines = {"refineries": 0.0, "oil_fields": 0.0}
    cancelled = list(state.cancelled)
    pause_cost = 0.0
    capital_rate = mkt.get("capital_rate", capital_terms(0.5)["rate"])
    for key, age in state.projects.items():
        p = PROJECTS[key]
        choice = dec.projects.get(key, "commit")
        if choice == "cancel":
            cancelled.append(key)
            notes.setdefault("projects_cancelled", []).append(key)
            continue
        if choice == "pause":
            proj_age[key] = age
            charge = p["outlay"] * capital_rate / 4
            proj_lines[p["segment"]] -= charge
            pause_cost += charge
            notes.setdefault("projects_paused", []).append(key)
            continue
        age = age + 1
        proj_age[key] = age
        year = (age - 1) // 4 + 1
        if year > len(p["cf"]) or (key == "rot_upgrade" and rot_status == "closed"):
            continue
        proj_lines[p["segment"]] += p["cf"][year - 1] * p["haircut"] / 4
    commit_outlay = 0.0
    for key, choice in dec.projects.items():
        if choice == "commit" and key not in state.projects and key not in cancelled:
            proj_age[key] = 0
            commit_outlay += PROJECTS[key]["outlay"]
    lines["projects_refining"] = proj_lines["refineries"]
    lines["projects_upstream"] = proj_lines["oil_fields"]
    # The rival's Gulf Coast expansion: once it is built (from Q4 2028), Halden's answer sets a yearly payoff.
    lines["capacity_game"] = capacity_payoff(dec.capacity_response, mkt.get("rival_builds", False))
    # Baton Rouge's turnaround (Q1 2030, one-time): contractor crews at the spring peak now, or off-peak next quarter with
    # a chance of a breakdown in between and three points off plant condition for running past due.
    turnaround_pending = state.turnaround_pending
    lines["turnaround"] = 0.0
    lines["turnaround_outage"] = 0.0
    health_penalty = 0.0
    if turnaround_pending:
        lines["turnaround"] = -C["turnaround_labor_base"]
        if mkt.get("outage", False):
            lines["turnaround_outage"] = -C["turnaround_outage_cost"]
            notes["turnaround_outage"] = True
        turnaround_pending = False
    elif mkt.get("labor", False) and dec.turnaround == "now":
        lines["turnaround"] = -turnaround_peak_cost()
    elif mkt.get("labor", False) and dec.turnaround == "wait":
        turnaround_pending = True
        health_penalty = C["turnaround_health_penalty"]
        notes["turnaround_delayed"] = True
    refining = (lines["baton_rouge"] + lines["rotterdam"] + lines["rotterdam_one_time"] + lines["singapore"]
                + lines["projects_refining"] + lines["capacity_game"] + lines["turnaround"] + lines["turnaround_outage"])

    # ---------------- Trading ----------------
    lines["geneva_desk"] = C["geneva_base_desk"]
    # Ahead of an OPEC+ decision, Geneva buys crude for Baton Rouge at the pre-decision price according to the
    # case the team plans for. It gains if crude rises and loses if it falls, and the money tied up costs interest.
    bought_ahead = 0.0
    carry = 0.0
    if mkt.get("opec", False):
        days = inventory_days(dec.opec_case)
        wti_pre = wti - shock
        bought_ahead = days * br_tp_bbl * shock / 1e6
        carry = -days * br_tp_bbl * wti_pre * C["opec_inventory_carry_annual"] / 4 / 1e6
    lines["crude_bought_ahead"] = bought_ahead
    lines["inventory_carry"] = carry
    trading = lines["geneva_desk"] + geneva + bought_ahead + carry

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

    # A rival's street cut (from Q3 2028): in a market where the team holds its price, drivers drift to the
    # rival; where it matches, Cordell gives up the cut on every gallon and keeps the drivers.
    # A crude shock reaches the pump at 60 cents on the dollar, and drivers barely react (-0.05).
    crude_vf = 1 + C["retail_crude_elasticity"] * C["retail_crude_passthrough"] * (shock / C["gal_per_bbl"]) / C["pump_base"]
    if recession:
        crude_vf *= 1 + demand_hit("gasoline")   # drivers still drive to work; gasoline falls least
    cord_gal_total = C["cordell_sites"] * C["cordell_gal_per_site_qtr"] * crude_vf
    nonfuel_per_gal = mkt.get("cordell_nonfuel") or C["cordell_nonfuel_per_gal"]
    rival_cut = mkt.get("rival_cut", 0.0)
    cord_fuel = cord_nonfuel = 0.0
    match_cost = ignore_cost = 0.0
    for c in CORDELL:
        vf, delta = volume_factor(c, dec.offsets[c["key"]])
        gal = cord_gal_total * c["share"] * vf
        cut_given = 0.0
        if rival_cut > 0:
            if dec.responses.get(c["key"], "ignore") == "match":
                cut_given = rival_cut
                match_cost += gal * rival_cut
            else:
                lost = gal * (-c["e"]) * rival_cut / C["pump_base"]
                ignore_cost += lost * (C["cordell_fuel_margin"] + delta + nonfuel_per_gal * C["cordell_nonfuel_halden_share"])
                gal -= lost
        cord_fuel += gal * (C["cordell_fuel_margin"] + delta - cut_given)
        cord_nonfuel += gal * nonfuel_per_gal * C["cordell_nonfuel_halden_share"]
    # Regions rebranded in an earlier quarter earn the Halden name's pull from the quarter after the work.
    rebrand_age = {}
    rebrand_gain = 0.0
    for key, age in state.rebranded.items():
        rebrand_age[key] = age + 1
        rebrand_gain += rebrand_gain_per_year(key, nonfuel_per_gal) / 4
    rebrand_outlay = 0.0
    for key, choice in dec.rebrand.items():
        if choice == "rebrand" and key not in state.rebranded:
            rebrand_age[key] = 0
            rebrand_outlay += rebrand_cost(key)
    cord_nonfuel += rebrand_gain * 1e6
    lines["cordell_fuel"] = cord_fuel / 1e6
    lines["cordell_shop"] = cord_nonfuel / 1e6
    lines["cordell_price_match"] = -match_cost / 1e6   # already inside cordell_fuel; shown on its own
    lines["rebrand_gain"] = rebrand_gain                # already inside cordell_shop; shown on its own

    # The Q4 2029 portfolio (one-time): what the team went ahead with. Its money goes out evenly over five years from the
    # quarter after the go-ahead; selling the European stations brings the proceeds in now and ends their line next quarter.
    portfolio_age = {}
    for key, age in state.portfolio.items():
        portfolio_age[key] = age + 1
    portfolio_new = []
    if mkt.get("carbon", 0.0) > 0 and not state.portfolio:
        for key, choice in dec.portfolio.items():
            if choice == "go" and key in PORTFOLIO:
                portfolio_age[key] = 0
                portfolio_new.append(key)
    europe_sold = state.europe_sold or "euro_retail_divest" in portfolio_new
    divest_proceeds = -PORTFOLIO["euro_retail_divest"]["cost"] if "euro_retail_divest" in portfolio_new else 0.0
    portfolio_capex = sum(PORTFOLIO[k]["cost"] / (C["portfolio_years"] * 4) for k, age in portfolio_age.items()
                          if PORTFOLIO[k]["cost"] > 0 and 1 <= age <= C["portfolio_years"] * 4)

    eu_factor = state.europe_volume_factor * (1 - C["europe_volume_decline_qtr"])
    eu_gal_total = C["europe_sites"] * C["europe_gal_per_site_qtr"] * eu_factor * crude_vf
    eu = 0.0
    for c in EUROPE:
        vf, delta = volume_factor(c, dec.offsets[c["key"]])
        gal = eu_gal_total * c["share"] * vf
        eu += gal * (C["europe_fuel_margin"] + delta + C["europe_nonfuel_per_gal"])
    europe_gone = state.europe_sold   # sold in an earlier quarter: the stations are someone else's now
    europe_share_fixed = C["europe_sites"] / (C["europe_sites"] + C["cordell_sites"])
    lines["europe_stations"] = 0.0 if europe_gone else eu / 1e6 * eur_f
    lines["retail_fixed"] = -C["retail_fixed_cost"] * (1 - europe_share_fixed if europe_gone else 1.0)
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
        if h.get("sgd", 0.0):
            # Singapore earns in Singapore dollars: selling them forward pays when the US dollar strengthens.
            settle += h["sgd"] * (1 - h["sgd_rate"] / mkt["usdsgd"])
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
    # Norway's petroleum tax absorbs 78% of the wage raise, not the usual rate. norway_tax_shield is the whole 78%
    # (what the story quotes); the tax line already carries the usual rate on the lower EBITDA, so only the extra is taken off here.
    norway_tax_shield = -lines["norway_wages"] * C["norway_tax_rate"]
    tax = C["tax_rate"] * max(0.0, ebitda - da) + lines["norway_wages"] * (C["norway_tax_rate"] - C["tax_rate"])
    new_capital = commit_outlay + rebrand_outlay + portfolio_capex   # the rebrand and the portfolio are capital spending, treated like projects (decision S2)
    capex = C["other_sustaining_capex"] + dec.rigs * C["rig_capex_per_qtr"] + new_capital
    fcf = ebitda - tax - capex
    new_ce = state.capital_employed + capex - da - (C["kessana_book_value"] if kes_exit_proceeds > 0 else 0.0)
    new_nd = state.net_debt - fcf + C["shareholder_payout"] - kes_exit_proceeds - divest_proceeds   # sale proceeds pay down debt; write-downs are non-cash

    # ---------------- Plant condition ----------------
    health = state.asset_health
    health -= health_penalty
    health -= 0.5 * max(0.0, br_run - C["br_wear_threshold"])
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
        # Before growth projects (decision S2, Vikram 9 Oct 2026): sustaining cash generation, so a sound project
        # isn't punished in the score in the quarter its money goes out. Net debt still carries it.
        "free_cash_flow": fcf + new_capital,
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
    if dec.sgd_hedge > 0:
        new_hedges["sgd"], new_hedges["sgd_rate"] = float(dec.sgd_hedge), mkt["usdsgd"]
    fx_effect = 0.0
    if fx:
        fx_effect = (lines["rotterdam"] * (1 - 1 / eur_f) + lines["europe_stations"] * (1 - 1 / eur_f)
                     + lines["singapore"] * (1 - 1 / sgd_f)
                     + (C["norway_lifting"] - nor_lifting) * (nor_vol * (1 - cut * C["norway_lifting_variable_share"]) + C["norway_nonop_volume"]) * D / 1e6)

    new_state = State(permian_prod=next_prod, prev_rigs=dec.rigs, rot_status=rot_status,
                      capital_employed=new_ce, net_debt=new_nd, asset_health=health,
                      europe_volume_factor=eu_factor, held_up=held_up, held_down=held_down,
                      hedges=new_hedges, projects=proj_age, cancelled=cancelled, rebranded=rebrand_age, prev_br_run=br_run,
                      kessana_take=kes_take, kessana_exited=kes_exited, portfolio=portfolio_age, europe_sold=europe_sold,
                      norway_wage_uplift=wage_uplift, turnaround_pending=turnaround_pending)
    money = {"ebitda": ebitda, "da": da, "tax": tax, "capex": capex, "fcf": fcf,
             "capital_employed_end": new_ce, "net_debt_end": new_nd}
    ops = {"tp": tp, "market_tp": market_tp, "cost_tp": cost_tp, "permian_prod": prod,
           "br_throughput": br_tp_bbl, "rot_throughput": rot_tp, "sg_accepted": sg_run, "rot_status": rot_status,
           "fx_effect": fx_effect, "project_outlay": commit_outlay, "project_pause_cost": pause_cost, "nwe": mkt["nwe"],
           "rival_match_cost": match_cost / 1e6, "rival_ignore_cost": ignore_cost / 1e6,
           "wti_shock": shock, "gc": mkt["gc"], "wti": wti, "rebrand_outlay": rebrand_outlay,
           "nonfuel_per_gal": nonfuel_per_gal, "br_run": br_run,
           "kessana_take": kes_take, "kessana_exit_proceeds": kes_exit_proceeds, "kessana_forgone": kes_forgone,
           "portfolio_capex": portfolio_capex, "divest_proceeds": divest_proceeds, "carbon": mkt.get("carbon", 0.0),
           "norway_wage_uplift": wage_uplift, "norway_stoppage_weeks": stoppage_weeks, "norway_tax_shield": norway_tax_shield,
           "turnaround_pending": 1.0 if turnaround_pending else 0.0}
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
