# Halden Energy — Week 6 Authoritative Package
## Capital allocation — the week that consumes the cohort feedback

Built to the Week 4 / Week 10 worked-reference standard, to serve as the ingestion source for
Batch 16A. Package-first: the economics were verified against the constants ledger before any file
was built, and the golden fixtures assert the load-bearing pedagogical relationship (the NPV/IRR
ranking flip), not just point values.

## Pattern

Week 6 follows the Week 4 single-decision shape but adds one thing that makes it harder: its
discount rate is not a standalone input, it is a validated consequence of the cohort's Week 4
transfer-pricing discipline. The package encodes that linkage explicitly (see
`data/cohort_discount_schedule.csv`) so the discount-rate rule can be reconciled, not left in
narrative.

## Contents

- `data/` — canonical CSVs (single source of truth; workbook and notebook both load these):
  - `project_cashflows.csv` — the three projects' cash flows, year 0 = outlay
  - `cohort_discount_schedule.csv` — discount rate AND capital envelope by cohort Wk4 behavior
  - `cost_of_capital.csv` — base hurdle rates by risk class (Helix risk flag)
  - `forecast_haircuts.csv` — historical forecast-accuracy haircuts (new-business 61%)
  - `currency_helix.csv` — the Helix EUR/USD structure (folded currency concept)
  - `worked_example_prior.csv` — prior two-project case for the worked example
- `halden_week6.xlsx` — student workbook. README, Data — Projects, Data — Adjustments,
  Worked Example (solved, NPV/IRR divergence), Your Analysis (yellow cells).
- `halden_week6_analysis.ipynb` — student notebook, parallel to the workbook, self-checking.
- `faculty/halden_week6_FACULTY_SOLUTION.xlsx` — the solved reference (disciplined-cohort answers).
- `fixtures/week6_golden.json` — golden fixtures: IRRs, per-cohort NPVs, ranking by cohort, the
  BR/Helix crossover, and seven ORDERING assertions that pin the flip itself.
- `fixtures/provenance.json` — SHA-256 hashes of every artifact, package and artifact versions.

## Expected outputs (the question Batch 16A requires answerable)

"Given these exact inputs, what are the expected Week 6 outputs?" — answered in the fixtures:

- IRRs: Baton Rouge 19.4%, Rotterdam 15.0%, Helix 12.4%
- NPV by cohort: disciplined (6.5%) Helix 388.4 > BR 322.4 > Rot 147.4; base (8.5%) BR 256.7 >
  Helix 236.2 > Rot 106.0; lax (11.0%) BR 184.4 > Helix 76.8 > Rot 60.7
- BR/Helix NPV crossover: 7.99% — disciplined rate below it (Helix wins), base/lax above it (BR wins)
- Haircut at 6.5%: Helix 388.4 -> 236.9 (61% new-business haircut bites hardest)

## Verification (all passed)

- Workbook recalcs clean (0 errors). Notebook executes clean and agrees with the workbook and the
  ledger on every figure. Faculty solution NPVs match the fixtures exactly.
- All seven golden ordering assertions pass, including "the highest-IRR project is NOT the
  highest-NPV project for the disciplined cohort" and "the cohort rates straddle the crossover."
- Economics reconciled against constants-ledger.md before build.

## Calibration note

All figures are provisional design-draft calibration. When refreshed against current market data,
the load-bearing relationship to preserve is the crossover straddle: the disciplined discount rate
must stay below the BR/Helix crossover and the base/lax rates above it, or the capital-allocation
lesson breaks. The golden fixtures enforce this.
