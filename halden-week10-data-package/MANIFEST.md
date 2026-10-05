# Halden Energy — Week 10 Authoritative Package
## The cycle turns — where the consequences converge

Batch 16A status: **PASS** (see VALIDATION_16A.md).

Built to the Week 6 / Week 8 standard. Economics computed from the canonical CSVs and reconciled against the constants ledger before any file was written. All figures are provisional design-draft calibration.

## Contents

- `data/` — canonical CSVs, the single source of truth: `product_elasticities.csv`, `refinery_yields.csv`, `recession_params.csv`, `binding_rules.csv`, `team_prior_state.csv`
- `halden_week10.xlsx` — student workbook (README, D_* data tabs, Worked Example, Your Analysis)
- `halden_week10_analysis.ipynb` — student notebook, same data, same tasks
- `faculty/halden_week10_FACULTY_SOLUTION.xlsx` — solved workbook; answers are live Excel formulas on the canonical data
- `faculty/halden_week10_FACULTY_SOLUTION.ipynb` — computes every answer and asserts it against the golden fixtures
- `fixtures/week10_golden.json` — golden fixtures: results, worked example, ordering assertions, tolerance
- `fixtures/provenance.json` — SHA-256 of every artifact, package and artifact versions
- `VALIDATION_16A.md` — gate result

## Expected outputs (headline)

- `demand_hit_gasoline` = -0.0105
- `demand_hit_diesel` = -0.0255
- `demand_hit_jet` = -0.048
- `refinery_hit_br` = -0.0199
- `refinery_hit_rot` = -0.0223
- `refinery_hit_sing` = -0.0262
- `disciplined_binding_count` = 0
- `constrained_binding_count` = 5

## What the fixtures pin

- Recession is uneven: jet falls hardest, gasoline least
- Ledger reconciliation: refinery hits Baton Rouge -1.99%, Rotterdam -2.23%, Singapore -2.62%
- Jet-heavy Singapore is hit hardest; gasoline-heavy Baton Rouge least
- Disciplined reference team: no constraint binds
- Constrained reference team: all five constraints bind (inherited, not new)

## Changelog

- 1.0.1: corrected the reference-team origin notes to match the Week 4 numbers. Above-target refining (from a marginal-cost or lazy transfer price) is what gives Delacroix cover. No numeric value or golden fixture changed.

## New week-specific parameters introduced by this package (add to ledger)

- binding_rules.csv: a constraint binds when cancellable capex < $200M, crude hedge coverage < 50%, the Week 4 transfer price left Baton Rouge reporting strong (Delacroix has cover), Straits Pacific standing is strained or hostile, or the cash cushion < $150M. The spec described the five threads without thresholds.
- team_prior_state.csv: two reference teams for regression. At runtime the platform supplies each team's own state from its history; these rows are fixtures, not student data.

## Flags for review

- Replaces the earlier Week 10 reference build, which predated the 16A standard (no faculty notebook, golden fixtures, or provenance).
- Seven-week variant: Week 10 also folds in the Norwegian union negotiation. Its data and fixtures live in the Week 13 package.