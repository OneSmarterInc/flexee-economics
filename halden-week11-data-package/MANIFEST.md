# Halden Energy — Week 11 Authoritative Package
## Kessana — the hold-up problem

Batch 16A status: **PASS** (see VALIDATION_16A.md).

Built to the Week 6 / Week 8 standard. Economics computed from the canonical CSVs and reconciled against the constants ledger before any file was written. All figures are provisional design-draft calibration.

## Contents

- `data/` — canonical CSVs, the single source of truth: `psc_terms.csv`, `reserves.csv`, `exit_and_sunk.csv`, `take_grid.csv`, `comparable_terms.csv`, `worked_example_field.csv`
- `halden_week11.xlsx` — student workbook (README, D_* data tabs, Worked Example, Your Analysis)
- `halden_week11_analysis.ipynb` — student notebook, same data, same tasks
- `faculty/halden_week11_FACULTY_SOLUTION.xlsx` — solved workbook; answers are live Excel formulas on the canonical data
- `faculty/halden_week11_FACULTY_SOLUTION.ipynb` — computes every answer and asserts it against the golden fixtures
- `fixtures/week11_golden.json` — golden fixtures: results, worked example, ordering assertions, tolerance
- `fixtures/provenance.json` — SHA-256 of every artifact, package and artifact versions
- `VALIDATION_16A.md` — gate result

## Expected outputs (headline)

- `profit_oil` = 67.0
- `annuity_factor` = 5.2161
- `pv_stay_current` = 4515.2783
- `pv_stay_demanded` = 3089.401
- `pv_stay_harsh` = 2376.4623
- `exit_value` = 180.0
- `indifference_take` = 0.9849

## What the fixtures pin

- Staying beats exiting at every take on the grid, including 80%
- Value of staying falls as the take rises
- Sunk capital does not enter the decision (result unchanged when sunk capital changes)
- On raw economics the government could push the take above 95% before Halden walks
- The 74% demand sits inside the range of comparable fiscal terms
- Ledger reconciliation: company margin 25.46 at 62%, 17.42 at 74%

## New week-specific parameters introduced by this package (add to ledger)

- comparable_terms.csv: six comparable fiscal regimes, government take 50%-85%.
- Production profile simplified to level annual output (34 M bbl/yr over 10 years).