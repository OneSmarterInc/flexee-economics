# Batch 22C - Week 10 Consumption Of Week 5 Runtime State

## Purpose

Batch 22C hardens Week 10 historical-state assembly so crude hedge coverage is sourced from persisted Week 5 runtime evaluations instead of raw Week 5 submissions.

The verified flow is:

```text
Week5EconomicEvaluation
        |
        v
Week10InheritedStateAssembler
        |
        v
Week10ConvergenceEconomicEngine
        |
        v
Week10EconomicEvaluation
```

No KPI, ranking, consequence, standing, what-if, LLM, or UI behavior was added.

## Assembler Change

`Week10InheritedStateAssembler::crudeHedgeCoverage()` now requires:

```text
Week5EconomicEvaluation.status = calculated
```

It reads `crude_hedge_coverage` from the Week 5 evaluation snapshots, preferring:

1. `output_snapshot.week10_inherited_state.crude_hedge_coverage`
2. `input_snapshot.week10_inherited_state.crude_hedge_coverage`
3. `decision_snapshot.week10_inherited_state.crude_hedge_coverage`
4. `decision_snapshot.answers.crude_hedge_coverage`
5. `decision_snapshot.answers.hedge_coverage`

The dependency metadata now records:

```text
source_entity = week5_economic_evaluation
source_version = Week5EconomicEvaluation.engine_version
```

Raw Week 5 submissions no longer satisfy the Week 10 hedge dependency.

## Tests Updated

Updated:

```text
tests/Feature/Economics/MultiWeekHistoricalIntegrationTest.php
tests/Feature/Economics/Week10EconomicEvaluationServiceTest.php
tests/Feature/Week10/Week10RuntimeIntegrationSmokeTest.php
```

Coverage:

- real Week 5 evaluations populate Week 10 hedge coverage;
- changing Week 5 persisted hedge coverage changes Week 10 inherited context;
- removing Week 5 evaluation returns `unresolved_dependency`;
- raw Week 5 submissions without evaluation do not satisfy the dependency;
- Week 4, Week 6, Week 8, and standing dependency paths remain unchanged;
- Week 10 runtime execution still uses assembled persisted history.

Focused result:

```text
14 tests / 132 assertions
```

## Deferred

Still deferred:

- Week 5 KPI/ranking;
- Week 5 consequences;
- Week 10 KPI/ranking;
- standing mutation;
- what-if;
- LLM interpretation;
- UI changes.
