# Halden Energy — Week KPI scoring & consequences Authoritative Package
## Seven-KPI financial state, composite scoring, and consequence catalog

Batch 16A status: **PASS** (see VALIDATION_16A.md).

Built to the Week 6 / Week 8 standard. Economics computed from the canonical CSVs and reconciled against the constants ledger before any file was written. All figures are provisional design-draft calibration.

## Contents

- `data/` — canonical CSVs, the single source of truth: `state_opening.csv`, `kpi_definitions.csv`, `kpi_rules.csv`, `input_dictionary.csv`, `reference_team_decisions.csv`, `reference_team_inputs.csv`, `consequence_catalog.csv`, `consequence_resolution.csv`, `worked_example_normalization.csv`
- `faculty/halden_kpi_consequence_FACULTY_SOLUTION.xlsx` — solved workbook; answers are live Excel formulas on the canonical data
- `faculty/halden_kpi_consequence_FACULTY_SOLUTION.ipynb` — computes every answer and asserts it against the golden fixtures
- `fixtures/kpi_consequence_golden.json` — golden fixtures: results, worked example, ordering assertions, tolerance
- `fixtures/provenance.json` — SHA-256 of every artifact, package and artifact versions
- `VALIDATION_16A.md` — gate result

## Expected outputs (headline)

- `opening_roace` = 0.1274
- `opening_free_cash_flow` = 7116.0
- `opening_net_debt_to_ebitda` = 1.3
- `section_window2_shift` = -1.5
- `disciplined_w13_composite` = 66.4766
- `harvester_w13_composite` = 31.6476
- `ambitious_w13_composite` = 58.5492
- `steady_w13_composite` = 81.3314
- `final_leader` = steady

## What the fixtures pin

- Composite weights sum to 1.00 (30/15/15/10/10/10/10)
- Opening state reproduces design: ROACE 12.7%, FCF $7,116M, net debt/EBITDA 1.30x
- All seven KPIs are available for every team in every week (no more incomplete rankings)
- Week 1: identical opening state, so every team ties at rank 1
- Ranks do not change when a KPI is rescaled (units cannot distort the composite)
- Composite stays within 0-100 for every team and week
- Lazy transfer price lowers integrated margin in Week 4
- Funding Helix depresses near-term ROACE versus funding Rotterdam (back-loaded project)
- Deferred maintenance shows up: the harvester ends with the lowest asset health
- Window 2 input used in KPI rules equals the shift resolved from the section's Week 6 decisions
- Every active consequence re-derives from team decisions to its expected value
- Week 10 binding counts used in KPI rules equal those derived from the consequence rules

## Changelog

- 1.0.1: Decision recorded — Week 10 Delacroix cover follows the Week 4 numbers (cover when refining reports above target, i.e. marginal-cost or lazy pricing). Rule unchanged; prose corrected across specs and guides.
- 1.0.1: Decision recorded — the Week 6 → Week 11 Kessana-capital thread is dropped (status "dropped" in consequence_catalog.csv). No fixture value changed.

## New week-specific parameters introduced by this package (add to ledger)

- Opening financial state: EBITDA $15,000M run-rate, capital employed $62,000M, net debt $19,500M, sustaining capex $4,500M, tax 30%, D&A 6% of capital, asset health 70/100.
- Throughput and volume conversions: Permian-Baton Rouge chain 250 kb/d; Baton Rouge 520 kb/d; Rotterdam 330 kb/d; Singapore 200 kb/d Halden share (refining total 1,050 kb/d per the ledger).
- All coefficients in kpi_rules.csv, each with its rationale column.
- Cohort state thresholds: disciplined if at least 70% of teams choose marginal-cost transfer pricing; lax if at least 70% choose market-based; otherwise base.
- Week 3 utilization mapping for window 1: running Rotterdam = 0.80, idled = 0.40.
- Constraint value rules: hedge coverage by Whitaker standing; cancellable capex = (envelope − Helix if funded) × 0.30; cash cushion formula (see consequence_catalog.csv).

## Flags for review

- Reference team inputs for Weeks 2, 5, 8, 11, 12, 13 are synthetic test paths chosen to exercise the rules. They are fixtures for the scoring engine, not economic claims.
- KPI rule coefficients are first-pass calibration; review alongside the market-data refresh.