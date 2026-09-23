# Batch 5A Implementation

Batch 5A adds the KPI framework and scoring infrastructure without implementing ranking, leaderboard publication, standing effects, cohort feedback, causal trace, or future-week consequences.

## Architecture

The framework shape is:

EconomicResolution
-> KPI Input Aggregation Layer
-> KPI Calculation Engine
-> KPISnapshot
-> Ranking Service (future)

`Week4KpiInputAggregator` converts a resolved Week 4 economic result into a generic `KpiCalculationContext`. `KpiCalculationService` accepts that context plus published KPI definitions and returns calculation results. The calculator does not query persistence directly.

## KPI Definitions

`kpi_definitions` stores versioned KPI definitions with:

- key
- name
- description
- weight
- calculation source
- version
- status
- effective dates
- metadata

`KpiDefinitionCatalog` publishes the Halden seven-KPI structure for `halden_kpi_v1`:

- Integrated margin per BOE: 30%
- ROACE: 15%
- Free cash flow: 15%
- Refining net margin vs benchmark: 10%
- Retail non-fuel margin per site: 10%
- Net debt to EBITDA: 10%
- Asset health index: 10%

Weights are validated with decimal arithmetic and must sum to `1.000000`.

## KPI Snapshots

`kpi_snapshots` stores historical KPI results by tenant, section simulation, runtime week, team simulation, team, and KPI definition.

Snapshots include:

- status
- nullable value
- unit
- precision
- calculation version
- input snapshot
- unavailable reason
- calculated timestamp
- optional economic resolution reference

Snapshots are immutable once created. Recalculation creates another snapshot instead of overwriting history.

## Week 4 Inputs

Week 4 currently exposes these KPI inputs from `EconomicResolution`:

- integrated margin per BOE
- upstream margin
- refining margin contribution

Batch 5A calculates only the supported Halden KPI:

- `integrated_margin_per_boe`

Unsupported KPIs are returned as unavailable with explicit reasons. No fake zero values are inserted.

## Deferred Features

The following remain intentionally out of scope:

- leaderboard
- rank calculation
- cross-section leaderboard
- Week 7/14 KPI visibility rules
- final weighted scoring
- standing model
- asset health model
- ROACE calculation
- free cash flow calculation
- debt model
- cohort feedback
- causal trace
- LLM
- Week 10 consequences
