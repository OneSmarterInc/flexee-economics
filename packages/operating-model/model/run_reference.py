"""Builds the golden fixtures: three reference teams through Quarters 1-4 (Q1-Q4 2027)."""
import csv
import copy
import json
from pathlib import Path

import halden_model as hm

ROOT = Path(__file__).resolve().parent.parent
FIX = ROOT / "fixtures"
FIX.mkdir(exist_ok=True)

BASE = {c["key"]: c["base"] for c in hm.CORDELL + hm.EUROPE}


def offsets(**kw):
    o = dict(BASE)
    o.update(kw)
    return o


# Decisions per team per round. Round n is quarter 2027Qn. Unlocks follow data/levers.csv;
# a lever not yet unlocked keeps its history value.
TEAMS = {
    "careful": [
        dict(rigs=11, br_run=96, rot_run=90),
        dict(rigs=11, br_run=96, rot_run=92, offsets=offsets(suburban=4.5, rural=7.0, interstate=3.5, nl=0.5, be=1.5, de=-1.5)),
        dict(rigs=11, br_run=96, rot_run=92, sg_request=95, offsets=offsets(suburban=4.5, rural=7.0, interstate=3.5, nl=0.5, be=1.5, de=-1.5)),
        dict(rigs=11, br_run=96, rot_run=92, sg_request=95, offsets=offsets(suburban=4.5, rural=7.0, interstate=3.5, nl=0.5, be=1.5, de=-1.5), tp_method="cost"),
    ],
    "average": [
        dict(),
        dict(),
        dict(),
        dict(),
    ],
    "careless": [
        dict(rigs=26, norway="cut", br_run=99, rot_run=70),
        dict(rigs=26, norway="cut", br_run=99, rot_run=70, offsets=offsets(urban=-1.0, suburban=0.5, rural=2.0, interstate=0.0, nl=-3.0, be=-2.0, de=-4.0)),
        dict(rigs=26, norway="cut", br_run=99, rot_posture="idle", sg_request=100, offsets=offsets(urban=-1.0, suburban=0.5, rural=2.0, interstate=0.0, nl=-3.0, be=-2.0, de=-4.0)),
        dict(rigs=26, norway="cut", br_run=99, rot_posture="idle", sg_request=100, offsets=offsets(urban=-1.0, suburban=0.5, rural=2.0, interstate=0.0, nl=-3.0, be=-2.0, de=-4.0), tp_method="other", tp_value=46.20),
    ],
}


def main():
    gas_other = hm.calibrate_gas_other(3750.0)
    start_state, hist = hm.run_history()
    market = [m for m in hm.load_market() if not m["is_history"]]

    rows, decisions_rows, state_rows, summary = [], [], [], {}
    states = {t: copy.deepcopy(start_state) for t in TEAMS}

    # history rows (same for every team; recorded once under team=history)
    for m, (lines, seg, money, kpi, ops, notes, st) in hist:
        for k, v in {**{f"line.{a}": b for a, b in lines.items()}, **{f"segment.{a}": b for a, b in seg.items()},
                     **{f"money.{a}": b for a, b in money.items()}, **{f"kpi.{a}": b for a, b in kpi.items()}}.items():
            rows.append(("history", m["quarter"], k, v))

    for rnd, m in enumerate(market, start=1):
        kpis, outs = {}, {}
        for team, plan in TEAMS.items():
            d = hm.Decisions(**plan[rnd - 1])
            out = hm.step(states[team], d, m)
            outs[team] = (d, out)
            kpis[team] = out[3]
        scores = hm.composite(kpis)
        ranks = hm.rank(scores)
        for team, (d, (lines, seg, money, kpi, ops, notes, st)) in outs.items():
            q = m["quarter"]
            for k, v in lines.items():
                rows.append((team, q, f"line.{k}", v))
            for k, v in seg.items():
                rows.append((team, q, f"segment.{k}", v))
            for k, v in money.items():
                rows.append((team, q, f"money.{k}", v))
            for k, v in kpi.items():
                rows.append((team, q, f"kpi.{k}", v))
            for k in ("tp", "market_tp", "cost_tp", "permian_prod", "br_throughput", "rot_throughput", "sg_accepted"):
                rows.append((team, q, f"ops.{k}", ops[k]))
            rows.append((team, q, "score.composite", scores[team]))
            rows.append((team, q, "score.rank", ranks[team]))
            dd = {"team": team, "round": rnd, "quarter": q, "rigs": d.rigs, "norway": d.norway, "br_run": d.br_run,
                  "rot_run": d.rot_run, "rot_posture": d.rot_posture, "sg_request": d.sg_request,
                  "tp_method": d.tp_method, "tp_value": "" if d.tp_value is None else d.tp_value,
                  "advisor_answers": d.advisor_answers}
            dd.update({f"off_{k}": v for k, v in d.offsets.items()})
            decisions_rows.append(dd)
            state_rows.append({"team": team, "quarter_end": q, "permian_prod_next": st.permian_prod, "rot_status": st.rot_status,
                               "capital_employed": st.capital_employed, "net_debt": st.net_debt, "asset_health": st.asset_health,
                               "europe_volume_factor": st.europe_volume_factor,
                               "held_up": json.dumps(st.held_up, sort_keys=True), "held_down": json.dumps(st.held_down, sort_keys=True)})
            states[team] = st
            summary.setdefault(team, []).append((q, money["ebitda"], scores[team], ranks[team], seg, kpi))

    with open(FIX / "golden_quarters.csv", "w", newline="") as f:
        w = csv.writer(f, lineterminator="\n")
        w.writerow(["team", "quarter", "metric", "value"])
        for r in rows:
            w.writerow([r[0], r[1], r[2], f"{r[3]:.6f}"])
    with open(FIX / "reference_decisions.csv", "w", newline="") as f:
        w = csv.DictWriter(f, fieldnames=list(decisions_rows[0].keys()), lineterminator="\n")
        w.writeheader()
        w.writerows(decisions_rows)
    with open(FIX / "state_after_quarter.csv", "w", newline="") as f:
        w = csv.DictWriter(f, fieldnames=list(state_rows[0].keys()), lineterminator="\n")
        w.writeheader()
        w.writerows(state_rows)
    with open(FIX / "calibration.json", "w") as f:
        json.dump({"gas_other_ebitda": gas_other,
                   "start_2027": {"permian_prod": start_state.permian_prod, "capital_employed": start_state.capital_employed,
                                  "net_debt": start_state.net_debt, "asset_health": start_state.asset_health}}, f, indent=2)

    print(f"gas_other calibrated to {gas_other:.2f}")
    for m, out in hist:
        print("history", m["quarter"], f"EBITDA {out[2]['ebitda']:.1f}", "rotterdam", f"{out[0]['rotterdam']:.1f}")
    for team, qs in summary.items():
        for q, e, s, r, seg, kpi in qs:
            print(f"{team:9s} {q} EBITDA {e:8.1f} score {s:5.1f} rank {r}  " + " ".join(f"{k}={v:.2f}" for k, v in kpi.items()))
    return summary


if __name__ == "__main__":
    main()
