# Halden Energy — Week 2 Authoritative Package
## Demand analysis and rack pricing

Batch 16A status: **PASS** (see VALIDATION_16A.md).

Built to the Week 6 / Week 8 standard. Economics computed from the canonical CSVs and reconciled against the constants ledger before any file was written. All figures are provisional design-draft calibration.

## Contents

- `data/` — canonical CSVs, the single source of truth: `cluster_params.csv`, `cluster_price_volume.csv`, `pricing_params.csv`, `fuel_nonfuel.csv`, `worked_example_cluster.csv`, `worked_example_params.csv`
- `halden_week2.xlsx` — student workbook (README, D_* data tabs, Worked Example, Your Analysis)
- `halden_week2_analysis.ipynb` — student notebook, same data, same tasks
- `faculty/halden_week2_FACULTY_SOLUTION.xlsx` — solved workbook; answers are live Excel formulas on the canonical data
- `faculty/halden_week2_FACULTY_SOLUTION.ipynb` — computes every answer and asserts it against the golden fixtures
- `fixtures/week2_golden.json` — golden fixtures: results, worked example, ordering assertions, tolerance
- `fixtures/provenance.json` — SHA-256 of every artifact, package and artifact versions
- `VALIDATION_16A.md` — gate result

## Expected outputs (headline)

- `est_urban_high_comp` = -0.1094
- `est_suburban_mid_comp` = -0.0605
- `est_rural_low_comp` = -0.0195
- `est_interstate` = -0.0494
- `est_netherlands_urban` = -0.0678
- `est_belgium_mixed` = -0.0499
- `est_germany_border` = -0.1289
- `cordell_weighted_est` = -0.0594
- `europe_weighted_est` = -0.0838
- `cordell_weighted_passthrough` = 0.6035

## What the fixtures pin

- Every cluster estimate lands within 0.01 of its design elasticity
- Cordell volume-weighted estimate reconciles with the ledger (about -0.060)
- European volume-weighted estimate reconciles with the ledger (about -0.085)
- Cordell weighted pass-through is 0.603 (reconciles with Week 8 constant 0.60)
- Elasticity spans rural near-zero to German border strongly negative
- Urban volume response to a rack cut is at least 5x rural (elasticity x transmission)

## New week-specific parameters introduced by this package (add to ledger)

- cluster_price_volume.csv: 156-week synthetic price/volume series per cluster (seed 42), generated from the ledger design elasticities. Stored as data, not generated at runtime.