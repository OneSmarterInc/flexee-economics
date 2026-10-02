# Halden Energy — Week 5 Authoritative Package
## Currency — transaction, translation, economic exposure

Batch 16A status: **PASS** (see VALIDATION_16A.md).

Built to the Week 6 / Week 8 standard. Economics computed from the canonical CSVs and reconciled against the constants ledger before any file was written. All figures are provisional design-draft calibration.

## Contents

- `data/` — canonical CSVs, the single source of truth: `fx_shock.csv`, `entity_flows.csv`, `norway_unit_cost.csv`, `existing_hedges.csv`, `forwards_options.csv`, `worked_example_entity.csv`
- `halden_week5.xlsx` — student workbook (README, D_* data tabs, Worked Example, Your Analysis)
- `halden_week5_analysis.ipynb` — student notebook, same data, same tasks
- `faculty/halden_week5_FACULTY_SOLUTION.xlsx` — solved workbook; answers are live Excel formulas on the canonical data
- `faculty/halden_week5_FACULTY_SOLUTION.ipynb` — computes every answer and asserts it against the golden fixtures
- `fixtures/week5_golden.json` — golden fixtures: results, worked example, ordering assertions, tolerance
- `fixtures/provenance.json` — SHA-256 of every artifact, package and artifact versions
- `VALIDATION_16A.md` — gate result

## Expected outputs (headline)

- `eur_change` = -0.0645
- `norway_benefit_musd` = 84.6154
- `norway_lifting_post` = 25.8462
- `euro_retail_translation_musd` = -77.4194
- `existing_hedge_gain_musd` = 19.3548
- `rot_natural_hedge_ratio` = 0.9
- `rot_net_impact_musd` = -16.129
- `rot_overhedge_loss_musd` = -145.1613
- `sing_impact_musd` = -2.2222

## What the fixtures pin

- Krone weakness HELPS Halden (Norwegian dollar costs fall)
- Norwegian lifting falls from 28.00 to about 25.85 (reconciles with ledger)
- European retail takes a translation hit of about -6.45%
- Rotterdam is a partial natural hedge: net euro exposure is 10% of gross euro costs or less
- Hedging Rotterdam gross euro costs loses 9x more than the true net exposure
- Singapore is not a live exposure this week (under 1% of flow)

## New week-specific parameters introduced by this package (add to ledger)

- entity_flows.csv: annual flows by entity and currency in $M at pre-shock rates (Norway revenue 3,200 USD / costs 1,100 NOK; European retail 1,200 EUR; Rotterdam revenue 2,500 EUR, EUR costs 2,250, USD spot crude 180; Singapore 300 SGD). The spec described the denominations without amounts.
- existing_hedges.csv: one EUR forward sale, $300M notional, 6 months.
- forwards_options.csv: 1-year forwards and collar premiums.