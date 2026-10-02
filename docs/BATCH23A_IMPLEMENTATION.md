# Batch 23A - Week 10 KPI and Ranking Integration

## Purpose

Batch 23A connects calculated Week 10 convergence evaluations to the existing KPI and ranking pipeline.

The runtime flow is:

```text
Week10EconomicEvaluation
        |
        v
Week10KpiInputAggregator
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

No Week 10 consequences, standing transitions, cohort effects, what-if support, LLM behavior, or new UI behavior were added.

## Supported Week 10 KPIs

The authoritative Week 10 package supplies:

- product demand hits;
- blended demand hit;
- refinery-specific demand hits;
- hardest-hit refinery;
- binding-constraint count;
- inherited-state provenance.

It does not supply a published KPI value for the current seven-KPI catalog. Batch 23A therefore does not mark any Week 10 KPI as available.

This is intentional. Week 10 is now represented in the KPI framework, but the package does not define an authoritative transformation from convergence demand/constraint outputs into:

- integrated margin per BOE;
- refining net margin versus benchmark;
- ROACE;
- free cash flow;
- retail non-fuel margin per site;
- net debt to EBITDA;
- asset health index.

## Unavailable KPIs

Calculated Week 10 evaluations create seven KPI snapshots, all with:

```text
status = unavailable
value = null
```

Unavailable published KPIs are never stored as zero. The unavailable reasons come from the existing KPI calculation framework, for example:

- `integrated_margin_per_boe`: requires integrated margin input;
- `refining_net_margin_vs_benchmark`: requires refining benchmark state;
- `roace`: requires capital base state;
- `free_cash_flow`: requires cash flow state;
- `retail_non_fuel_margin_per_site`: requires retail site margin state;
- `net_debt_to_ebitda`: requires debt and EBITDA state;
- `asset_health_index`: requires asset health model.

## Provenance

Each Week 10 KPI snapshot preserves a source snapshot with:

- Week 10 economic evaluation id;
- engine version;
- package version;
- evaluation status;
- inherited-state snapshot;
- Week 10 input snapshot;
- Week 10 output snapshot;
- headline economic outputs.

This keeps the KPI layer traceable back through the Week 10 evaluation to the persisted Week 4, Week 5, Week 6, Week 8, and standing-state dependencies captured by `Week10InheritedStateAssembler`.

## Ranking Behavior

`WeekExecutionService` now calculates rankings for Week 10 after KPI population.

Because the full published KPI basis is unavailable, Week 10 ranking snapshots are created as:

```text
status = incomplete
composite_score = null
rank = null
```

The ranking service continues to reject missing/unavailable KPIs rather than treating them as zero.

## Execution Integration

Week 10 KPI population only runs for:

```text
Week10EconomicEvaluation.status = calculated
```

Unresolved or unavailable Week 10 evaluations do not create KPI snapshots.

## Tests

Added focused coverage in:

```text
tests/Feature/Scoring/Week10KpiPopulationTest.php
```

Updated Week 10 smoke/regression expectations in:

```text
tests/Feature/Week10/Week10RuntimeIntegrationSmokeTest.php
tests/Feature/Economics/MultiWeekHistoricalIntegrationTest.php
```

Coverage includes:

- KPI snapshot creation from calculated Week 10 evaluations;
- all unsupported KPIs remain unavailable/null;
- no fake zero KPI values;
- source snapshot provenance points to the Week 10 evaluation and inherited-state snapshot;
- idempotent KPI population;
- unresolved evaluations create no KPI snapshots;
- Week 10 ranking snapshots remain incomplete;
- team isolation for KPI and ranking visibility;
- Week 10 runtime execution now reaches KPI/ranking without creating consequences or cohort effects.

## Deferred

Still deferred:

- Week 10 consequence mapping;
- Week 10 standing transitions;
- Week 10 what-if;
- Week 10 LLM interpretation changes;
- cohort effects;
- new Week 10 UI;
- authoritative mapping from Week 10 convergence outputs into any published KPI value.
