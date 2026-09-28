# Batch 30A Implementation

## Scope

Batch 30A adds a faculty operations dashboard at `/faculty/dashboard`.

The dashboard is a read-only operations surface over existing platform records. It does not introduce new week mechanics, economic formulas, KPI mappings, standing changes, consequence links, what-if logic, or LLM behavior.

## Architecture

```text
Faculty Dashboard Route
        |
        v
FacultyOperationsDashboard Livewire Component
        |
        +--> Authorized SectionSimulation query
        +--> SimulationContentResolver
        +--> SubmissionCompletenessService
        +--> WeekExecutionRecord
        +--> Economic evaluation records
        +--> KpiSnapshot / RankingSnapshot
        +--> ConsequenceLink
```

The dashboard links into the existing deeper tools:

- Faculty week control
- Causal trace
- What-if console

## Faculty Workflow Supported

The page shows:

- course and section simulation selection;
- current week status;
- content package state;
- submission readiness;
- week timeline;
- execution steps;
- economic result counts;
- KPI/ranking availability;
- explicit consequence-mapping status.

When no authoritative consequence mapping exists, the dashboard says so directly instead of generating a narrative.

## Authorization

The component allows:

- administrators within the tenant;
- faculty assigned to the section.

Students are denied. Faculty section filtering uses the existing `section_faculty` assignment model.

## Result Review

Economic records are summarized from the existing immutable evaluation tables:

- Week 4 `EconomicResolution`
- Week 5 `Week5EconomicEvaluation`
- Week 6 `CapitalAllocationEvaluation`
- Week 8 `Week8EconomicEvaluation`
- Week 9 `Week9EconomicEvaluation`
- Week 10 `Week10EconomicEvaluation`
- Week 11 `Week11EconomicEvaluation`
- Week 12 `Week12EconomicEvaluation`
- Week 13 `Week13EconomicEvaluation`

KPI and ranking summaries use existing snapshots. Unavailable or incomplete measurement states remain visible and are not converted into fake scores.

## Deferred

Deferred intentionally:

- new economic engines;
- KPI/ranking mappings not already implemented;
- standing changes;
- consequence creation;
- graph visualization;
- teaching moments;
- automatic AI summaries;
- Week 14 board workflow.

## Tests

`FacultyOperationsDashboardTest` covers:

- student denial;
- assigned faculty scope;
- administrator tenant access;
- dashboard sections;
- timeline state;
- result summaries;
- no raw submission-answer leakage.

## Verification Notes

Focused and full verification should use the same platform commands as Batch 29A. In this shell, `php` was not available on `PATH`, so command execution depends on locating the local PHP/Herd binary or running from an environment where PHP is configured.
