# Batch 5B Implementation

Batch 5B connects resolved Week 4 economic results to KPI snapshot population.

## Flow

The implemented flow is:

Team submits decision
-> WeekResolutionService
-> EconomicResolution
-> Week4KpiPopulationService
-> KpiCalculationService
-> KPISnapshot

KPI calculation is not embedded in `WeekResolutionService`. The Week 4 population service accepts an existing `EconomicResolution`, aggregates supported KPI inputs, calculates KPI results, and stores immutable KPI snapshots.

## Week 4 Supported KPI

Week 4 currently populates:

- `integrated_margin_per_boe`

The value is sourced from the Week 4 `EconomicResolution` integrated margin.

## Unavailable KPIs

The other Halden KPI definitions are still populated as unavailable snapshots, with nullable values and explicit reasons. Batch 5B does not invent zero values for unavailable mechanics.

Unavailable examples:

- ROACE requires capital base state.
- Free cash flow requires cash flow state.
- Net debt to EBITDA requires debt and EBITDA state.
- Asset health index requires an asset health model.

## Idempotency

`Week4KpiPopulationService` is idempotent by economic resolution, KPI definition, and calculation version. Repeating population for the same resolved team/week returns the existing snapshots rather than creating duplicates.

General snapshot storage remains append-only for deliberate future recalculation workflows.

## Snapshot Linkage

Each populated snapshot records:

- tenant
- section simulation
- runtime week
- team simulation
- team
- KPI definition
- calculation version
- source economic resolution
- input snapshot
- calculated timestamp

This preserves the KPI state that existed after a Week 4 decision.

## Deferred Features

The following remain intentionally out of scope:

- rank calculation
- percentile calculation
- leaderboard position
- cross-section leaderboard
- publication and visibility gates
- final scoring
- standing model
- cohort feedback
- causal trace
- LLM
- Week 10 consequences
