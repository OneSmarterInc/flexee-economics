# Batch 24B Implementation

## Purpose

Batch 24B integrates the package-backed Week 11 Kessana economic engine into the runtime execution pipeline. It does not add Week 11 KPI/ranking, standing, consequences, what-if, UI, or LLM behavior.

## Runtime Architecture

```text
Week 11 DecisionSubmission
        |
        v
WeekExecutionService
        |
        v
Week11EconomicEvaluationService
        |
        v
Week11ReferencePackage
        |
        v
Week11EconomicEngine
        |
        v
Week11EconomicEvaluation
```

The economic engine remains package-backed and deterministic. `WeekExecutionService` only dispatches submitted Week 11 decisions to the evaluation service; it does not contain Week 11 formulas.

## Persistence Model

`week11_economic_evaluations` stores one immutable evaluation per tenant, submitted decision, and engine identifier.

The record preserves:

- tenant, section simulation, runtime week, team, and submitted decision;
- engine identifier/version;
- package version;
- calculated fiscal outputs;
- take-grid result snapshots;
- worked-example snapshot;
- full input and output snapshots;
- unavailable package reason when the authoritative package is absent;
- actor/process/timestamp metadata.

The unique key is:

```text
tenant_id + decision_submission_id + engine_identifier
```

Rerunning Week 11 evaluation for the same submission returns the existing record instead of creating a competing historical result.

## Stored Outputs

The evaluation persists the package-supported Week 11 outputs:

- realized Kessana price;
- profit oil;
- annual production in MMbbl;
- annuity factor;
- current/mid/demanded/harsh company margins;
- current/mid/demanded/harsh PV-of-stay values;
- exit value;
- stay-minus-exit under demanded take;
- indifference government take;
- comparable fiscal-term range;
- demanded take;
- boolean ordering/invariance assertions.

All parity-sensitive numeric values use decimal-safe `BigDecimal` formatting before persistence.

## Execution Integration

Week 11 is now handled by `WeekExecutionService::resolveDecisions()` through `Week11EconomicEvaluationService`.

The existing faculty week-control page displays the normal execution step status. The student submission page now reports Week 11 as `resolved` once a `Week11EconomicEvaluation` exists for the team/week.

## Deferred Functionality

Still deferred:

- Week 11 KPI/ranking population;
- standing changes;
- consequence links;
- cohort feedback effects;
- Week 11 what-if;
- Week 11-specific UI;
- LLM interpretation.

## Verification

Batch 24B adds focused service tests and a golden runtime smoke test covering:

- submitted decision evaluation;
- idempotency;
- missing package handling;
- draft rejection;
- tenant isolation;
- student workflow to faculty execution;
- golden output persistence;
- no downstream KPI/ranking/standing/consequence/cohort side effects.
