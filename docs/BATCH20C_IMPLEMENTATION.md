# Batch 20C - Week 10 Runtime Integration

## Purpose

Batch 20C connects the existing Week 10 convergence engine to the runtime execution pipeline without adding KPI, ranking, consequence, cohort, what-if, LLM, or new UI behavior.

The runtime flow is:

```text
DecisionSubmission
        |
        v
Week10InheritedStateAssembler
        |
        v
Week10ConvergenceEconomicEngine
        |
        v
Week10EconomicEvaluation
        |
        v
WeekExecutionService
```

## Historical-State Assembly

`Week10InheritedStateAssembler` is the integration boundary for historical dependencies. It reads the following dependencies from existing runtime records:

- Week 6 cancellable capex from a calculated `CapitalAllocationEvaluation`.
- Week 5 crude hedge coverage from a submitted `DecisionSubmission`.
- Week 4 Baton Rouge reported-margin condition from an `EconomicResolution`.
- Straits Pacific standing from `StandingState`.
- Week 8 cash cushion from a calculated `Week8EconomicEvaluation`.

Prior records must expose Week 10 handoff values explicitly in snapshots, preferably:

```json
{
    "week10_inherited_state": {
        "cancellable_capex_musd": "120.0"
    }
}
```

The assembler does not infer missing values. If a record or required handoff field is absent, the dependency is marked unresolved.

## Persistence

`week10_economic_evaluations` stores:

- tenant, section simulation, runtime week, team, and submission references;
- engine identifier and version;
- package version;
- demand hits;
- refinery demand hits;
- hardest-hit refinery;
- binding constraint count;
- unresolved dependency keys;
- inherited-state snapshot;
- input and output snapshots;
- evaluator actor/process/timestamp.

The table has an idempotency constraint on:

```text
tenant_id + decision_submission_id + engine_identifier
```

Evaluations are immutable after creation.

## Execution Integration

`WeekExecutionService` now evaluates Week 10 submitted decisions during the `resolve_decisions` step. KPI, ranking, consequence generation, cohort feedback, and publication remain unchanged/deferred for Week 10.

## Student And Faculty Visibility

The existing student workspace now treats a persisted `Week10EconomicEvaluation` as a resolved Week 10 status. Faculty week control uses the authoritative Week 10 package type when validating active content.

## Deferred

Still deferred:

- Week 10 KPI/ranking integration;
- standing mutation;
- new consequence links;
- cohort feedback;
- Week 10 what-if;
- LLM interpretation;
- new Week 10-specific UI.
