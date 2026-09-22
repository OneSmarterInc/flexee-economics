# Batch 3 Implementation - Submission Framework

Batch 3 adds a generic student decision and memo submission framework for simulation runtime weeks. It intentionally does not implement Week 4 economics, scoring, KPIs, ranking, Python execution, LLM workflows, or course-specific rubrics.

## Domain Model Selected

Reusable simulation content now includes versioned submission definitions:

- `decision_form_definitions`
- `decision_field_definitions`
- `memo_definitions`

Tenant-owned runtime participation now includes current submission snapshots plus immutable revision history:

- `decision_submissions`
- `decision_submission_revisions`
- `memo_submissions`
- `memo_submission_revisions`

Definitions belong to a `SimulationWeek` and `SimulationVersion`. Submissions belong to one tenant, section simulation, runtime week, team simulation, and definition.

## Decision Forms

Decision forms are field-driven rather than Week 4-specific. Supported field types are:

- integer
- decimal
- percentage
- currency
- select
- radio
- boolean
- short text

Validation is declarative through definition metadata such as required fields, numeric bounds, max length, and allowed options. Draft saves validate provided values but allow missing required fields. Final submits require all required fields.

## Memo Submissions

Memo definitions support:

- title
- instructions
- required flag
- word limit
- character limit
- rubric reference
- submission format
- metadata

Memo drafts may be empty. Final memo submissions require body text when the definition is required and enforce configured word and character limits.

## Submission Lifecycle

Students can write only when:

- the runtime week is `open`
- the runtime week deadline has not passed
- the actor belongs to the runtime team
- the runtime week, team simulation, definition, and actor are in the same tenant context

Current submission rows hold the latest draft or final state. Every draft save and final submit creates an immutable revision row. Once a current submission is final, the service rejects further writes and the model blocks direct material updates.

## Completeness

`SubmissionCompletenessService` reports per-team decision and memo status for a runtime week. A team is complete and ready for future evaluation only when all required submission pieces for that week are submitted.

This is intentionally a structural readiness flag. It does not score, rank, evaluate economics, or trigger downstream artifact generation.

## UI and Routes

Student submission UI:

- `GET /submissions/weeks/{sectionSimulationWeek}`
- `POST /submissions/weeks/{sectionSimulationWeek}/decisions/draft`
- `POST /submissions/weeks/{sectionSimulationWeek}/decisions/submit`
- `POST /submissions/weeks/{sectionSimulationWeek}/memo/draft`
- `POST /submissions/weeks/{sectionSimulationWeek}/memo/submit`

The student dashboard links visible runtime weeks to the submission page. The page renders generic decision fields and memo text from definitions.

Faculty lifecycle UI now includes per-team submission status for runtime weeks.

## Seed Data

`DatabaseSeeder` adds development-only generic Week 1 demo definitions:

- one decision form with quantity and percentage fields
- one memo definition with a character limit

The seeded Week 1 runtime week is opened so local development can exercise draft and final submission flows.

## Tests

Batch 3 adds coverage for:

- unknown decision keys rejected
- required final decision values enforced
- generic decision draft and final submission
- memo draft, final submission, and length validation
- immutable revision creation
- final current submission immutability
- closed and deadline-past week write rejection
- wrong team, tenant, and definition rejection
- direct route wrong-definition rejection
- student and cross-tenant UI authorization
- faculty lifecycle submission status display
- completeness requiring required decision and memo pieces

## Verification Results

Verified on September 22, 2026 with PHP at `C:\Users\sakas\.config\herd-lite\bin\php.exe`.

Automated verification:

- `php artisan migrate:fresh --seed` passed.
- `php artisan test tests/Feature/Submissions` passed: 17 tests, 43 assertions.
- `php artisan test` passed: 100 tests, 256 assertions.
- `composer validate` passed.
- `composer run lint:check` passed.
- `composer run types:check` passed with 0 PHPStan errors.
- `npm run check` passed.
- `npm run types:check` passed.
- `npm run build` passed; Wayfinder generated route/action types successfully.

Browser smoke verification:

- Student login (`student11@example.test`) loaded the dashboard and linked opened Week 1 to the submission page.
- Student submitted generic decision values and memo text; the page showed `Decisions: submitted`, `Memo: submitted`, `Complete`, and disabled the edit controls.
- Faculty login (`faculty@example.test`) loaded `/simulation-lifecycle` and showed Team Alpha as `Decisions: submitted - Memo: submitted - complete`.
- Browser checks reported page content present, no Vite/framework error overlay, and no captured console errors.

Defects found and repaired during verification:

- Added composite unique keys for tenant-scoped current submission foreign keys so SQLite and production databases can enforce revision references.
- Fixed typed JSON handling and Eloquent relation generics so PHPStan verifies the submission framework.
- Added direct model-level protection against mutating final current submission snapshots.
- Fixed an existing sidebar SSR hydration mismatch that surfaced on the new Inertia submission page.
