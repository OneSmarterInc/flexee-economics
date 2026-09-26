# Batch 18C - Week 8 Runtime Integration

Batch 18C integrates the existing Week 8 OPEC/scenario economic engine into the runtime execution pipeline. It does not reimplement Batch 18B economics.

## Architecture

```text
DecisionSubmission
        |
        v
Week8EconomicEvaluationService
        |
        v
Week8ReferencePackage
        |
        v
Week8EconomicEngine
        |
        v
Week8EconomicEvaluation
```

`WeekExecutionService` now recognizes Week 8 during the `resolve_decisions` step and evaluates submitted Week 8 decision submissions through `Week8EconomicEvaluationService`.

## Submission Mapping

The service maps submitted decision answers into the engine without duplicating formulas:

- `probability_holds_full`
- `probability_holds_partial`
- `probability_fails`
- optional `realized_scenario_key`

The three probability fields become the student prediction distribution. The optional realized scenario remains separate and is passed as the simulation realization. If no realization is supplied, the evaluation stores expected and predicted values without fabricating an actual scenario.

## Persistence

`week8_economic_evaluations` stores immutable evaluation records with:

- tenant, section simulation, runtime week, team, and decision submission references;
- engine identifier and version;
- package version;
- expected WTI, upstream impact, and refining crack;
- prediction expected values;
- optional realized scenario outputs;
- prediction, realization, input, and output snapshots;
- actor/process metadata and timestamp.

Uniqueness is enforced on:

```text
tenant_id + decision_submission_id + engine_identifier
```

Repeated execution returns the existing evaluation rather than creating a duplicate.

## Prediction vs Realization

The evaluation preserves three distinct concepts:

- package scenario distribution;
- student/team prediction distribution;
- realized scenario and realized economic outcome.

This is required for later reasoning-versus-luck analysis.

## OPEC and Cohort Separation

This batch uses only the Week 8 OPEC shock engine:

```text
OPEC scenario -> WTI -> upstream/refining/retail propagation
```

It does not implement the Week 6 to Week 8 cohort response:

```text
Week 6 aggregate capacity -> cohort response function -> Week 8 market effect
```

No cohort feedback effects are created by Week 8 runtime evaluation.

## Deferred

Still deferred:

- Week 8 KPI/ranking effects;
- standing changes;
- Week 9 consequences;
- Week 6 to Week 8 cohort response economics;
- Week 8 what-if;
- LLM interpretation;
- dedicated Week 8 UI beyond the generic submission workspace.

## Verification

Added focused tests:

- `tests/Feature/Economics/Week8EconomicEvaluationServiceTest.php`
- `tests/Feature/Week8/Week8RuntimeIntegrationSmokeTest.php`

The tests cover idempotency, draft rejection, tenant isolation, student submission, faculty execution, persisted Week 8 evaluation, prediction/realization separation, and absence of downstream KPI/ranking/consequence/cohort mutation.
