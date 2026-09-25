# Halden Energy — Week 9 Authoritative Package
## The Cordell question — brand equity with a price tag

Batch 16A status: **PASS** (see VALIDATION_16A.md).

Built to the Week 6 / Week 8 standard. Economics computed from the canonical CSVs and reconciled against the constants ledger before any file was written. All figures are provisional design-draft calibration.

## Contents

- `data/` — canonical CSVs, the single source of truth: `markets.csv`, `rebrand_params.csv`, `nonfuel_states.csv`, `worked_example_markets.csv`
- `halden_week9.xlsx` — student workbook (README, D_* data tabs, Worked Example, Your Analysis)
- `halden_week9_analysis.ipynb` — student notebook, same data, same tasks
- `faculty/halden_week9_FACULTY_SOLUTION.xlsx` — solved workbook; answers are live Excel formulas on the canonical data
- `faculty/halden_week9_FACULTY_SOLUTION.ipynb` — computes every answer and asserts it against the golden fixtures
- `fixtures/week9_golden.json` — golden fixtures: results, worked example, ordering assertions, tolerance
- `fixtures/provenance.json` — SHA-256 of every artifact, package and artifact versions
- `VALIDATION_16A.md` — gate result

## Expected outputs (headline)

- `cost_per_site` = 79069.7674
- `net_per_fill_LA_MS_core` = -0.045
- `payback_years_gulf_secondary` = 14.6425
- `payback_years_southeast_edge` = 5.491
- `partial_gain_musd` = 22.68
- `partial_cost_musd` = 193.7209
- `partial_payback_years` = 8.5415
- `full_net_gain_musd` = 7.695
- `pricewar_partial_payback_years` = 9.4406

## What the fixtures pin

- Strong-equity core: rebranding destroys value (net per fill < 0), keep Cordell
- Southeast edge pays back faster than gulf secondary
- Partial rebrand earns about 3x the full rebrand (core equity destruction)
- Ledger reconciliation: partial $22.7M/yr, full $7.7M/yr, edge 5.5 yrs, gulf 14.6 yrs
- A price-war cohort faces a longer payback (window 3 consequence)

## New week-specific parameters introduced by this package (add to ledger)

- Price-war sensitivity rule: rebrand gains scale with the cohort non-fuel margin (gain × state margin ÷ 0.42). The spec stated the direction without a rule.

## Flags for review

- fills_per_site_year (180,000) scales every payback proportionally. It is a calibration lever for the market-data refresh, not a fixed constant.