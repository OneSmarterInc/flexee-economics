# Halden Energy — Week 7 Authoritative Package
## Competitive response — two market structures

Batch 16A status: **PASS** (see VALIDATION_16A.md).

Built to the Week 6 / Week 8 standard. Economics computed from the canonical CSVs and reconciled against the constants ledger before any file was written. All figures are provisional design-draft calibration.

## Contents

- `data/` — canonical CSVs, the single source of truth: `clusters.csv`, `rival_move.csv`, `capacity_payoffs.csv`, `window3_params.csv`, `cohort_states.csv`, `worked_example_markets.csv`
- `halden_week7.xlsx` — student workbook (README, D_* data tabs, Worked Example, Your Analysis)
- `halden_week7_analysis.ipynb` — student notebook, same data, same tasks
- `faculty/halden_week7_FACULTY_SOLUTION.xlsx` — solved workbook; answers are live Excel formulas on the canonical data
- `faculty/halden_week7_FACULTY_SOLUTION.ipynb` — computes every answer and asserts it against the golden fixtures
- `fixtures/week7_golden.json` — golden fixtures: results, worked example, ordering assertions, tolerance
- `fixtures/provenance.json` — SHA-256 of every artifact, package and artifact versions
- `VALIDATION_16A.md` — gate result

## Expected outputs (headline)

- `at_risk_pct_rural_low_comp` = -0.0375
- `match_cost_musd_rural_low_comp` = 75.0
- `ignore_cost_musd_rural_low_comp` = 0.068
- `ev_hold_musd` = -28.0
- `ev_match_musd` = -80.0
- `breakeven_build_probability` = 0.375
- `nonfuel_price_war` = 0.38
- `nonfuel_disciplined` = 0.45

## What the fixtures pin

- Core clusters (suburban, rural): ignoring the cut costs far less than matching it
- Capacity game: holding beats matching at the assessed build probability (0.70)
- Breakeven build probability is 0.375 — match only if the rival is likely bluffing
- Window 3 reproduces the ledger: 0.380 / 0.420 / 0.450
- Window 3 bounded within 15% of base
- A price war hurts more than discipline helps

## New week-specific parameters introduced by this package (add to ledger)

- clusters.csv annual volumes (M gal): urban 1,100; suburban 1,900; rural 1,250; interstate 750.
- rival_move.csv: gallons per fill 12; rival build probability 0.70 (analyst read of the rival balance sheet).
- capacity_payoffs.csv: Halden payoff matrix ($M/yr): hold/builds -40, hold/bluffs 0, match/builds -140, match/bluffs +60.

## Flags for review

- Under the ledger elasticities, matching the price cut is not justified in ANY cluster, including urban. The Week 7 spec implied an elastic cluster might justify a response. That only happens at station-level elasticities (around -20; see the worked example). Decide whether urban should carry a station-level elasticity, or keep the lesson as "never match in these markets."