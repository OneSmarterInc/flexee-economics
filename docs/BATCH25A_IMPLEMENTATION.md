# Batch 25A Implementation

## Purpose

Batch 25A connects Week 11 Kessana runtime evaluations to the existing KPI and ranking pipeline without inventing unsupported KPI mappings.

The runtime measurement flow is:

```text
Week11EconomicEvaluation
        |
        v
Week11KpiInputAggregator
        |
        v
KpiCalculationService
        |
        v
KpiSnapshot
        |
        v
RankingCalculationService
        |
        v
RankingSnapshot
```

No Week 11 consequences, standing changes, what-if support, LLM behavior, Week 12 behavior, or new UI were added.

## KPI Mapping Determination

The authoritative Week 11 package defines:

- Kessana realized price;
- profit oil;
- annual production;
- annuity factor;
- company margin by government-take scenario;
- PV of staying;
- exit value;
- stay-minus-exit;
- indifference take;
- comparable fiscal-term range;
- package ordering assertions.

It does not define an authoritative transformation from those fiscal-decision outputs into any of the seven published KPIs.

## Supported KPIs

No Week 11 KPI is currently supported as an available KPI value.

This is intentional. The Week 11 package validates the Kessana hold-up economics, but it does not map those economics to:

- integrated margin per BOE;
- ROACE;
- free cash flow;
- refining net margin versus benchmark;
- retail non-fuel margin per site;
- net debt to EBITDA;
- asset health index.

## Unavailable KPIs

Calculated Week 11 evaluations create seven KPI snapshots, all with:

```text
status = unavailable
value = null
```

Unavailable published KPIs are never stored as zero. The unavailable reasons come from the shared KPI calculation framework, for example:

- `integrated_margin_per_boe`: requires integrated margin input;
- `refining_net_margin_vs_benchmark`: requires refining benchmark state;
- `roace`: requires capital base state;
- `free_cash_flow`: requires cash flow state;
- `retail_non_fuel_margin_per_site`: requires retail site margin state;
- `net_debt_to_ebitda`: requires debt and EBITDA state;
- `asset_health_index`: requires asset health model.

## Provenance

Each Week 11 KPI snapshot preserves source provenance with:

- Week 11 economic evaluation id;
- source decision submission id;
- engine version;
- package version;
- evaluation status;
- Week 11 input snapshot;
- Week 11 output snapshot;
- headline Kessana economic outputs.

This keeps the measurement layer traceable back to the persisted Kessana evaluation and the team's submitted decision.

## Ranking Behavior

`WeekExecutionService` now calculates rankings for Week 11 after KPI population.

Because all seven Week 11 KPI snapshots are unavailable, ranking snapshots are created as:

```text
status = incomplete
composite_score = null
rank = null
```

The ranking service continues to reject missing or unavailable KPIs rather than treating them as zero.

## Execution Integration

Week 11 KPI population only runs for:

```text
Week11EconomicEvaluation.status = calculated
```

Unavailable Week 11 evaluations do not create KPI snapshots.

## Tests

Added focused coverage in:

```text
tests/Feature/Scoring/Week11KpiPopulationTest.php
```

Updated Week 11 runtime smoke expectations in:

```text
tests/Feature/Week11/Week11RuntimeIntegrationSmokeTest.php
```

Coverage includes:

- KPI mapping determination through unavailable/null behavior;
- no fake zero KPI values;
- source snapshot provenance points to the Week 11 evaluation and decision;
- idempotent KPI population;
- unavailable evaluations create no KPI snapshots;
- Week 11 ranking snapshots remain incomplete;
- team isolation for KPI and ranking visibility;
- Week 11 runtime execution now reaches KPI/ranking without creating consequences, standing changes, or cohort effects.

## Deferred

Still deferred:

- authoritative Week 11 KPI mapping, if one is later supplied;
- Week 11 consequence mapping;
- Week 11 standing transitions;
- Week 11 what-if;
- Week 11 LLM interpretation changes;
- Week 12.
