# Halden Energy — Week 1 Authoritative Package
## Inheriting the seat

Batch 16A status: **PASS** (see VALIDATION_16A.md).

Built to the Week 6 / Week 8 standard. Economics computed from the canonical CSVs and reconciled against the constants ledger before any file was written. All figures are provisional design-draft calibration.

## Contents

- `data/` — canonical CSVs, the single source of truth: `benchmarks.csv`, `upstream_assets.csv`, `refining_assets.csv`, `rotterdam_cost_split.csv`, `book_values.csv`, `product_yields.csv`, `worked_example_assets.csv`
- `halden_week1.xlsx` — student workbook (README, D_* data tabs, Worked Example, Your Analysis)
- `halden_week1_analysis.ipynb` — student notebook, same data, same tasks
- `faculty/halden_week1_FACULTY_SOLUTION.xlsx` — solved workbook; answers are live Excel formulas on the canonical data
- `faculty/halden_week1_FACULTY_SOLUTION.ipynb` — computes every answer and asserts it against the golden fixtures
- `fixtures/week1_golden.json` — golden fixtures: results, worked example, ordering assertions, tolerance
- `fixtures/provenance.json` — SHA-256 of every artifact, package and artifact versions
- `VALIDATION_16A.md` — gate result

## Expected outputs (headline)

- `permian_margin` = 56.4
- `norway_posttax` = 10.34
- `kessana_company` = 25.46
- `br_net` = 20.35
- `rot_contribution` = 2.0
- `rot_net` = -0.3
- `rot_shutdown_crack` = 2.6
- `economic_rank` = Permian > Kessana > Baton Rouge > Norwegian > Singapore > Rotterdam
- `reported_rank` = Permian > Baton Rouge > Kessana > Norwegian > Singapore > Rotterdam

## What the fixtures pin

- Rotterdam loses money net but covers its variable cost (keep running short-run)
- Rotterdam current crack sits above its shutdown crack
- Norwegian post-tax margin is 22% of pre-tax (78% tax)
- Tax shield: a $2 pre-tax Norwegian loss costs Halden only $0.44
- Reported-profit ranking differs from economic ranking
- Ledger reconciliation: Permian 56.40, Norway 10.34, Kessana 25.46, Baton Rouge 20.35, Rotterdam -0.30

## New week-specific parameters introduced by this package (add to ledger)

- book_values.csv (book value, replacement cost, 3-yr reported profit per asset). The Week 1 spec named this dataset without values; these are introduced here.