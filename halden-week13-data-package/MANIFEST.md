# Halden Energy — Week 13 Authoritative Package
## Factor markets — three labor structures

Batch 16A status: **PASS** (see VALIDATION_16A.md).

Built to the Week 6 / Week 8 standard. Economics computed from the canonical CSVs and reconciled against the constants ledger before any file was written. All figures are provisional design-draft calibration.

## Contents

- `data/` — canonical CSVs, the single source of truth: `norway_union.csv`, `permian_labor.csv`, `turnaround.csv`, `wage_benchmarks.csv`, `worked_example_labor.csv`
- `halden_week13.xlsx` — student workbook (README, D_* data tabs, Worked Example, Your Analysis)
- `halden_week13_analysis.ipynb` — student notebook, same data, same tasks
- `faculty/halden_week13_FACULTY_SOLUTION.xlsx` — solved workbook; answers are live Excel formulas on the canonical data
- `faculty/halden_week13_FACULTY_SOLUTION.ipynb` — computes every answer and asserts it against the golden fixtures
- `fixtures/week13_golden.json` — golden fixtures: results, worked example, ordering assertions, tolerance
- `fixtures/provenance.json` — SHA-256 of every artifact, package and artifact versions
- `VALIDATION_16A.md` — gate result

## Expected outputs (headline)

- `norway_gross_cost_musd` = 33.6
- `norway_after_tax_cost_musd` = 7.392
- `permian_mrp_k` = 507.6
- `mrp_to_wage` = 3.5007
- `turnaround_peak_cost_musd` = 81.0
- `delay_expected_cost_musd` = 78.0
- `delay_saving_musd` = 3.0

## What the fixtures pin

- Tax shield: the Norwegian concession costs Halden 22% of its face value
- Permian marginal worker MRP covers the market wage (wage-taker, compete on retention)
- Contractor peak is 35% above base
- Turnaround timing is a genuine tradeoff: expected costs within 5% of each other
- Delaying carries an asset-health KPI penalty

## New week-specific parameters introduced by this package (add to ledger)

- Norwegian offshore wage bill $420M; Permian marginal worker 9,000 bbl/yr at a $145k loaded wage; turnaround labor base $60M, delayed-outage probability 12%, outage cost $150M, asset-health penalty 3 points. The spec named these inputs without values.