# Batch 2 Implementation - Simulation Domain and Lifecycle

Batch 2 adds the structural simulation domain for Halden Energy without implementing submissions, scoring, Week 4 economics, KPIs, ranking, Python execution, or LLM workflows.

## Domain Model Selected

Simulation definitions are platform-global reusable content:

- `simulations`
- `simulation_variants`
- `simulation_versions`
- `simulation_weeks`
- `week_content_versions`
- `generated_artifacts`

Runtime simulation participation is tenant-owned:

- `section_simulations`
- `section_simulation_weeks`
- `team_simulations`
- `simulation_seat_assignments`
- `audit_events`

The hierarchy is:

`Simulation -> SimulationVariant -> SimulationVersion -> SimulationWeek -> SectionSimulation -> SectionSimulationWeek`

This keeps Halden content reusable across tenants while section assignment, teams, lifecycle state, audit history, and student visibility remain tenant-isolated.

## Lifecycle

`SimulationLifecycleService` owns assignment and week transitions.

Allowed runtime week transitions:

- `draft -> scheduled`
- `draft -> released`
- `scheduled -> released`
- `released -> open`
- `open -> closed`
- `closed -> published`

Invalid transitions such as `draft -> published` and `published -> open` throw `InvalidArgumentException`.

## Assignment Behavior

Assigning a published `SimulationVersion` to a section creates:

- one `section_simulations` row
- one `section_simulation_weeks` row for each definition week
- one `team_simulations` row for each team in the section
- one `simulation_seat_assignments` row for each team member with a Halden seat
- an `audit_events` row for the assignment

Only published simulation versions can be assigned. Published or in-use versions cannot be materially modified.

## Authorization

`SectionSimulationPolicy` and `SectionSimulationWeekPolicy` extend the Batch 1 tenant model:

- administrators can view/manage tenant runtime simulations
- assigned faculty can view/manage their section simulations
- students can view only their enrolled section simulations
- students can view only runtime weeks in `released`, `open`, `closed`, or `published`
- students cannot transition lifecycle state

## UI and Routes

Faculty/admin lifecycle UI:

- `/simulation-lifecycle`

JSON routes for direct access and tests:

- `GET /foundation/section-simulations/{sectionSimulation}`
- `GET /foundation/section-simulation-weeks/{sectionSimulationWeek}`
- `POST /foundation/section-simulation-weeks/{sectionSimulationWeek}/transition`

Student dashboard now lists available simulation weeks, filtered to released-or-later lifecycle states.

## Seed Data

`DatabaseSeeder` now creates a structural demo:

- Simulation: `Halden Energy`
- Variant: `Fourteen-week flagship`
- Version: `2026-demo`
- 14 placeholder week definitions
- Section A demo assignment
- Week 1 released for student visibility

Seeded configuration deliberately records `economics: not-included`.

## Tests

Batch 2 adds coverage for:

- definition hierarchy and duplicate week constraints
- published/in-use version immutability
- assignment expansion into runtime weeks, teams, seats, and audit
- valid and invalid lifecycle transitions
- faculty/student/cross-tenant authorization
- student dashboard visibility filtering
- runtime seat assignment independence from platform role

## Verification Results

Verified on September 22, 2026 with PHP at `C:\Users\sakas\.config\herd-lite\bin\php.exe`.

Migration and seed:

- `php artisan migrate:fresh --seed` passed from an empty database.
- Batch 1 and Batch 2 migrations executed in order.
- Seeder created the Halden University demo, Halden Energy simulation, `Fourteen-week flagship` variant, `2026-demo` version, 14 structural weeks, Section A assignment, and Week 1 released state.

PHP tests and quality gates:

- `php artisan test` passed: 83 tests, 83 passed, 213 assertions.
- `composer validate` passed.
- `composer run lint:check` passed.
- `composer run types:check` passed with 0 PHPStan errors.

Frontend checks:

- `npm run check` passed.
- `npm run types:check` passed.
- `npm run build` passed with Herd-lite PHP on `PATH`; Wayfinder generated route/action types successfully.

State-machine verification:

- Actual runtime week states are `draft`, `scheduled`, `released`, `open`, `closed`, and `published`.
- Valid transitions are `draft -> scheduled`, `draft -> released`, `scheduled -> released`, `released -> open`, `open -> closed`, and `closed -> published`.
- Tests verify the allowed release/open/close/publish path, the scheduled/released path, and forbidden shortcuts including `draft -> published` and `published -> open`.
- Controllers and Livewire components call `SimulationLifecycleService`; repository search found no direct runtime-week lifecycle mutation bypassing the service outside seed/test setup.

Version immutability verification:

- Draft versions can be materially edited before use.
- Published or in-use versions reject material edits server-side.
- Week definitions for published or in-use versions reject material edits server-side.
- Section simulations reject retargeting to another section, simulation, variant, or version after assignment.

Tenant-isolation verification:

- Cross-tenant lifecycle actors are rejected.
- Tenant A section simulation with Tenant B section context is rejected.
- Tenant A section simulation with Tenant B team context is rejected.
- Section Simulation using Version A with runtime week definition from Version B is rejected.
- Student/team/simulation context from different tenants is rejected.
- Student from another section cannot be assigned into the Section A simulation team.
- Students cannot access lifecycle controls or mutate runtime weeks by direct ULID route.
- Students can see only released-or-later runtime weeks on the dashboard and cannot directly view unreleased weeks.

Smoke test:

- Faculty login (`faculty@example.test`) loaded `/simulation-lifecycle`, displayed Section A, `2026-demo`, and Week 1 lifecycle state, and an authorized Week 1 transition worked.
- Student login (`student11@example.test`) displayed Halden Energy for Section A and only permitted weeks on the dashboard.
- Student direct navigation to `/simulation-lifecycle` returned 403.

Defects found and repaired during verification:

- Fixed enum-cast handling in simulation version immutability checks.
- Added frozen-version protection for simulation week definitions.
- Added section-simulation retargeting protection.
- Added explicit enum status helpers so PHPStan verifies lifecycle status handling.
- Fixed `/simulation-lifecycle` Blade shell to avoid loading the Inertia app bundle on the standalone Livewire page.
