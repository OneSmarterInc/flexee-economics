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
