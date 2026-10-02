# Stage 1 Architecture Proposal

## Source constraints

This historical proposal was based on the handoff and constants ledger then available. The authoritative markdown sources have since been supplied; the worked Week 4 reference package is still required before economics implementation.

The repository currently has no Laravel application files. The initial implementation should therefore begin with a fresh Laravel scaffold that follows the handoff:

- Student application: Laravel, Inertia, Vue.
- Faculty application: Laravel, Livewire.
- Laravel runtime: authentication, tenancy, courses, sections, teams, simulation state, decisions, memo submissions, scoring, KPI computation, publishing, ranking, faculty controls, and audit history.
- Python: offline generation only for synthetic paths, response functions, and simulation artifacts.
- LLM functionality: queued integration pattern; no synchronous LLM calls in the student critical submission path.

## Multi-tenancy

Use the explicit hierarchy confirmed by the development instructions:

`Tenant / Institution -> Course -> Section -> Team -> Student`

Recommended rule: every tenant-owned record must either carry `tenant_id` directly or have a short, enforced foreign-key path back to a record that carries `tenant_id`. Authorization should never rely only on frontend filtering.

Stage 1 should support one tenant and one section end-to-end, but the schema must support many tenants, courses, sections, and teams from the beginning.

## Application boundaries

| Boundary               | Responsibility                                                                                                    |
| ---------------------- | ----------------------------------------------------------------------------------------------------------------- |
| HTTP controllers       | Request validation, authorization, orchestration, response rendering                                              |
| Inertia/Vue student UI | Student dashboard, assigned seat content, Week 4 data package access, decision form, memo form, permitted results |
| Livewire faculty UI    | Section activation, roster/team oversight, submission monitoring, results review, publishing, rankings            |
| Domain layer           | Simulation lifecycle, Week 4 calculation, scoring, KPI computation, ranking                                       |
| Content/config layer   | Versioned week definitions, datasets, prompts, constants, rubric metadata                                         |
| Jobs                   | Non-critical asynchronous operations such as publication snapshots, queued LLM assistance, exports, notifications |
| Audit layer            | Immutable record of faculty controls, submissions, scoring runs, publications, and administrative changes         |

## Core domain entities

Use Laravel conventions and keep names close to the domain:

- `Tenant`
- `Institution`
- `User`
- `Course`
- `Section`
- `Enrollment`
- `Team`
- `Seat`
- `TeamMember`
- `Simulation`
- `SimulationVariant`
- `SimulationWeek`
- `WeekContentVersion`
- `SectionSimulation`
- `TeamSimulation`
- `DecisionSubmission`
- `MemoSubmission`
- `ScoringRun`
- `KpiResult`
- `Score`
- `RankingSnapshot`
- `Publication`
- `AuditEvent`
- `GeneratedArtifact`

Avoid separate `Faculty` and `Student` tables at first unless the development instructions require them. Prefer `users` plus scoped roles/enrollments because the same person can plausibly teach one course and participate in another.

## Economic engine

Create a deterministic domain layer under a namespace such as:

- `app/Domain/Simulation`
- `app/Domain/Simulation/Week4`
- `app/Domain/Scoring`
- `app/Domain/Ranking`

The Week 4 engine should accept:

- a versioned week definition,
- a versioned constants snapshot,
- the student/team decision payload,
- relevant section/team context,
- relevant generated artifacts.

It should return structured outputs:

- calculated KPIs,
- score components,
- ranking inputs,
- warnings or trace metadata,
- source/version identifiers.

Controllers and UI components must not contain economic formulas.

## Scoring engine

Scoring must be:

- deterministic: same versioned inputs produce same outputs;
- testable: pure calculation classes are unit-tested without HTTP;
- versioned: each scoring run records content version, constants version, and engine version;
- reproducible: historical submissions keep original inputs and calculation metadata;
- auditable: every scoring run, override, publication, and recalculation is logged.

A historical result must be traceable to:

- tenant, course, section, team;
- simulation, variant, week, content version;
- constants ledger version or hash;
- generated artifact version or hash;
- decision submission payload;
- memo submission;
- KPI outputs;
- score outputs;
- ranking snapshot and publication state.

## Content architecture

Do not scatter economic/content configuration through PHP source. Use versioned content packages, for example:

- `database/seeders/content/halden/...` for structured seed data;
- `storage/app/simulation-content/halden/...` or `resources/simulation-content/halden/...` for package files;
- database records for content versions, artifact hashes, and activation state.

A week definition should include:

- week number and slug;
- variant applicability;
- decision schema;
- validation rules;
- dataset references;
- role/seat content references;
- memo prompt metadata;
- scoring engine identifier;
- constants snapshot reference;
- expected precision and rounding policy;
- reference fixture paths for tests.

For Stage 1, Week 4 content should be loaded as a versioned package once the missing worked data package is available.

## Python/Laravel boundary

Python is not the application runtime.

Python should produce deterministic artifacts before runtime, such as:

- synthetic price paths;
- response functions;
- reference datasets;
- expected-output fixtures;
- generated package metadata.

Recommended flow:

1. Python generation runs offline under a controlled command or pipeline.
2. Outputs are written to package directories with manifests and hashes.
3. Laravel imports or references those immutable artifacts.
4. Runtime scoring reads the artifact version assigned to the section simulation.
5. Re-generation creates a new artifact version; it does not mutate historical runs.

## Queue architecture

Deterministic scoring should run synchronously at close time or in a Laravel queue job, but never depend on an external AI service.

Candidate jobs:

- `CloseSectionWeek`
- `ScoreTeamSubmission`
- `BuildRankingSnapshot`
- `PublishWeekResults`
- `GenerateFacultyExport`
- `RunQueuedInterpretiveAssistant`
- `SendPublicationNotification`

Queued jobs must record attempts, failures, and inputs. Jobs that affect visible results should be idempotent.

## Authorization

Policy boundaries:

- Student: can view own tenant/course/section/team, assigned seat content, open week package, own/team submission state, and published results permitted for their section.
- Team member: can collaborate on the team submission only while the week is open and only for the assigned team.
- Faculty: can manage assigned courses/sections, activate weeks, review submissions, run/publish results, and view faculty-only traces for scoped sections.
- Administrator: can manage tenants, institutions, global simulation content, and user/course assignment.

Required isolation:

- no cross-tenant reads or writes;
- no cross-section student access;
- no access to unpublished rankings by students;
- no faculty action outside assigned scope unless administrator privileges allow it.

## Stage 1 milestone

Stage 1 is complete when Week 4 runs end-to-end for one section:

- students authenticate;
- students belong to tenant/course/section/team/seat;
- faculty activates Week 4;
- students access Week 4 data and role content;
- teams submit decisions and memos;
- submissions close;
- deterministic calculations store KPIs and scores;
- rankings are generated;
- faculty reviews and publishes;
- students see permitted published results.
