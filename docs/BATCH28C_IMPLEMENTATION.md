# Batch 28C Implementation

Batch 28C connects the package-backed Week 13 factor-markets engine to runtime evaluation. It does not add KPI/ranking integration, standing changes, consequence links, UI changes beyond existing generic submission status, what-if, or LLM behavior.

## Runtime Architecture

```text
Week 13 Decision Submission
        ↓
WeekExecutionService
        ↓
Week13EconomicEvaluationService
        ↓
Week13EconomicEngine
        ↓
Week13EconomicEvaluation
```

## Persistence

Created:

```text
week13_economic_evaluations
Week13EconomicEvaluation
```

The evaluation stores:

- tenant, section simulation, runtime week, team, and source decision submission;
- engine identifier and version;
- package identifier and version;
- input and output snapshots;
- Norway wage concession outputs;
- Permian MRP and MRP-to-wage outputs;
- turnaround timing outputs;
- package-defined asset-health penalty output;
- wage benchmarks;
- worked-example snapshot;
- ordering assertions;
- evaluator and timestamp.

Evaluations are immutable after creation. Idempotency is enforced by:

```text
tenant_id + decision_submission_id + engine_identifier
```

## Evaluation Service

`Week13EconomicEvaluationService`:

- requires a submitted Week 13 decision;
- requires an authorized same-tenant faculty/admin actor;
- loads `Week13ReferencePackage`;
- returns an unavailable evaluation if the package is missing;
- invokes `Week13EconomicEngine`;
- persists the immutable evaluation with submission answers included in the input snapshot.

The service does not interpret submitted answers as formula inputs yet because the authoritative Week 13 runtime decision-field mapping remains open. The package remains the source of economic calculations.

## Execution Integration

`WeekExecutionService` now routes Week 13 submitted decisions through `Week13EconomicEvaluationService`.

KPI and ranking steps remain deferred for Week 13.

## Student Status

The generic student submission page now reports Week 13 as:

- `unresolved` before `Week13EconomicEvaluation` exists;
- `resolved` after runtime execution creates an evaluation.

No faculty solution artifacts are exposed to students.

## Tests

Added:

- `tests/Feature/Economics/Week13EconomicEvaluationServiceTest.php`
- `tests/Feature/Week13/Week13RuntimeIntegrationSmokeTest.php`

Covered:

- idempotent submitted-decision evaluation;
- package-backed golden outputs in persisted records;
- unavailable package behavior without fallback formulas;
- draft rejection;
- tenant isolation;
- student submission to faculty execution smoke path;
- student resolved-status visibility;
- no KPI/ranking/standing/consequence/cohort mutation.

## Deferred

- Week 13 KPI/ranking integration;
- asset-health state mutation;
- standing changes;
- consequence links;
- Week 13-specific UI;
- what-if;
- LLM interpretation.
