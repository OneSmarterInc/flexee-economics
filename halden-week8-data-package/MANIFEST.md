# Halden Energy — Week 8 Authoritative Package
## OPEC+ and integrated response — the week that sets the propagation coefficients

Built to the Week 4 / Week 6 / Week 10 worked-reference standard, as the ingestion source for
Batch 16A. Package-first: economics verified against the constants ledger before any file was built.
Week 8's load-bearing artifacts are the three price-propagation coefficients and the scenario
structure, so the golden fixtures pin the coefficients and the integration conflict, not an
allocation.

## What makes Week 8 different

Week 8 establishes the price-propagation mechanics that every downstream week inherits: a $1 WTI
move reaches upstream 1:1, compresses the refining crack at -0.35 (short-run), and barely touches
retail volume (inelastic fuel demand). The pedagogical core is the integration conflict — one shock
that helps upstream and hurts refining, so the right answer for Halden is not the sum of the right
answers for its segments. The fixtures assert that conflict directly.

## Contents

- `data/` — canonical CSVs (single source of truth):
  - `opec_scenarios.csv` — three resolved states with sim probabilities and delta WTI
  - `propagation_coefficients.csv` — upstream 1.0, crack -0.35, pass-through 0.60, elasticity -0.05
  - `baseline_state.csv` — pre-shock WTI, Brent, crack, pump base
  - `compliance_history.csv` — OPEC compliance episodes (basis for the probability estimate)
  - `worked_example_prior.csv` — prior shock for the worked example
- `halden_week8.xlsx` — student workbook (README, Data — Scenarios & Propagation, Data — Compliance,
  Worked Example (solved expected-value), Your Analysis)
- `halden_week8_analysis.ipynb` — student notebook, parallel, self-checking
- `faculty/halden_week8_FACULTY_SOLUTION.xlsx` — the solved reference (sim-weight answers)
- `fixtures/week8_golden.json` — golden fixtures: coefficients, per-scenario segment impacts,
  expected WTI, and seven ordering assertions including the integration conflict
- `fixtures/provenance.json` — SHA-256 hashes of every artifact, package and artifact versions

## Expected outputs (the question Batch 16A requires answerable)

- Propagation per scenario: holds_full (+14) upstream +14.0, crack 16.60, retail -0.31%;
  holds_partial (+7) upstream +7.0, crack 19.05, retail -0.16%; fails (-4) upstream -4.0,
  crack 22.90, retail +0.09%
- Expected WTI: 80.70
- Integration conflict: when the cut holds full, upstream gains $14/bbl while the crack loses $4.90

## Verification (all passed)

- Workbook recalcs clean (0 errors). Notebook executes clean and agrees with the workbook and the
  ledger on every figure. Faculty solution matches the fixtures.
- All seven golden assertions pass, including the integration-conflict ordering.
- Economics reconciled against constants-ledger.md before build.

## Calibration note

All figures provisional design-draft calibration. The load-bearing relationships to preserve on any
refresh: the propagation coefficients (upstream 1:1, crack -0.35, retail elasticity -0.05) and the
integration conflict (upstream gains while the crack compresses when the cut holds). The golden
fixtures enforce these.
