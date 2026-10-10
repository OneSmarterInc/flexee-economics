"""16A-style validation for the operating model. Writes VALIDATION.md and exits non-zero on failure."""
import copy
import csv
import hashlib
import subprocess
import sys
from pathlib import Path

import halden_model as hm
import run_reference as rr

ROOT = Path(__file__).resolve().parent.parent
checks = []


def check(name, ok, detail):
    checks.append((name, bool(ok), detail))


def q(quarter):
    return next(m for m in hm.load_market() if m["quarter"] == quarter)


hm.calibrate_gas_other(3750.0)
start, hist = hm.run_history()

# 1 Calibration to the KPI package's opening state
q4 = hist[-1][1]
check("Q4 2026 EBITDA calibrates to 3,750 (15,000 a year)", abs(q4[2]["ebitda"] - 3750) < 1e-6, f"{q4[2]['ebitda']:.4f}")
opening = hm.opening_state()
roace_open = 100 * 4 * (3750 - opening.capital_employed * 0.06 / 4) * 0.7 / opening.capital_employed
check("Opening return on capital matches KPI package (12.7%)", abs(roace_open - 12.70) < 0.05, f"{roace_open:.2f}%")
check("Opening debt-to-earnings matches KPI package (1.30x)", abs(opening.net_debt / 15000 - 1.30) < 1e-9, f"{opening.net_debt/15000:.2f}x")

# 2 Rotterdam history: lost money in 3 of 4 2026 quarters
losses = sum(1 for _, out in hist if out[0]["rotterdam"] < 0)
check("Rotterdam lost money in 3 of 4 quarters of 2026", losses == 3, f"{losses} losing quarters")

# 3 Transfer price moves profit inside Halden but never changes the total
m4 = q("2027Q4")
results = {}
for label, kw in {"cost": dict(tp_method="cost"), "market": dict(tp_method="market"), "lazy": dict(tp_method="other", tp_value=46.20)}.items():
    st = copy.deepcopy(start)
    out = hm.step(st, hm.Decisions(**kw), m4)
    results[label] = out
tot = {k: v[2]["ebitda"] for k, v in results.items()}
check("Total EBITDA identical at cost, market and in-between crude prices", max(tot.values()) - min(tot.values()) < 1e-9,
      ", ".join(f"{k} {v:.4f}" for k, v in tot.items()))
shift = results["cost"][0]["internal_crude_shift"] - results["market"][0]["internal_crude_shift"]
expected = (73.70 - 18.70) * 120000 * 91.25 / 1e6
check("Moving from market ($73.70) to cost ($18.70) shifts $55/bbl x 120,000 bbl/day to the refinery", abs(shift - expected) < 1e-6, f"{shift:.3f} vs {expected:.3f}")

# 4 Geneva rule from the Week 4 engine
mk, co = hm.transfer_prices(74.0)
g = lambda tp: hm.geneva_capture_per_bbl(tp, mk, co) * 40000 * 91.25 / 1e6
check("Geneva earns nothing at cost, at market, inside the 10% bands, or above market",
      all(abs(g(x)) < 1e-12 for x in (18.70, 73.70, 20.0, 67.0, 80.0)), "0 at 18.70, 20.00, 67.00, 73.70, 80.00")
check("Geneva earns 35% of the gap at $46.20: 0.35 x 27.50 x 40,000 x 91.25", abs(g(46.20) - 0.35 * 27.5 * 40000 * 91.25 / 1e6) < 1e-9, f"${g(46.20):.3f}M")
check("Market crude price at Q4 2027 equals the Week 4 package ($73.70)", abs(mk - 73.70) < 1e-9 and abs(co - 18.70) < 1e-9, f"market {mk:.2f}, cost {co:.2f}")

# 5 Shutdown point: running Rotterdam beats pausing it while the crack is above $2.60
def rot_ebitda(posture, run=82, crack=4.60):
    st = copy.deepcopy(start)
    mm = dict(m4, nwe=crack)
    return hm.step(st, hm.Decisions(rot_posture=posture, rot_run=run), mm)[0]["rotterdam"]
check("At a $4.60 European margin, running Rotterdam loses less than pausing it", rot_ebitda("run") > rot_ebitda("idle"),
      f"run {rot_ebitda('run'):.1f} vs pause {rot_ebitda('idle'):.1f}")
check("At a $2.00 European margin (below the $2.60 shutdown point), pausing beats running", rot_ebitda("idle", crack=2.0) > rot_ebitda("run", crack=2.0),
      f"run {rot_ebitda('run', crack=2.0):.1f} vs pause {rot_ebitda('idle', crack=2.0):.1f}")
check("Running Rotterdam harder helps while the margin is above the shutdown point", rot_ebitda("run", 95) > rot_ebitda("run", 78),
      f"95%: {rot_ebitda('run',95):.1f} vs 78%: {rot_ebitda('run',78):.1f}")

# 6 Restart and closure
st = copy.deepcopy(start)
o1 = hm.step(st, hm.Decisions(rot_posture="idle"), q("2027Q1"))
o2 = hm.step(o1[-1], hm.Decisions(rot_posture="run"), q("2027Q2"))
check("Restarting a paused Rotterdam charges the $85M restart once", abs(o2[0]["rotterdam_one_time"] + 85) < 1e-9, f"{o2[0]['rotterdam_one_time']:.1f}")
c1 = hm.step(copy.deepcopy(start), hm.Decisions(rot_posture="close"), q("2027Q1"))
c2 = hm.step(c1[-1], hm.Decisions(rot_posture="run"), q("2027Q2"))
check("Closing Rotterdam is permanent", c2[4]["rot_status"] == "closed" and c2[4]["rot_throughput"] == 0, c2[4]["rot_status"])

# 7 Norway: pumping less always lowers EBITDA (marginal barrels still earn money)
n_run = hm.step(copy.deepcopy(start), hm.Decisions(norway="run"), m4)[2]["ebitda"]
n_cut = hm.step(copy.deepcopy(start), hm.Decisions(norway="cut"), m4)[2]["ebitda"]
check("Pumping 10% less in Norway lowers EBITDA", n_cut < n_run, f"cut {n_cut:.1f} vs run {n_run:.1f}")

# 8 Rigs: marginal value crosses the $40M rig cost between 10 and 11 rigs at Q1 2027 prices
wti = q("2027Q1")["wti"]
marg = lambda r: (hm.permian_adds(r + 1) - hm.permian_adds(r)) * 91.25 * (wti - 15.5) / 0.08 / 1e6
check("The 11th rig pays for itself, the 12th does not, and the old presidents' 14th loses money", marg(10) > 40 and marg(11) < 40 and marg(13) < 40,
      f"11th rig worth ${marg(10):.1f}M, 12th ${marg(11):.1f}M, 14th ${marg(13):.1f}M vs $40M cost")
r_now = hm.step(copy.deepcopy(start), hm.Decisions(rigs=30), q("2027Q1"))
r_base = hm.step(copy.deepcopy(start), hm.Decisions(rigs=14), q("2027Q1"))
check("Rigs change production next quarter, not this one", abs(r_now[4]["permian_prod"] - r_base[4]["permian_prod"]) < 1e-9 and r_now[-1].permian_prod > r_base[-1].permian_prod,
      f"this quarter equal; next quarter {r_now[-1].permian_prod:.0f} vs {r_base[-1].permian_prod:.0f}")

# 9 Singapore accepts 80-95%
check("Straits Pacific holds Singapore between 80% and 95%",
      hm.step(copy.deepcopy(start), hm.Decisions(sg_request=100), m4)[4]["sg_accepted"] == 95
      and hm.step(copy.deepcopy(start), hm.Decisions(sg_request=60), m4)[4]["sg_accepted"] == 80, "100 -> 95, 60 -> 80")

# 10 Retail: raising price lowers volume more each quarter it is held; rivals match cuts over time
def cordell_fuel(seq):
    st = copy.deepcopy(start)
    outs = []
    for i, off in enumerate(seq):
        o = dict(rr.BASE); o["urban"] = off
        out = hm.step(st, hm.Decisions(offsets=o), q(f"2027Q{i+1}"))
        st = out[-1]
        outs.append(out[0]["cordell_shop"])
    return outs
up = cordell_fuel([7.0, 7.0, 7.0])
check("Holding a city price rise loses more shop traffic each quarter", up[0] > up[1] > up[2], " > ".join(f"{x:.3f}" for x in up))
dn = cordell_fuel([-3.0, -3.0, -3.0])
check("Holding a city price cut gains less traffic each quarter as rivals match", dn[0] > dn[1] > dn[2], " > ".join(f"{x:.3f}" for x in dn))

# 11 Currency (Q1 2028, Week 5 package): krone weakness helps, euro weakness hurts retail, Rotterdam hedges itself
m5, m6 = q("2028Q1"), q("2028Q2")
lift = hm.C["norway_lifting"] * hm.C["fx_ref_usdnok"] / m5["usdnok"]
check("Norwegian lifting falls from $28.00 to about $25.85 when the krone weakens to 11.05", abs(lift - 25.846154) < 1e-5, f"${lift:.4f}")
eu_change = m5["eurusd"] / hm.C["fx_ref_eurusd"] - 1
check("European station profit loses about 6.45% in dollars when the euro falls to 1.015", abs(eu_change + 0.064516) < 1e-5, f"{100*eu_change:.2f}%")
s4 = rr  # noqa
base5 = hm.step(copy.deepcopy(start), hm.Decisions(), dict(m5, fx_live=False, existing_eur_hedge=False))
live5 = hm.step(copy.deepcopy(start), hm.Decisions(), dict(m5, existing_eur_hedge=False))
rot_gross = (hm.C["rot_capacity"] * 0.82 * hm.C["rot_variable_opex"] + hm.C["rot_capacity"] * hm.C["rot_fixed_opex_per_bbl_capacity"]) * 91.25 / 1e6
rot_move = live5[0]["rotterdam"] - base5[0]["rotterdam"]
gross_move = rot_gross * eu_change
check("Rotterdam is a natural hedge: its net euro swing is under a fifth of the swing on its gross euro costs (Week 5: about a tenth)",
      abs(rot_move) < 0.20 * abs(gross_move), f"net {rot_move:.2f} vs gross {gross_move:.2f} ({100 * abs(rot_move / gross_move):.0f}%)")
check("Before 2028, currency effects are held at zero (rates moved less than 1%)",
      all(not m["fx_live"] for m in hm.load_market() if m["quarter"] < "2028Q1"), "fx_live = 0 for 2026-2027")
eh = hm.step(copy.deepcopy(start), hm.Decisions(), m5)[0]["hedges"]
check("The $300M euro forward sold before the shock pays $19.35M in Q1 2028", abs(eh - 19.354839) < 1e-5, f"${eh:.4f}M")

# 12 Hedges settle next quarter against the rates then
h5 = hm.step(copy.deepcopy(start), hm.Decisions(crude_hedge_pct=50, eur_hedge=600, nok_hedge=600), dict(m5, existing_eur_hedge=False))
st5 = h5[-1]
falls = hm.step(copy.deepcopy(st5), hm.Decisions(), dict(m6, wti=m5["wti"] - 5))[0]["hedges"]
rises = hm.step(copy.deepcopy(st5), hm.Decisions(), dict(m6, wti=m5["wti"] + 5))[0]["hedges"]
check("A crude hedge gains when oil falls and loses when it rises (euro and krone legs equal in both)", falls > rises and abs((falls - rises) - 10 * st5.hedges["crude_bbl_day"] * 91.25 / 1e6) < 1e-6,
      f"oil -$5: {falls:.1f}; oil +$5: {rises:.1f}")
eur_leg = 600 * (m5["eurusd"] - m6["eurusd"]) / m5["eurusd"]
check("Selling $600M of euros at 1.015 loses when the euro recovers to 1.031", eur_leg < 0, f"${eur_leg:.2f}M")

# 13 Window 1: the class's Q3 2027 European run rates set the Q1 2028 European margin
check("Window 1: at a 75% average the margin stays $4.60; at 90% it falls to $3.25; it never goes below $2.60",
      abs(hm.window1_nwe(0.75) - 4.60) < 1e-9 and abs(hm.window1_nwe(0.90) - 3.25) < 1e-9 and abs(hm.window1_nwe(1.0) - 2.60) < 1e-9,
      f"{hm.window1_nwe(0.75):.2f} / {hm.window1_nwe(0.90):.2f} / {hm.window1_nwe(1.0):.2f}")

# 14 Capital terms from Q4 2027 crude-price discipline (Week 6 package)
cost_d = hm.crude_price_discipline(hm.Decisions(tp_method="cost"), 74.0)
lazy_d = hm.crude_price_discipline(hm.Decisions(tp_method="other", tp_value=46.20), 74.0)
mkt_d = hm.crude_price_discipline(hm.Decisions(tp_method="market"), 74.0)
t = lambda xs: hm.capital_terms(sum(xs) / len(xs))
check("Capital envelope: all at cost $1,520M at 6.5%; mixed $1,150M at 8.5%; lax $950M at 11%",
      t([cost_d] * 3)["envelope"] == 1520 and t([cost_d, mkt_d, lazy_d])["envelope"] == 1150 and t([lazy_d] * 3)["envelope"] == 950
      and t([cost_d] * 3)["rate"] == 0.065 and t([lazy_d] * 3)["rate"] == 0.110, "1520 / 1150 / 950")

# 15 Projects pay from the quarter after commitment, cut to what such projects deliver
p1 = hm.step(copy.deepcopy(start), hm.Decisions(projects={"br_upgrade": "commit"}), m6)
p2 = hm.step(p1[-1], hm.Decisions(projects={"br_upgrade": "commit"}), m6)
check("A committed project costs its outlay now and pays a quarter of year one, at 88% of forecast, next quarter",
      abs(p1[2]["capex"] - 640 - (hm.C["other_sustaining_capex"] + 14 * 40)) < 1e-9 and p1[0]["projects_refining"] == 0
      and abs(p2[0]["projects_refining"] - 180 * 0.88 / 4) < 1e-9 and p2[2]["capex"] == hm.C["other_sustaining_capex"] + 14 * 40,
      f"outlay 640, then {p2[0]['projects_refining']:.2f} a quarter")
r1 = hm.step(copy.deepcopy(start), hm.Decisions(projects={"rot_upgrade": "commit"}), m6)
r2 = hm.step(r1[-1], hm.Decisions(rot_posture="close"), m6)
check("A Rotterdam project stops paying if Rotterdam closes", r2[0]["projects_refining"] == 0, "0 after closure")
check("The score counts free cash flow before new projects; net debt still carries the outlay",
      abs(p1[3]["free_cash_flow"] - p1[2]["fcf"] - 640) < 1e-9 and p1[2]["net_debt_end"] > hm.step(copy.deepcopy(start), hm.Decisions(), m6)[2]["net_debt_end"],
      f"score FCF {p1[3]['free_cash_flow']:.1f} vs cash FCF {p1[2]['fcf']:.1f}")

# 16 Competitive response (Q3 2028, Week 7 package): a rival cuts 6c a gallon in every Cordell market
m7 = q("2028Q3")
held = hm.step(copy.deepcopy(start), hm.Decisions(), m7)
matched = hm.step(copy.deepcopy(start), hm.Decisions(responses={c["key"]: "match" for c in hm.CORDELL}), m7)
per_cluster = []
ok = True
for c in hm.CORDELL:
    one = hm.step(copy.deepcopy(start), hm.Decisions(responses={c["key"]: "match"}), m7)
    mc = one[4]["rival_match_cost"]
    ic = held[4]["rival_ignore_cost"] - one[4]["rival_ignore_cost"]
    ok &= ic < mc / 20
    per_cluster.append(f"{c['key']}: hold {ic:.2f} vs match {mc:.1f}")
check("Holding price against the rival's cut costs under a twentieth of matching it, in every Cordell market", ok, "; ".join(per_cluster) + " ($M a quarter)")
check("Matching everywhere costs 6c on every Cordell gallon and shows as its own line",
      abs(matched[0]["cordell_price_match"] + 0.06 * hm.C["cordell_sites"] * hm.C["cordell_gal_per_site_qtr"] / 1e6) < 1e-6
      and matched[2]["ebitda"] < held[2]["ebitda"], f"{matched[0]['cordell_price_match']:.2f}")
check("Before the rival moves, the answer levers change nothing", abs(hm.step(copy.deepcopy(start), hm.Decisions(responses={"urban": "match"}), m6)[2]["ebitda"]
      - hm.step(copy.deepcopy(start), hm.Decisions(), m6)[2]["ebitda"]) < 1e-9, "Q2 2028 EBITDA equal")

# 17 The capacity game (Week 7 package): hold beats match once the rival builds; breakeven build probability 0.375
g = hm.CAPACITY_GAME
p = hm.C["rival_build_probability"]
ev_hold = p * g[("hold", "builds")] + (1 - p) * g[("hold", "bluffs")]
ev_match = p * g[("match", "builds")] + (1 - p) * g[("match", "bluffs")]
breakeven = (g[("match", "bluffs")] - g[("hold", "bluffs")]) / ((g[("match", "bluffs")] - g[("hold", "bluffs")]) + (g[("hold", "builds")] - g[("match", "builds")]))
check("Capacity game: at a 70% chance the rival builds, holding (-$28M a year) beats matching (-$80M); breakeven is 37.5%",
      abs(ev_hold + 28) < 1e-9 and abs(ev_match + 80) < 1e-9 and abs(breakeven - 0.375) < 1e-9, f"hold {ev_hold:.0f}, match {ev_match:.0f}, breakeven {breakeven:.3f}")
mb = dict(m7, rival_builds=True)
check("Once the rival builds, holding costs $10M a quarter and matching $35M, under Refineries",
      abs(hm.step(copy.deepcopy(start), hm.Decisions(), mb)[0]["capacity_game"] + 10) < 1e-9
      and abs(hm.step(copy.deepcopy(start), hm.Decisions(capacity_response="match"), mb)[0]["capacity_game"] + 35) < 1e-9
      and hm.step(copy.deepcopy(start), hm.Decisions(), m7)[0]["capacity_game"] == 0.0, "-10 / -35; 0 before it builds")

# 18 Window 3: the class's Q3 2028 price aggression sets the Q1 2029 shop margin
check("Window 3 reproduces the ledger: a price war 0.38, base 0.42, disciplined 0.45 a fill, bounded within 15% of base",
      abs(hm.window3_nonfuel(0.9) - 0.38) < 1e-9 and abs(hm.window3_nonfuel(0.5) - 0.42) < 1e-9 and abs(hm.window3_nonfuel(0.2) - 0.45) < 1e-9
      and abs(hm.window3_nonfuel(1.0) - 0.37) < 1e-9 and abs(hm.window3_nonfuel(0.0) - 0.47) < 1e-9, "0.38 / 0.42 / 0.45; floor 0.357 at full aggression")
check("A team that matches in two of four Cordell markets is half aggressive", hm.price_aggression(hm.Decisions(responses={"urban": "match", "rural": "match"})) == 0.5, "0.5")

# 19 OPEC+ (Q4 2028, Week 8 package): three outcomes, one shock that helps the oil fields and hurts the refinery
m8 = q("2028Q4")
check("Expected WTI across the three OPEC+ outcomes is $80.70 from a $74 start", abs(hm.expected_wti(m8["wti"]) - 80.70) < 1e-9, f"{hm.expected_wti(m8['wti']):.2f}")
outs = {k: hm.opec_market(m8, k, 0.5) for k in hm.OPEC}
check("Outcomes: the cut holds WTI 88 / margin 16.60; partly holds 81 / 19.05; fails 70 / 22.90 (crack -0.35 a dollar, Window 2 at pivot)",
      all(abs(outs["full"][k] - v) < 1e-9 for k, v in {"wti": 88.0, "gc": 16.60}.items())
      and all(abs(outs["partial"][k] - v) < 1e-9 for k, v in {"wti": 81.0, "gc": 19.05}.items())
      and all(abs(outs["fails"][k] - v) < 1e-9 for k, v in {"wti": 70.0, "gc": 22.90}.items()), "88/16.60, 81/19.05, 70/22.90")
full = hm.step(copy.deepcopy(start), hm.Decisions(), outs["full"])
fails = hm.step(copy.deepcopy(start), hm.Decisions(), outs["fails"])
check("Integration conflict: when the cut holds, the oil fields earn more and Baton Rouge earns less than when it fails",
      full[0]["permian"] > fails[0]["permian"] and full[0]["baton_rouge"] < fails[0]["baton_rouge"],
      f"Permian {full[0]['permian']:.0f} vs {fails[0]['permian']:.0f}; Baton Rouge {full[0]['baton_rouge']:.0f} vs {fails[0]['baton_rouge']:.0f}")
vf = 1 + hm.C["retail_crude_elasticity"] * hm.C["retail_crude_passthrough"] * (14 / hm.C["gal_per_bbl"]) / hm.C["pump_base"]
check("Drivers barely react: a $14 crude rise moves station volume -0.31%", abs((vf - 1) * 100 + 0.3125) < 1e-6, f"{(vf - 1) * 100:.4f}%")

# 20 Crude bought ahead: the planning case is a bet on the outcome, and the money tied up costs interest
plan = lambda case, outcome: hm.step(copy.deepcopy(start), hm.Decisions(opec_case=case), outs[outcome])[0]
br = hm.C["br_capacity"] * 0.96
gain_full = 30 * br * 14 / 1e6
carry = 30 * br * 74 * hm.C["opec_inventory_carry_annual"] / 4 / 1e6
check("Planning for the cut to hold buys 30 days of Baton Rouge crude ahead: +$210M if it holds, -$60M if it fails, less $24M of interest either way",
      abs(plan("full", "full")["crude_bought_ahead"] - gain_full) < 1e-6 and abs(plan("full", "fails")["crude_bought_ahead"] + 30 * br * 4 / 1e6) < 1e-6
      and abs(plan("full", "full")["inventory_carry"] + carry) < 1e-6 and plan("fails", "full")["crude_bought_ahead"] == 0.0 and plan("fails", "full")["inventory_carry"] == 0.0,
      f"+{gain_full:.1f} / {-30 * br * 4 / 1e6:.1f}; carry {carry:.1f}; planning for failure buys nothing")
check("Outside the OPEC+ quarter the planning case changes nothing", abs(hm.step(copy.deepcopy(start), hm.Decisions(opec_case="full"), m7)[2]["ebitda"]
      - hm.step(copy.deepcopy(start), hm.Decisions(), m7)[2]["ebitda"]) < 1e-9, "Q3 2028 EBITDA equal")

# 21 Window 2: the share of the class that went ahead with the Baton Rouge upgrade moves the Gulf Coast margin
check("Window 2: all building -$3.00; half 0; none +$1.50; overbuilding costs twice what restraint earns",
      abs(hm.window2_shift(1.0) + 3.0) < 1e-9 and abs(hm.window2_shift(0.5)) < 1e-9 and abs(hm.window2_shift(0.0) - 1.5) < 1e-9, "-3.00 / 0 / +1.50")
worst = hm.opec_market(m8, "full", 1.0)
check("Worst case (the cut holds and everyone built): Gulf Coast margin $13.60", abs(worst["gc"] - 13.60) < 1e-9, f"{worst['gc']:.2f}")

# 22 The rebrand (Q1 2029, Week 9 package): the Halden name is worth more where the Cordell name is worth less
cps = hm.C["rebrand_total_cost"] * 1e6 / sum(m["sites"] for m in hm.REBRAND.values())
check("Putting the Halden name on a station costs $79,070 (a $340M programme over 4,300 sites)", abs(cps - 79069.767442) < 1e-3, f"${cps:,.2f}")
gains = {k: hm.rebrand_gain_per_year(k, 0.42) for k in hm.REBRAND}
check("At a $0.42 shop margin the rebrand earns -$15.0M a year in the heartland, +$7.6M on the Gulf Coast, +$15.1M on the Southeast edge",
      abs(gains["core"] + 14.985) < 1e-6 and abs(gains["gulf"] - 7.56) < 1e-6 and abs(gains["edge"] - 15.12) < 1e-6, ", ".join(f"{k} {v:.3f}" for k, v in gains.items()))
pb = {k: hm.rebrand_cost(k) / gains[k] for k in ("gulf", "edge")}
partial = (hm.rebrand_cost("gulf") + hm.rebrand_cost("edge")) / (gains["gulf"] + gains["edge"])
check("Paybacks: Southeast edge 5.5 years, Gulf Coast 14.6; both together 8.5 years on $193.7M; all three regions earn only $7.7M a year",
      abs(pb["edge"] - 5.490956) < 1e-5 and abs(pb["gulf"] - 14.64255) < 1e-4 and abs(partial - 8.541487) < 1e-5 and abs(sum(gains.values()) - 7.695) < 1e-6,
      f"edge {pb['edge']:.2f}, gulf {pb['gulf']:.2f}, both {partial:.2f}; all three {sum(gains.values()):.3f}/yr")
war = (hm.rebrand_cost("gulf") + hm.rebrand_cost("edge")) / sum(hm.rebrand_gain_per_year(k, 0.38) for k in ("gulf", "edge"))
check("After a price war (shop margin $0.38) the same rebrand takes 9.4 years to pay back", abs(war - 9.440591) < 1e-5, f"{war:.2f} years")
m9 = q("2029Q1")
rb1 = hm.step(copy.deepcopy(start), hm.Decisions(rebrand={"edge": "rebrand"}), m9)
rb2 = hm.step(rb1[-1], hm.Decisions(rebrand={"edge": "rebrand"}), m9)
base9 = hm.step(copy.deepcopy(start), hm.Decisions(), m9)
check("Rebranding the Southeast edge costs $83.0M of capital now (added back in the score) and pays $3.78M a quarter in the shop from the next quarter",
      abs(rb1[4]["rebrand_outlay"] - 83.023256) < 1e-5 and rb1[0]["rebrand_gain"] == 0.0 and abs(rb1[3]["free_cash_flow"] - rb1[2]["fcf"] - 83.023256) < 1e-5
      and abs(rb2[0]["rebrand_gain"] - 15.12 / 4) < 1e-6 and abs(rb2[0]["cordell_shop"] - base9[0]["cordell_shop"] - 15.12 / 4) < 1e-6
      and rb2[4]["rebrand_outlay"] == 0.0, f"outlay {rb1[4]['rebrand_outlay']:.2f}, then {rb2[0]['rebrand_gain']:.3f} a quarter, charged once")
# Window 3 landing: a price-war class earns less in every Cordell shop in 2029
war9 = hm.step(copy.deepcopy(start), hm.Decisions(), dict(m9, cordell_nonfuel=hm.window3_nonfuel(0.9)))
calm9 = hm.step(copy.deepcopy(start), hm.Decisions(), dict(m9, cordell_nonfuel=hm.window3_nonfuel(0.2)))
check("Window 3 lands: a class that fought a price war earns less in the shops than one that held its prices",
      war9[0]["cordell_shop"] < base9[0]["cordell_shop"] < calm9[0]["cordell_shop"], f"{war9[0]['cordell_shop']:.1f} < {base9[0]['cordell_shop']:.1f} < {calm9[0]['cordell_shop']:.1f}")

# 23 The recession (Q2 2029, Week 10 package): uneven by product, so uneven by refinery
hits = {p: hm.demand_hit(p) for p in ("gasoline", "diesel", "jet")}
check("Demand falls 1.05% for gasoline, 2.55% for diesel and 4.8% for jet when the economy shrinks 3%",
      abs(hits["gasoline"] + 0.0105) < 1e-9 and abs(hits["diesel"] + 0.0255) < 1e-9 and abs(hits["jet"] + 0.048) < 1e-9, ", ".join(f"{p} {v*100:.2f}%" for p, v in hits.items()))
rh = {k: hm.refinery_hit(k) for k in ("br", "rot", "sg")}
check("Runs fall 1.99% at Baton Rouge, 2.23% at Rotterdam and 2.62% at Singapore (Week 10 package: -1.992, -2.226, -2.622): jet-heavy plants fall furthest",
      abs(rh["br"] + 0.01992) < 1e-9 and abs(rh["rot"] + 0.02226) < 1e-9 and abs(rh["sg"] + 0.02622) < 1e-9 and rh["sg"] < rh["rot"] < rh["br"], ", ".join(f"{k} {v*100:.2f}%" for k, v in rh.items()))
m10 = q("2029Q2")
r10 = hm.step(copy.deepcopy(start), hm.Decisions(), m10)
check("In the recession quarter Baton Rouge runs 1.99% below what the team asked for, Singapore 2.62%", abs(r10[4]["br_throughput"] / (hm.C["br_capacity"] * 0.96) - (1 + rh["br"])) < 1e-9
      and abs(r10[4]["sg_accepted"] - 90) < 1e-9 and abs(hm.step(copy.deepcopy(start), hm.Decisions(), m9)[4]["br_throughput"] - hm.C["br_capacity"] * 0.96) < 1e-9, "throughput cut; the accepted request itself unchanged")

# 24 Delacroix's cover: the right Q4 2027 answer carries a cost in the recession
w4 = q("2027Q4")["wti"]
check("Marcus has cover after a crude price at cost or well below market (46.20), none after market or near-market (70)",
      hm.delacroix_has_cover(hm.Decisions(tp_method="cost"), w4) and hm.delacroix_has_cover(hm.Decisions(tp_method="other", tp_value=46.20), w4)
      and not hm.delacroix_has_cover(hm.Decisions(tp_method="market"), w4) and not hm.delacroix_has_cover(hm.Decisions(tp_method="other", tp_value=70.0), w4), "cost yes, 46.20 yes, market no, 70 no")
covered = hm.step(copy.deepcopy(start), hm.Decisions(br_run=90, delacroix_cover=True), m10)
uncovered = hm.step(copy.deepcopy(start), hm.Decisions(br_run=90), m10)
check("With cover, a cut from 96% to 90% is only half delivered (93%); without it, the cut lands; a cut outside the recession is never resisted",
      abs(covered[4]["br_run"] - 93) < 1e-9 and abs(uncovered[4]["br_run"] - 90) < 1e-9
      and abs(hm.step(copy.deepcopy(start), hm.Decisions(br_run=90, delacroix_cover=True), m9)[4]["br_run"] - 90) < 1e-9, f"{covered[4]['br_run']:.0f}% vs {uncovered[4]['br_run']:.0f}%")

# 25 Straits Pacific: a strained partner cuts Singapore to the minimum in the recession
ask = lambda *xs: [hm.Decisions(sg_request=x) for x in xs]
check("Asking Singapore for 100% in two of the last four quarters strains the partnership; once does not",
      hm.straits_is_strained(ask(100, 90, 100, 90)) and not hm.straits_is_strained(ask(100, 90, 90, 90)), "2 of 4 yes, 1 of 4 no")
strained = hm.step(copy.deepcopy(start), hm.Decisions(sg_request=95, straits_strained=True), m10)
check("A strained Straits Pacific runs Singapore at 80% in the recession whatever Halden asks; a cooperative one honours the request",
      strained[4]["sg_accepted"] == 80 and r10[4]["sg_accepted"] == 90 and hm.step(copy.deepcopy(start), hm.Decisions(sg_request=95, straits_strained=True), m9)[4]["sg_accepted"] == 95,
      "80 vs 90; no effect outside the recession")
check("Rotterdam sits just above its shutdown point in the recession: running still beats pausing, barely",
      hm.step(copy.deepcopy(start), hm.Decisions(rot_posture="run"), m10)[0]["rotterdam"] > hm.step(copy.deepcopy(start), hm.Decisions(rot_posture="idle"), m10)[0]["rotterdam"]
      and m10["nwe"] > hm.C["window1_floor"], f"margin {m10['nwe']:.2f} vs shutdown point {hm.C['window1_floor']:.2f}")

# 26 Reference teams: careful > average > careless in every quarter
summary = rr.main()
ok = True
detail = []
for i in range(len(summary["careful"])):
    c, a, l = (summary[t][i][2] for t in ("careful", "average", "careless"))
    ok &= c > a > l
    detail.append(f"Q{i+1}: {c:.1f} / {a:.1f} / {l:.1f}")
check("Score order careful > average > careless in all ten quarters", ok, "; ".join(detail))

# 27 Determinism: fixtures rebuild byte-identical
h1 = hashlib.sha256((ROOT / "fixtures/golden_quarters.csv").read_bytes()).hexdigest()
rr.main()
h2 = hashlib.sha256((ROOT / "fixtures/golden_quarters.csv").read_bytes()).hexdigest()
check("Fixtures rebuild identically", h1 == h2, h1[:16])

passed = sum(1 for c in checks if c[1])
lines = ["# Operating model validation (v0.6, Quarters 1-10)", "", f"**Result: {passed} of {len(checks)} checks pass.**", "",
         "| # | Check | Result | Detail |", "| --- | --- | --- | --- |"]
for i, (n, okk, d) in enumerate(checks, 1):
    lines.append(f"| {i} | {n} | {'PASS' if okk else 'FAIL'} | {d} |")
(ROOT / "VALIDATION.md").write_text("\n".join(lines) + "\n")
print("\n".join(lines))
sys.exit(0 if passed == len(checks) else 1)
