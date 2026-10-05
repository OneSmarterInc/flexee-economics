# Batch 34B - KPI / Consequence Package v1.0.1 Integration

## Source Package

The authoritative package is `halden-kpi-consequence-package/` version `1.0.1`.

The package provides:

- `data/state_opening.csv`
- `data/kpi_definitions.csv`
- `data/kpi_rules.csv`
- `data/input_dictionary.csv`
- `data/consequence_catalog.csv`
- `data/reference_team_inputs.csv`
- `data/reference_team_decisions.csv`
- `data/worked_example_normalization.csv`
- `fixtures/kpi_consequence_golden.json`
- `fixtures/provenance.json`

Source package files and fixtures were not modified.

## Package Reader

`KpiConsequenceReferencePackage` now exposes the canonical package data required by scoring and consequence registration:

- opening state;
- KPI definitions;
- KPI rules;
- input dictionary;
- reference inputs and decisions;
- worked normalization example;
- golden fixture;
- source hashes and package version.

## KPI Definition Catalog

`KpiDefinitionCatalog` now publishes the seven KPI definitions from `data/kpi_definitions.csv` rather than maintaining a local static definition list.

The active KPI definition version is:

```text
halden_kpi_consequence_v1_0_1
```

The package supplies the KPI weights and ranking direction metadata. The ranking direction is important because `net_debt_to_ebitda` is a lower-is-better metric.

## Financial State Engine

`KpiFinancialStateService` applies the package `kpi_rules.csv` generically.

The service starts from `state_opening.csv`, then applies:

```text
state[state_var] += coefficient x input[input_key]
```

for all package rules through the requested runtime week.

Runtime input extraction is sourced from persisted evaluation/history records, including:

- Week 2 elasticity evaluation;
- Week 3 shutdown evaluation;
- Week 4 transfer-pricing economic resolution;
- Week 5 currency evaluation;
- Week 6 capital allocation evaluation;
- Week 7 competitive response evaluation;
- Week 8 OPEC evaluation and cohort adjustment;
- Week 9 retail evaluation;
- Week 10 convergence evaluation;
- Week 11 Kessana evaluation;
- Week 12 transition portfolio evaluation;
- Week 13 factor-market evaluation.

Absent future-week records default the corresponding package input to zero. This is intentional for variants where those weeks do not run, such as the seven-week path.

## KPI Calculations

`KpiCalculationService` now calculates all seven published KPIs from the package-backed financial state:

- integrated margin per BOE;
- ROACE;
- free cash flow;
- refining net margin vs benchmark;
- retail non-fuel margin per site;
- net debt to EBITDA;
- asset health index.

The calculation version is:

```text
kpi_consequence_v1_0_1
```

Decimal calculations use `Brick\Math\BigDecimal` and `RoundingMode::HalfUp`.

## Ranking

`RankingCalculationService` now applies package ranking behavior:

- per-KPI section min/max normalization to a 0-100 score;
- lower-is-better normalization for `net_debt_to_ebitda`;
- all-equal KPI values normalize to `50.000000`;
- composite score is the weighted sum of normalized KPI scores;
- tied composite scores share rank.

Incomplete ranking behavior remains in place when required KPI snapshots are missing or unavailable.

## Consequence Catalog

`KpiConsequenceDefinitionCatalog` can register active consequence definitions from `data/consequence_catalog.csv`.

Rows marked as `deferred`, `narrative`, or `dropped` remain non-runtime records and are not registered as active automatic consequence definitions.

## Runtime Integration

`WeekExecutionService` now uses package-backed KPI population for supported executed weeks.

Week-specific services for Weeks 4, 8, 10, and 11 delegate to the package-backed population path after verifying the relevant evaluation exists. Other supported weeks use the generic package-backed population path directly.

The execution pipeline now creates KPI snapshots and ranking snapshots for weeks whose package-backed state can be evaluated, instead of leaving later weeks unmeasured by default.

## Reference Validation

Focused tests validate:

- package provenance access;
- active consequence catalog registration;
- opening-state KPI parity with the golden fixture;
- Week 13 steady-state KPI parity with the golden fixture;
- ranking normalization including the lower-is-better debt metric.

Golden tolerance follows the package standard:

```text
relative: 1e-3
absolute: 1e-5
```

## Deferred

This batch does not implement:

- production deployment;
- effective-dated seat assignments;
- Week 2 authored UI changes;
- LLM behavior;
- automatic grading;
- speculative consequences;
- Week 7 compounding beyond existing package-backed inputs;
- new cohort windows.

## Verification Note

Backend verification was completed with Herd PHP `8.4.0`.

Verified:

- full Laravel test suite;
- PHPStan;
- Pint;
- Composer validation;
- frontend check;
- frontend type check;
- frontend production build;
- `git diff --check`.
