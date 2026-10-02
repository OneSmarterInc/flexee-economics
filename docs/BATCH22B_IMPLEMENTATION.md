# Batch 22B - Week 5 Runtime Integration

## Purpose

Batch 22B connects the Week 5 currency engine to runtime execution.

The implemented flow is:

```text
Week 5 Decision Submission
        |
        v
WeekExecutionService
        |
        v
Week5EconomicEvaluationService
        |
        v
Week5EconomicEngine
        |
        v
Week5EconomicEvaluation
```

This batch does not add Week 5 KPI/ranking effects, consequences, standing changes, what-if support, LLM interpretation, or UI redesign.

## Persistence

Added:

```text
week5_economic_evaluations
App\Models\Week5EconomicEvaluation
```

The evaluation stores:

- tenant, section simulation, runtime week, team, and submission references;
- engine identifier and version;
- package version;
- evaluation status;
- decimal-safe Week 5 outputs;
- decision snapshot;
- input snapshot;
- output snapshot;
- actor/process and timestamp.

Rows are immutable once created.

## Evaluation Service

Added:

```text
App\Domain\Economics\Week5\Week5EconomicEvaluationService
```

Responsibilities:

- accept submitted Week 5 `DecisionSubmission` records;
- reject drafts and wrong-week submissions;
- enforce tenant/faculty authorization;
- load `Week5ReferencePackage`;
- call `Week5EconomicEngine`;
- persist `Week5EconomicEvaluation`;
- return existing evaluations idempotently.

The service records submitted hedge-coverage answers inside `decision_snapshot` and exposes the same value under:

```text
output_snapshot.week10_inherited_state.crude_hedge_coverage
```

This preserves the historical state for later handoff work without changing the Week 10 assembler in this batch.

## Execution Integration

`WeekExecutionService` now recognizes Week 5 during `resolve_decisions` and evaluates submitted Week 5 decisions through `Week5EconomicEvaluationService`.

Execution output records:

```text
week5_economic_evaluation_count
evaluation_status_counts
```

## Student And Faculty Runtime Visibility

Week 5 now uses the generic authoritative package type in both:

- `Student\SubmissionController`
- `FacultyWeekControl`

Student status displays `resolved` once a `Week5EconomicEvaluation` exists for the team/week.

## Tests Added

Added:

```text
tests/Feature/Economics/Week5EconomicEvaluationServiceTest.php
tests/Feature/Week5/Week5RuntimeIntegrationSmokeTest.php
```

Coverage:

- submitted Week 5 decisions create idempotent evaluations;
- drafts do not evaluate;
- cross-tenant faculty cannot evaluate another tenant's submission;
- evaluations are immutable;
- student sees Week 5 package content and not faculty solution artifacts;
- faculty executes Week 5 through `FacultyWeekControl`;
- `Week5EconomicEvaluation` matches golden fixture outputs;
- execution record reports Week 5 evaluation status counts;
- Week 5 does not create KPI snapshots, ranking snapshots, consequence links, or cohort effects;
- student sees resolved status after execution.

Focused result:

```text
5 tests / 70 assertions
```

## Deferred

Still deferred:

- Week 5 KPI/ranking integration;
- Week 5 consequences;
- Week 5 to Week 10 assembler handoff;
- standing changes;
- what-if;
- LLM interpretation;
- UI redesign.
