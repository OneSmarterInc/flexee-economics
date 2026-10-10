"""Builds the golden fixtures: three reference teams through Quarters 1-12 (Q1 2027 to Q4 2029)."""
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


# Decisions per team per round (round 1 = Q1 2027). Unlocks follow data/levers.csv;
# a lever not yet unlocked keeps its history value.
TEAMS = {
    "careful": [
        dict(rigs=11, br_run=96, rot_run=90),
        dict(rigs=11, br_run=96, rot_run=92, offsets=offsets(suburban=4.5, rural=7.0, interstate=3.5, nl=0.5, be=1.5, de=-1.5)),
        dict(rigs=11, br_run=96, rot_run=92, sg_request=95, offsets=offsets(suburban=4.5, rural=7.0, interstate=3.5, nl=0.5, be=1.5, de=-1.5)),
        dict(rigs=11, br_run=96, rot_run=92, sg_request=95, offsets=offsets(suburban=4.5, rural=7.0, interstate=3.5, nl=0.5, be=1.5, de=-1.5), tp_method="cost"),
        dict(rigs=11, br_run=96, rot_run=92, sg_request=95, offsets=offsets(suburban=4.5, rural=7.0, interstate=3.5, nl=0.5, be=1.5, de=-1.5), tp_method="cost",
             crude_hedge_pct=25, eur_hedge=100, nok_hedge=150),
        dict(rigs=11, br_run=96, rot_run=92, sg_request=95, offsets=offsets(suburban=4.5, rural=7.0, interstate=3.5, nl=0.5, be=1.5, de=-1.5), tp_method="cost",
             crude_hedge_pct=25, eur_hedge=100, nok_hedge=150, projects={"br_upgrade": "commit", "rot_upgrade": "commit", "helix": "hold"}),
        dict(rigs=11, br_run=96, rot_run=92, sg_request=95, offsets=offsets(suburban=4.5, rural=7.0, interstate=3.5, nl=0.5, be=1.5, de=-1.5), tp_method="cost",
             crude_hedge_pct=25, eur_hedge=100, nok_hedge=150, projects={"br_upgrade": "commit", "rot_upgrade": "commit", "helix": "hold"},
             responses={}, capacity_response="hold"),
        dict(rigs=11, br_run=96, rot_run=92, sg_request=95, offsets=offsets(suburban=4.5, rural=7.0, interstate=3.5, nl=0.5, be=1.5, de=-1.5), tp_method="cost",
             crude_hedge_pct=25, eur_hedge=100, nok_hedge=150, projects={"br_upgrade": "commit", "rot_upgrade": "commit", "helix": "hold"},
             responses={}, capacity_response="hold", opec_case="partial"),
        dict(rigs=11, br_run=96, rot_run=92, sg_request=95, offsets=offsets(suburban=4.5, rural=7.0, interstate=3.5, nl=0.5, be=1.5, de=-1.5), tp_method="cost",
             crude_hedge_pct=25, eur_hedge=100, nok_hedge=150, projects={"br_upgrade": "commit", "rot_upgrade": "commit", "helix": "hold"},
             responses={}, capacity_response="hold", opec_case="partial", rebrand={"core": "keep", "gulf": "keep", "edge": "rebrand"}),
        dict(rigs=9, br_run=90, rot_run=85, sg_request=85, offsets=offsets(suburban=4.5, rural=7.0, interstate=3.5, nl=0.5, be=1.5, de=-1.5), tp_method="cost",
             crude_hedge_pct=25, eur_hedge=100, nok_hedge=150, projects={"br_upgrade": "commit", "rot_upgrade": "commit", "helix": "hold"},
             responses={}, capacity_response="hold", opec_case="partial", rebrand={"core": "keep", "gulf": "keep", "edge": "rebrand"}),
        dict(rigs=10, br_run=93, rot_run=88, sg_request=88, offsets=offsets(suburban=4.5, rural=7.0, interstate=3.5, nl=0.5, be=1.5, de=-1.5), tp_method="cost",
             crude_hedge_pct=25, eur_hedge=100, nok_hedge=150, projects={"br_upgrade": "commit", "rot_upgrade": "commit", "helix": "hold"},
             responses={}, capacity_response="hold", opec_case="partial", rebrand={"core": "keep", "gulf": "keep", "edge": "rebrand"},
             kessana_position="counter"),
        dict(rigs=10, br_run=93, rot_run=88, sg_request=88, offsets=offsets(suburban=4.5, rural=7.0, interstate=3.5, nl=0.5, be=1.5, de=-1.5), tp_method="cost",
             crude_hedge_pct=25, eur_hedge=100, nok_hedge=150, projects={"br_upgrade": "commit", "rot_upgrade": "commit", "helix": "hold"},
             responses={}, capacity_response="hold", opec_case="partial", rebrand={"core": "keep", "gulf": "keep", "edge": "rebrand"},
             kessana_position="counter", portfolio={"biofuel_conversion": "go", "offshore_wind": "go"}),
    ],
    "average": [
        dict(),
        dict(),
        dict(),
        dict(),
        dict(),
        dict(),
        dict(),
        dict(),
        dict(),
        dict(),
        dict(kessana_position="accept"),
        dict(kessana_position="accept", portfolio={"permian_expansion": "go"}),
    ],
    "careless": [
        dict(rigs=26, norway="cut", br_run=99, rot_run=70),
        dict(rigs=26, norway="cut", br_run=99, rot_run=70, offsets=offsets(urban=-1.0, suburban=0.5, rural=2.0, interstate=0.0, nl=-3.0, be=-2.0, de=-4.0)),
        dict(rigs=26, norway="cut", br_run=99, rot_posture="idle", sg_request=100, offsets=offsets(urban=-1.0, suburban=0.5, rural=2.0, interstate=0.0, nl=-3.0, be=-2.0, de=-4.0)),
        dict(rigs=26, norway="cut", br_run=99, rot_posture="idle", sg_request=100, offsets=offsets(urban=-1.0, suburban=0.5, rural=2.0, interstate=0.0, nl=-3.0, be=-2.0, de=-4.0), tp_method="other", tp_value=46.20),
        dict(rigs=26, norway="cut", br_run=99, rot_posture="idle", sg_request=100, offsets=offsets(urban=-1.0, suburban=0.5, rural=2.0, interstate=0.0, nl=-3.0, be=-2.0, de=-4.0), tp_method="other", tp_value=46.20,
             crude_hedge_pct=50, eur_hedge=600),
        dict(rigs=26, norway="cut", br_run=99, rot_posture="idle", sg_request=100, offsets=offsets(urban=-1.0, suburban=0.5, rural=2.0, interstate=0.0, nl=-3.0, be=-2.0, de=-4.0), tp_method="other", tp_value=46.20,
             crude_hedge_pct=50, eur_hedge=600, projects={"helix": "commit"}),
        dict(rigs=26, norway="cut", br_run=99, rot_posture="idle", sg_request=100, offsets=offsets(urban=-1.0, suburban=0.5, rural=2.0, interstate=0.0, nl=-3.0, be=-2.0, de=-4.0), tp_method="other", tp_value=46.20,
             crude_hedge_pct=50, eur_hedge=600, projects={"helix": "commit"},
             responses={"urban": "match", "suburban": "match", "rural": "match", "interstate": "match"}, capacity_response="match"),
        dict(rigs=26, norway="cut", br_run=99, rot_posture="idle", sg_request=100, offsets=offsets(urban=-1.0, suburban=0.5, rural=2.0, interstate=0.0, nl=-3.0, be=-2.0, de=-4.0), tp_method="other", tp_value=46.20,
             crude_hedge_pct=50, eur_hedge=600, projects={"helix": "commit"},
             responses={"urban": "match", "suburban": "match", "rural": "match", "interstate": "match"}, capacity_response="match", opec_case="full"),
        dict(rigs=26, norway="cut", br_run=99, rot_posture="idle", sg_request=100, offsets=offsets(urban=-1.0, suburban=0.5, rural=2.0, interstate=0.0, nl=-3.0, be=-2.0, de=-4.0), tp_method="other", tp_value=46.20,
             crude_hedge_pct=50, eur_hedge=600, projects={"helix": "commit"},
             responses={"urban": "match", "suburban": "match", "rural": "match", "interstate": "match"}, capacity_response="match", opec_case="full",
             rebrand={"core": "rebrand", "gulf": "rebrand", "edge": "rebrand"}),
        dict(rigs=26, norway="cut", br_run=99, rot_posture="idle", sg_request=100, offsets=offsets(urban=-1.0, suburban=0.5, rural=2.0, interstate=0.0, nl=-3.0, be=-2.0, de=-4.0), tp_method="other", tp_value=46.20,
             crude_hedge_pct=50, eur_hedge=600, projects={"helix": "commit"},
             responses={"urban": "match", "suburban": "match", "rural": "match", "interstate": "match"}, capacity_response="match", opec_case="full",
             rebrand={"core": "rebrand", "gulf": "rebrand", "edge": "rebrand"}),
        dict(rigs=26, norway="cut", br_run=99, rot_posture="idle", sg_request=100, offsets=offsets(urban=-1.0, suburban=0.5, rural=2.0, interstate=0.0, nl=-3.0, be=-2.0, de=-4.0), tp_method="other", tp_value=46.20,
             crude_hedge_pct=50, eur_hedge=600, projects={"helix": "commit"},
             responses={"urban": "match", "suburban": "match", "rural": "match", "interstate": "match"}, capacity_response="match", opec_case="full",
             rebrand={"core": "rebrand", "gulf": "rebrand", "edge": "rebrand"}, kessana_position="threaten"),
        dict(rigs=26, norway="cut", br_run=99, rot_posture="idle", sg_request=100, offsets=offsets(urban=-1.0, suburban=0.5, rural=2.0, interstate=0.0, nl=-3.0, be=-2.0, de=-4.0), tp_method="other", tp_value=46.20,
             crude_hedge_pct=50, eur_hedge=600, projects={"helix": "commit"},
             responses={"urban": "match", "suburban": "match", "rural": "match", "interstate": "match"}, capacity_response="match", opec_case="full",
             rebrand={"core": "rebrand", "gulf": "rebrand", "edge": "rebrand"}, kessana_position="threaten",
             portfolio={"helix_rotterdam": "go", "offshore_wind": "go", "euro_retail_divest": "go"}),
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

    plans = {t: [hm.Decisions(**p) for p in TEAMS[t]] for t in TEAMS}
    # What each team carries from its own history into the recession: whether Marcus has cover to resist a Baton
    # Rouge run cut (the Q4 2027 crude price) and whether Straits Pacific is strained (the last four Singapore asks).
    q4_wti = next(x["wti"] for x in market if x["quarter"] == "2027Q4")
    for t in TEAMS:
        for i, d in enumerate(plans[t]):
            d.delacroix_cover = i >= 4 and hm.delacroix_has_cover(plans[t][3], q4_wti)
            d.straits_strained = hm.straits_is_strained(plans[t][max(0, i - 4):i])
    class_effects = {}
    for rnd, m in enumerate(market, start=1):
        m = dict(m)
        if m["quarter"] == "2028Q1":   # Window 1 lands: Q3 2027 European run rates set this margin
            avg_util = sum(hm.european_util(plans[t][2]) for t in TEAMS) / len(TEAMS)
            m["nwe"] = hm.window1_nwe(avg_util)
            class_effects["window1_avg_util"] = avg_util
            class_effects["window1_nwe"] = m["nwe"]
        if m["quarter"] == "2028Q2":   # capital terms from Q4 2027 crude-price discipline
            q4_wti = next(x["wti"] for x in market if x["quarter"] == "2027Q4")
            avg_d = sum(hm.crude_price_discipline(plans[t][3], q4_wti) for t in TEAMS) / len(TEAMS)
            terms = hm.capital_terms(avg_d)
            class_effects.update({"capital_discipline": avg_d, "capital_behaviour": terms["behaviour"],
                                  "capital_rate": terms["rate"], "capital_envelope": terms["envelope"]})
            for t in TEAMS:
                outlay = sum(hm.PROJECTS[k]["outlay"] for k, v in plans[t][rnd - 1].projects.items() if v == "commit")
                assert outlay <= terms["envelope"], f"{t} commits {outlay} over the envelope {terms['envelope']}"
        if m["quarter"] == "2028Q3":   # Window 3 opens: this quarter's price aggression lands in Q1 2029
            avg_agg = sum(hm.price_aggression(plans[t][rnd - 1]) for t in TEAMS) / len(TEAMS)
            class_effects["window3_avg_aggression"] = avg_agg
            class_effects["window3_nonfuel"] = hm.window3_nonfuel(avg_agg)
        if m["quarter"] == "2028Q4":   # OPEC+ decides (the fixtures use the likeliest outcome); Window 2 lands
            br_share = sum(1 for t in TEAMS if plans[t][5].projects.get("br_upgrade") == "commit") / len(TEAMS)
            class_effects["window2_br_share"] = br_share
            class_effects["window2_shift"] = hm.window2_shift(br_share)
            class_effects["opec_outcome"] = "partial"
            m = hm.opec_market(m, "partial", br_share)
            class_effects["opec_wti"] = m["wti"]
            class_effects["opec_gc"] = m["gc"]
        if m["quarter"] == "2029Q4":   # the portfolio must be fundable
            for t in TEAMS:
                chosen = [k for k, v in plans[t][rnd - 1].portfolio.items() if v == "go"]
                assert hm.portfolio_check(chosen, states[t].rot_status == "closed") == [], f"{t} portfolio {chosen} can't be funded"
        if m["quarter"].startswith("2029"):   # Window 3 lands for all of 2029
            m["cordell_nonfuel"] = class_effects["window3_nonfuel"]
        kpis, outs = {}, {}
        for team in TEAMS:
            d = plans[team][rnd - 1]
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
            for k in ("tp", "market_tp", "cost_tp", "permian_prod", "br_throughput", "rot_throughput", "sg_accepted", "fx_effect", "project_outlay", "nwe",
                      "rival_match_cost", "rival_ignore_cost", "wti_shock", "gc", "wti", "rebrand_outlay", "nonfuel_per_gal", "br_run",
                      "kessana_take", "kessana_exit_proceeds", "kessana_forgone", "portfolio_capex", "divest_proceeds", "carbon"):
                rows.append((team, q, f"ops.{k}", ops[k]))
            rows.append((team, q, "score.composite", scores[team]))
            rows.append((team, q, "score.rank", ranks[team]))
            dd = {"team": team, "round": rnd, "quarter": q, "rigs": d.rigs, "norway": d.norway, "br_run": d.br_run,
                  "rot_run": d.rot_run, "rot_posture": d.rot_posture, "sg_request": d.sg_request,
                  "tp_method": d.tp_method, "tp_value": "" if d.tp_value is None else d.tp_value,
                  "advisor_answers": d.advisor_answers, "crude_hedge_pct": d.crude_hedge_pct, "eur_hedge": d.eur_hedge,
                  "nok_hedge": d.nok_hedge, **{f"proj_{k}": d.projects.get(k, "hold") for k in hm.PROJECTS},
                  **{f"resp_{c['key']}": d.responses.get(c["key"], "ignore") for c in hm.CORDELL}, "capacity_response": d.capacity_response,
                  "opec_case": d.opec_case, **{f"rebrand_{k}": d.rebrand.get(k, "keep") for k in hm.REBRAND},
                  "delacroix_cover": int(d.delacroix_cover), "straits_strained": int(d.straits_strained),
                  "kessana_position": d.kessana_position, **{f"port_{k}": d.portfolio.get(k, "hold") for k in hm.PORTFOLIO}}
            dd.update({f"off_{k}": v for k, v in d.offsets.items()})
            decisions_rows.append(dd)
            state_rows.append({"team": team, "quarter_end": q, "permian_prod_next": st.permian_prod, "rot_status": st.rot_status,
                               "capital_employed": st.capital_employed, "net_debt": st.net_debt, "asset_health": st.asset_health,
                               "europe_volume_factor": st.europe_volume_factor,
                               "held_up": json.dumps(st.held_up, sort_keys=True), "held_down": json.dumps(st.held_down, sort_keys=True),
                               "hedges": json.dumps(st.hedges, sort_keys=True), "projects": json.dumps(st.projects, sort_keys=True),
                               "rebranded": json.dumps(st.rebranded, sort_keys=True), "prev_br_run": st.prev_br_run,
                               "kessana_take": st.kessana_take, "kessana_exited": int(st.kessana_exited),
                               "portfolio": json.dumps(st.portfolio, sort_keys=True), "europe_sold": int(st.europe_sold)})
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
    with open(FIX / "class_effects.json", "w") as f:
        json.dump(class_effects, f, indent=2, sort_keys=True)
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
