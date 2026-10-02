# Batch 19B - Week 9 Runtime Integration

Batch 19B integrates the existing Week 9 Cordell rebrand economic engine into the week execution pipeline. It does not add Week 9 KPI/ranking, standing, consequences, cohort response, what-if, or LLM behavior.

## Architecture

```text
DecisionSubmission
        |
        v
Week9EconomicEvaluationService
        |
        v
Week9ReferencePackage
        |
        v
Week9EconomicEngine
        |
        v
Week9EconomicEvaluation
```

`WeekExecutionService` now recognizes Week 9 during `resolve_decisions` and evaluates submitted Week 9 decisions through `Week9EconomicEvaluationService`.

## Submission Mapping

The runtime decision mapping supports the existing generic decision framework:

```text
rebrand_LA_MS_core       boolean
rebrand_gulf_secondary   boolean
rebrand_southeast_edge   boolean
nonfuel_state_key        optional string, default base
```

The selected rebrand markets are passed to the package-backed engine. The non-fuel state remains an explicit runtime context input and defaults to `base` when not supplied.

## Persistence

`week9_economic_evaluations` stores immutable evaluation records with:

- tenant, section simulation, runtime week, team, and decision submission references;
- engine identifier and version;
- package version;
- selected non-fuel state;
- selected rebrand markets;
- cost per site;
- partial gain, cost, and payback;
- full net gain;
- price-war partial payback;
- input and output snapshots;
- actor/process metadata and timestamp.

Uniqueness is enforced on:

```text
tenant_id + decision_submission_id + engine_identifier
```

Repeated evaluation returns the existing record rather than creating a duplicate.

## Content Package

Week 9 uses the generic authoritative package type:

```text
authoritative_week9_reference_package
```

Student/faculty package lookup and faculty execution content validation now recognize that package type for Week 9.

## Deferred

Still deferred:

- Week 9 KPI/ranking integration;
- Week 9 standing changes;
- Week 9 consequence links;
- Week 7 to Week 9 cohort response production application;
- Week 9 what-if;
- LLM interpretation.

The Week 7 to Week 9 cohort window remains separate from the Week 9 Cordell rebrand economics.

## Verification

Added focused tests:

- `tests/Feature/Economics/Week9EconomicEvaluationServiceTest.php`
- `tests/Feature/Week9/Week9RuntimeIntegrationSmokeTest.php`

The tests cover idempotency, draft rejection, tenant isolation, student submission, faculty execution, persisted Week 9 evaluation, package/version provenance, resolved student status, and absence of downstream KPI/ranking/consequence/cohort mutation.
