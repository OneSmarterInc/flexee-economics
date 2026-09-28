# Batch 26C Implementation

Batch 26C integrates the package-backed Week 12 transition-portfolio economic engine into the runtime execution pipeline. It does not add KPI/ranking integration, standing changes, consequence links, Week 12-specific UI, what-if support, or LLM behavior.

## Runtime Architecture

```text
Week 12 DecisionSubmission
        ↓
WeekExecutionService
        ↓
Week12EconomicEvaluationService
        ↓
Week12ReferencePackage
        ↓
Week12EconomicEngine
        ↓
Week12EconomicEvaluation
```

`WeekExecutionService` recognizes Week 12 in the decision-resolution step and delegates submitted decision records to `Week12EconomicEvaluationService`. It does not contain Week 12 formulas.

## Evaluation Model

`week12_economic_evaluations` stores one immutable evaluation per tenant, decision submission, and engine identifier.

The model preserves:

- tenant;
- section simulation;
- runtime week;
- team simulation;
- team;
- source decision submission;
- engine identifier and version;
- package identifier and version;
- selected projects;
- rejected projects;
- available projects;
- selected portfolio feasibility;
- selected constraint failures;
- selected divestment state;
- selected capital requirement;
- selected available envelope;
- package-level feasibility counts;
- full portfolio result snapshots;
- worked-example snapshot;
- input and output snapshots;
- evaluator actor/process/timestamp.

The unique key is:

```text
tenant_id + decision_submission_id + engine_identifier
```

Repeated evaluation of the same Week 12 submission returns the existing record.

## Portfolio Context Storage

Week 12 runtime persistence intentionally stores the portfolio context, not only headline counts.

The evaluation record captures:

- what the team selected;
- what the team did not select;
- what alternatives were available from the package;
- whether the selected portfolio was feasible;
- which constraints failed, if any;
- whether divestment was selected;
- whether divestment unlocked feasibility.

Missing selected-project input is stored as an `invalid_submission` evaluation. The service does not substitute a default portfolio.

## Golden Smoke Test

`tests/Feature/Week12/Week12RuntimeIntegrationSmokeTest.php` verifies:

```text
student opens Week 12 workspace
        ↓
student submits portfolio decision and memo
        ↓
faculty executes week
        ↓
Week12EconomicEvaluation is created
        ↓
golden feasibility outputs are persisted
        ↓
student sees resolved status
```

The smoke test uses the package-backed portfolio:

```text
helix_rotterdam + offshore_wind + euro_retail_divest
```

and verifies the stored result:

- selected portfolio feasible;
- selected portfolio includes divestment;
- selected portfolio is unlocked by divestment;
- selected capital required: `$1,750M`;
- feasible portfolios: `17`;
- feasible with Helix Rotterdam: `3`;
- portfolios unlocked by divestment: `2`.

## Student And Faculty Visibility

The existing student submission page now reports Week 12 as resolved once `Week12EconomicEvaluation` exists for the team/week.

The existing faculty week-control page can execute Week 12 because the generic authoritative package resolver now includes Week 12.

No Week 12-specific UI was added.

## Idempotency

`Week12EconomicEvaluationService` locks on:

```text
tenant_id + decision_submission_id + engine_identifier
```

If a matching evaluation exists, the service returns it instead of creating a duplicate historical result.

## Deferred Functionality

Deferred to later batches:

- Week 12 KPI/ranking integration;
- standing changes;
- consequence links;
- Week 12-specific UI;
- Week 12 what-if support;
- LLM interpretation.

## Verification

Focused runtime tests:

```text
7 tests / 99 assertions
```

Focused engine tests remain:

```text
7 tests / 90 assertions
```
