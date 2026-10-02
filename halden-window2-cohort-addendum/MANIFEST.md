# Halden Energy — Week 6→8 cohort window 2 Authoritative Package
## Cohort feedback window 2 — Gulf Coast capacity additions

Batch 16A status: **PASS** (see VALIDATION_16A.md).

Built to the Week 6 / Week 8 standard. Economics computed from the canonical CSVs and reconciled against the constants ledger before any file was written. All figures are provisional design-draft calibration.

## Contents

- `data/` — canonical CSVs, the single source of truth: `window2_params.csv`, `cohort_states.csv`, `baton_rouge_margin.csv`, `week8_link.csv`, `worked_example_window.csv`
- `faculty/halden_window2_FACULTY_SOLUTION.xlsx` — solved workbook; answers are live Excel formulas on the canonical data
- `faculty/halden_window2_FACULTY_SOLUTION.ipynb` — computes every answer and asserts it against the golden fixtures
- `fixtures/window2_golden.json` — golden fixtures: results, worked example, ordering assertions, tolerance
- `fixtures/provenance.json` — SHA-256 of every artifact, package and artifact versions
- `VALIDATION_16A.md` — gate result

## Expected outputs (headline)

- `br_margin_baseline` = 20.35
- `crack_shift_all_add` = -3.0
- `crack_shift_most_add` = -1.5
- `crack_shift_split` = 0.0
- `crack_shift_few_add` = 0.75
- `crack_shift_none_add` = 1.5
- `max_abs_change_pct` = 0.1474
- `worst_case_crack_with_opec_holds_full` = 13.6
- `worst_case_br_margin_with_opec_holds_full` = 12.45

## What the fixtures pin

- A split room (50% add capacity) produces no shift
- More capacity added by the room always means a lower Week 8 crack
- Asymmetric: universal overbuild hurts at least twice what universal restraint helps
- Bounded: Baton Rouge margin moves no more than 15% from baseline in any cohort state
- Tuned to be visible: the extreme state moves Baton Rouge margin by at least 10%
- Worst case stacked on the OPEC holds-full shock still leaves crack and Baton Rouge margin positive

## New week-specific parameters introduced by this package (add to ledger)

- Cohort metric: share of a section's teams that fund the Baton Rouge crude flexibility upgrade in Week 6.
- Response: crack shift = −6.0 × (share − 0.50) above the pivot; +3.0 × (0.50 − share) below it. Range −3.00 to +1.50 $/bbl.
- Integration rule: Week 8 Gulf Coast crack = 21.50 + (−0.35 × ΔWTI) + window-2 shift. Applies market-wide to every team in the section.
- Timing: aggregate computed at Week 6 lock, hidden through Weeks 6 and 7, revealed in the Week 8 briefing with each team's contribution to the aggregate.

## Flags for review

- Calibration: the response is tuned for pedagogical visibility (extreme state moves Baton Rouge margin ~15%). An anchor episode and real-world elasticity have not been attached yet. Add them in the market-data refresh, as the design requires for every cohort window.
- Week 7 compounding deferred: the Week 7 spec says a cohort that also matches the rival expansion compounds the overbuild. That term is not included here, so window 2 can run now on Week 6 data alone. Add it when the Week 7 runtime exists.
- Seven-week variant does not use window 2. Its single cohort window is the Week 4 → Week 6 discount-rate linkage.