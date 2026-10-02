# Batch 18D - Week 8 KPI and Ranking Integration

Batch 18D connects persisted Week 8 OPEC/scenario evaluations to the existing KPI and ranking pipeline. It does not add new Week 8 economics.

## Architecture

```text
Week8EconomicEvaluation
        |
        v
Week8KpiInputAggregator
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

`WeekExecutionService` now runs Week 8 KPI population and ranking calculation after the Week 8 economic evaluation step.

## Supported Week 8 KPI

The authoritative Week 8 package supports one published KPI input:

```text
refining_net_margin_vs_benchmark
```

The value is calculated from the realized Week 8 economic outcome:

```text
realized refining crack - package baseline crack
```

For the package-backed full-hold realization fixture, this produces:

```text
16.60 - 21.50 = -4.9000
```

This uses the realized economic outcome while preserving the team's prediction snapshot for later reasoning-versus-luck review.

## Unavailable Metrics

The remaining published KPI definitions are still stored as unavailable snapshots with null values. Batch 18D does not fabricate:

- integrated margin per BOE;
- ROACE;
- free cash flow;
- retail non-fuel margin per site;
- net debt to EBITDA;
- asset health index.

This preserves the existing scoring rule: unavailable KPI values are not treated as zero.

## Ranking Behavior

Week 8 ranking snapshots are created through the standard ranking service, but they remain:

```text
status = incomplete
composite_score = null
rank = null
```

because the full published KPI basis is not available yet.

## Snapshot Provenance

Week 8 KPI snapshots include the generic KPI metadata plus a nested `source_snapshot` with:

- Week 8 economic evaluation id;
- engine version;
- package version;
- prediction snapshot;
- realization snapshot;
- input snapshot;
- output snapshot.

The KPI snapshot does not point to an `EconomicResolution` record because Week 8 uses `Week8EconomicEvaluation` as its economic record type.

## Idempotency

`Week8KpiPopulationService` reuses existing KPI snapshots for the same tenant, runtime week, team, KPI definition, and calculation version. Re-running week execution does not create duplicate Week 8 KPI snapshots.

## Deferred

Still deferred:

- Week 6 to Week 8 cohort response economics;
- Week 8 standing changes;
- Week 9 consequences;
- Week 8 what-if;
- LLM interpretation;
- Week 8-specific UI beyond the existing runtime path;
- complete Week 8 ranking once additional KPI inputs exist.

The Week 8 OPEC propagation coefficient for refining crack remains separate from the Week 6 to Week 8 cohort response now supplied by `halden-window2-cohort-addendum/`.

## Verification

Added/updated focused coverage:

- `tests/Feature/Scoring/Week8KpiPopulationTest.php`
- `tests/Feature/Week8/Week8RuntimeIntegrationSmokeTest.php`

The tests cover supported KPI population, unavailable KPI handling, idempotency, prediction/realization snapshot preservation, Week 8 runtime KPI/ranking creation, incomplete ranking behavior, and absence of consequence/cohort side effects.
