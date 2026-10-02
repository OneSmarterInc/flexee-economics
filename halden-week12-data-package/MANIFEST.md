# Halden Energy — Week 12 Authoritative Package
## What the company should become — transition portfolio

Batch 16A status: **PASS** (see VALIDATION_16A.md).

Built to the Week 6 / Week 8 standard. Economics computed from the canonical CSVs and reconciled against the constants ledger before any file was written. All figures are provisional design-draft calibration.

## Contents

- `data/` — canonical CSVs, the single source of truth: `envelope.csv`, `buckets.csv`, `projects.csv`, `carbon_scenarios.csv`, `demand_scenarios.csv`, `worked_example_projects.csv`
- `halden_week12.xlsx` — student workbook (README, D_* data tabs, Worked Example, Your Analysis)
- `halden_week12_analysis.ipynb` — student notebook, same data, same tasks
- `faculty/halden_week12_FACULTY_SOLUTION.xlsx` — solved workbook; answers are live Excel formulas on the canonical data
- `faculty/halden_week12_FACULTY_SOLUTION.ipynb` — computes every answer and asserts it against the golden fixtures
- `fixtures/week12_golden.json` — golden fixtures: results, worked example, ordering assertions, tolerance
- `fixtures/provenance.json` — SHA-256 of every artifact, package and artifact versions
- `VALIDATION_16A.md` — gate result

## Expected outputs (headline)

- `discretionary` = 1200.0
- `helix_rotterdam_cost` = 1200.0
- `adjacent_ceiling` = 1200.0
- `envelope_with_divest` = 1750.0
- `hr_plus_wind_cost` = 1750.0
- `feasible_portfolios` = 17
- `feasible_with_helix_rotterdam` = 3
- `portfolios_unlocked_by_divest` = 2

## What the fixtures pin

- Every project swings sign across the scenario matrix (no dominant portfolio)
- Sustaining floor leaves $1,200M discretionary
- Helix Rotterdam can be funded within the adjacent-transition bucket ceiling
- Helix Rotterdam alone fills the discretionary envelope
- The divestment unlocks portfolios that are otherwise unaffordable
- INTERLOCK: Helix Rotterdam + offshore wind is affordable only with the divestment

## New week-specific parameters introduced by this package (add to ledger)

- Project scenario NPV model: NPV = base + carbon sensitivity × (carbon − 40) + demand sensitivity × demand code. The spec described the sign swings without a model.

## Flags for review

- Design decision applied (Option 1): adjacent-transition ceiling raised from $900M to $1,200M; divestment proceeds raised from $320M to $550M. Week 12 spec, 7-week Week 12 spec, and constants ledger updated to match.