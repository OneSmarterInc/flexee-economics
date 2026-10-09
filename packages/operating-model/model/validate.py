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

# 11 Reference teams: careful > average > careless in every quarter
summary = rr.main()
ok = True
detail = []
for i in range(4):
    c, a, l = (summary[t][i][2] for t in ("careful", "average", "careless"))
    ok &= c > a > l
    detail.append(f"Q{i+1}: {c:.1f} / {a:.1f} / {l:.1f}")
check("Score order careful > average > careless in all four quarters", ok, "; ".join(detail))

# 12 Determinism: fixtures rebuild byte-identical
h1 = hashlib.sha256((ROOT / "fixtures/golden_quarters.csv").read_bytes()).hexdigest()
rr.main()
h2 = hashlib.sha256((ROOT / "fixtures/golden_quarters.csv").read_bytes()).hexdigest()
check("Fixtures rebuild identically", h1 == h2, h1[:16])

passed = sum(1 for c in checks if c[1])
lines = ["# Operating model validation (v0.1, Quarters 1-4)", "", f"**Result: {passed} of {len(checks)} checks pass.**", "",
         "| # | Check | Result | Detail |", "| --- | --- | --- | --- |"]
for i, (n, okk, d) in enumerate(checks, 1):
    lines.append(f"| {i} | {n} | {'PASS' if okk else 'FAIL'} | {d} |")
(ROOT / "VALIDATION.md").write_text("\n".join(lines) + "\n")
print("\n".join(lines))
sys.exit(0 if passed == len(checks) else 1)
