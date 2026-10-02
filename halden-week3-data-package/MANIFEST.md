# Halden Energy — Week 3 Authoritative Package
## Run rates and the shutdown point

Batch 16A status: **PASS** (see VALIDATION_16A.md).

Built to the Week 6 / Week 8 standard. Economics computed from the canonical CSVs and reconciled against the constants ledger before any file was written. All figures are provisional design-draft calibration.

## Contents

- `data/` — canonical CSVs, the single source of truth: `refinery_costs.csv`, `rotterdam_restart.csv`, `window1_params.csv`, `cohort_states.csv`, `crack_history.csv`, `jv_governance.csv`, `worked_example_plant.csv`
- `halden_week3.xlsx` — student workbook (README, D_* data tabs, Worked Example, Your Analysis)
- `halden_week3_analysis.ipynb` — student notebook, same data, same tasks
- `faculty/halden_week3_FACULTY_SOLUTION.xlsx` — solved workbook; answers are live Excel formulas on the canonical data
- `faculty/halden_week3_FACULTY_SOLUTION.ipynb` — computes every answer and asserts it against the golden fixtures
- `fixtures/week3_golden.json` — golden fixtures: results, worked example, ordering assertions, tolerance
- `fixtures/provenance.json` — SHA-256 of every artifact, package and artifact versions
- `VALIDATION_16A.md` — gate result

## Expected outputs (headline)

- `rot_contribution` = 2.0
- `rot_net` = -0.3
- `rot_shutdown_crack` = 2.6
- `rot_idle_delta` = -1.1
- `crack_runs_hard` = 3.25
- `crack_base` = 4.6
- `crack_disciplines` = 5.95
- `rot_net_disciplines` = 1.05

## What the fixtures pin

- Rotterdam covers variable cost (+2.00) but loses money net (-0.30)
- Idling Rotterdam is worse than running it short-run (idle delta < 0)
- Baton Rouge and Singapore nets reconcile with Week 1 (20.35 and 8.60)
- Window 1 is bounded: every cohort state keeps the NWE crack above the 2.60 shutdown floor
- Even when the room runs Europe hard, Rotterdam still covers variable cost
- A disciplined room turns Rotterdam net-positive
- Ledger points reproduced: 3.25 / 4.60 / 5.95

## New week-specific parameters introduced by this package (add to ledger)

- Variable/fixed split for Baton Rouge (3.70 / 2.20) and Singapore (3.40 / 2.20); totals match the ledger opex (5.90 and 5.60).
- Avoidable share of fixed cost: Baton Rouge 0.80, Rotterdam 0.90, Singapore 0.80 $/bbl. The spec called for this split without numbers.
- crack_history.csv: 36-month deterministic crack series, deliberately ambiguous between cyclical trough and structural decline.

## Flags for review

- Window 1 response is symmetric as recorded in the ledger (slope 9 either side of 75% utilization: 3.25 / 4.60 / 5.95). The ledger text calls it asymmetric. Numbers encoded as recorded; decide whether to add asymmetry.