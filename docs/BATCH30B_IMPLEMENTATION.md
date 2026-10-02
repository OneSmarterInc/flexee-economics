# Batch 30B Implementation

## Scope

Batch 30B adds the student multi-week journey experience at `/student/dashboard`.

This batch is product-facing only. It does not add economic logic, evaluation logic, KPI calculations, consequence rules, LLM behavior, or Week 14 workflow.

## Student Workflow

```text
Student Dashboard
        |
        v
Current Week
        |
        v
Authorized Content
        |
        v
Decision / Memo Status
        |
        v
Own History
        |
        v
Released Own Results
```

## Routes and Components

Added:

- `GET /student/dashboard`
- Route name: `student.dashboard`
- Controller: `App\Http\Controllers\Student\DashboardController`
- Inertia page: `resources/js/pages/Student/Dashboard.vue`

The existing submission workspace remains the place where students complete decisions, capital allocation choices, and memos.

## Reused Services

The dashboard reuses:

- `SimulationContentResolver` for authorized student artifacts;
- `SubmissionCompletenessService` for decision, memo, and capital-allocation readiness;
- existing runtime week lifecycle state;
- existing immutable economic evaluation records;
- existing KPI snapshots for student-safe availability counts.

## Security Model

Students can see:

- their own course, section, team, and simulation;
- currently visible runtime weeks;
- student/shared content artifacts only;
- their own decision and memo statuses;
- their own resolved-result availability;
- their own KPI availability counts.

Students cannot see:

- faculty solution materials;
- golden fixtures;
- faculty notebooks;
- other teams or peer submissions;
- faculty dashboard;
- causal trace;
- what-if console;
- faculty interpretation data.

## Timeline Behavior

The timeline is derived from actual runtime week status and the team's own resolved state:

- `completed`: own team result exists;
- `active`: runtime week is open;
- `available`: released/closed/published but not resolved;
- `upcoming`: future scheduled/draft week after the current visible week;
- `locked`: unavailable week before or without a current visible context.

Unavailable future content is not resolved or shown.

## Mobile Considerations

The page uses stacked cards and responsive grids rather than wide tables. Current week status, timeline cards, content cards, and history cards collapse naturally on smaller screens.

## Tests

`StudentJourneyTest` covers:

- student dashboard journey payload;
- student-safe artifact filtering;
- own history visibility;
- peer history hiding;
- other-tenant runtime week denial;
- student denial from faculty dashboard, causal trace, and what-if tools.

## Deferred

Deferred intentionally:

- student coaching or LLM assistant;
- peer comparisons;
- faculty trace exposure;
- automatic grading;
- new result-publication rules;
- Week 14 board-defense workflow.
