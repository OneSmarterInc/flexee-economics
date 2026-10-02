# Developer Handoff

Current stable baseline:

```text
d95afdc897a850d42fc9d23aef7c1fe21c132755
test: validate final product readiness
```

This repository contains the Halden Energy managerial economics teaching simulation platform. It is a Laravel 13 application with Inertia/Vue student screens, Livewire faculty tools, package-backed economic engines, multi-week runtime execution, and a Week 14 board-defense assessment workflow.

## Current Status

The project is at the final platform readiness checkpoint.

Validated in Batch 32A:

- clean database rebuild: passed;
- demo health and reset: passed;
- focused product acceptance: `25 tests / 682 assertions`;
- full Laravel suite: `416 tests / 2,681 assertions`;
- PHPStan: `0 errors`;
- frontend check/types/build: passed;
- working tree: clean at checkpoint.

Primary readiness docs:

- `docs/RELEASE_READINESS.md`
- `docs/BATCH32A_IMPLEMENTATION.md`
- `docs/DEMO_RUNBOOK.md`

## Technology Stack

Backend:

- PHP `8.3+`; verified with PHP `8.4.0`
- Laravel `13.x`
- Fortify/passkeys authentication
- Livewire `4.x` for faculty tools
- PostgreSQL expected for local development, though tests use Laravel's testing database configuration

Frontend:

- Vue `3`
- Inertia
- Vite Plus / Vite
- Tailwind CSS
- TypeScript

Tooling:

- Composer
- npm
- Pint
- PHPStan / Larastan
- PHPUnit

Verified local tooling at Batch 32A:

```text
PHP      8.4.0
Composer 2.8.12
Node     v20.19.5
npm      10.8.2
```

## Repository Map

Core backend:

```text
app/
  Domain/          Business/domain services
  Http/            Controllers and request-facing HTTP logic
  Livewire/        Faculty and operational Livewire components
  Models/          Eloquent models
  Policies/        Authorization policies
  Enums/           Domain enums
```

Frontend:

```text
resources/js/
  pages/           Inertia/Vue pages
  components/      Shared Vue components
  layouts/         App layouts
resources/css/     Frontend styling
```

Routing:

```text
routes/web.php      Web routes
routes/console.php  Artisan demo commands
routes/settings.php Settings routes
```

Database:

```text
database/migrations/
database/seeders/
database/factories/
```

Tests:

```text
tests/Feature/
tests/Unit/
```

Authoritative simulation packages:

```text
halden-week1-data-package/
halden-week2-data-package/
halden-week3-data-package/
halden-week4-data-package/
halden-week5-data-package/
halden-week6-data-package/
halden-week7-data-package/
halden-week8-data-package/
halden-week9-data-package/
halden-week10-data-package/
halden-week11-data-package/
halden-week12-data-package/
halden-week13-data-package/
```

Week 14 intentionally has no data package. It is an assessment workflow, not an economic engine.

## Domain Directory Guide

Important `app/Domain` areas:

```text
Advisors/        Advisor catalog, consultation records, decision-support history
Assessment/     Week 14 board-defense submission and faculty assessment
Capital/        Week 6 capital allocation domain objects
CausalTrace/    Faculty causal trace service and typed trace nodes
CohortFeedback/ Cohort aggregation and future-effect framework
Consequences/   ConsequenceLink framework and resolvers
Content/        Package registration, activation, resolver, authoritative package manifests
Demo/           Demo health check support
Economics/      Week-specific economic engines and evaluation services
Execution/      WeekExecutionService and execution orchestration
Interpretation/ Interpretive assistant service/provider boundary
Ranking/        Ranking snapshot calculation
Scoring/        KPI calculation and week-specific KPI population
Simulation/     Section/week lifecycle services
Standing/       Counterparty standing state and event history
Submissions/    Decision/memo submission services
WhatIf/         Faculty what-if foundation
```

## Product Capabilities

Student workflow:

```text
Student dashboard
  -> current week
  -> content package materials
  -> decision submission
  -> memo submission
  -> own history/results
  -> Week 14 board defense submission
  -> published assessment feedback
```

Faculty workflow:

```text
Faculty dashboard
  -> section/week selection
  -> readiness and submission status
  -> week execution
  -> result review
  -> causal trace
  -> what-if console
  -> Week 14 board-defense assessment
  -> feedback publication
```

Simulation runtime:

```text
Content package
  -> student submission
  -> WeekExecutionService
  -> week-specific economic evaluation
  -> KPI/ranking where supported
  -> historical state
  -> faculty analysis
```

## Implemented Weeks

Computational/runtime paths:

| Week | Status                                              | Notes                                                  |
| ---- | --------------------------------------------------- | ------------------------------------------------------ |
| 4    | Package, engine, runtime, KPI/ranking, consequences | Transfer pricing golden vertical slice                 |
| 5    | Package, engine, runtime                            | Currency/FX/hedging; feeds Week 10 historical state    |
| 6    | Package, engine, runtime                            | Capital allocation, NPV/IRR                            |
| 8    | Package, engine, runtime, supported KPI/ranking     | OPEC/scenario economics                                |
| 9    | Package, engine, runtime                            | Market/retail economics                                |
| 10   | Package, engine, runtime, KPI/ranking boundary      | Convergence from persisted Week 4/5/6/8/standing state |
| 11   | Package, engine, runtime, KPI/ranking boundary      | Kessana fiscal decision                                |
| 12   | Package, engine, runtime                            | Transition portfolio and Helix/divestment mechanics    |
| 13   | Package, engine, runtime                            | Factor markets                                         |
| 14   | Assessment workflow                                 | Board defense; no economic engine                      |

Weeks 1, 2, 3, and 7 have authoritative packages registered as content packages but are not currently implemented as economic runtime engines in the same way as Weeks 4-13 above.

## Local Setup

From the repository root:

```powershell
cd C:\Users\sakas\Documents\ChatGPT\Flexee-economics
```

Install dependencies:

```powershell
composer install
npm install
```

Create environment:

```powershell
copy .env.example .env
php artisan key:generate
```

Configure `.env` for the local database. Do not commit `.env`.

Run migrations and seeders:

```powershell
php artisan migrate --seed
```

For a fully clean local rebuild:

```powershell
php artisan migrate:fresh --seed
```

If PHP is not on `PATH`, use the verified Herd binary path from this workstation:

```powershell
& "C:\Users\sakas\.config\herd-lite\bin\php.exe" artisan migrate:fresh --seed
```

## Run The Project Locally

Terminal 1, backend:

```powershell
php artisan serve --host=127.0.0.1 --port=8000
```

Terminal 2, frontend:

```powershell
npm run dev
```

Open:

```text
http://127.0.0.1:8000
```

Use the Laravel URL for the app. The Vite URL `http://localhost:5173` is the asset dev server and may return `404` if opened directly.

## Demo Accounts

All seeded demo accounts use:

```text
password
```

Accounts:

```text
admin@example.test
faculty@example.test
student11@example.test
```

The repeatable demo baseline is:

```text
Halden University Demo
Managerial Economics
Section A Demo Halden Energy
Team Alpha
Week 4 open with validated package
```

## Key URLs

General:

```text
/login
/dashboard
```

Student:

```text
/student/dashboard
/submissions/weeks/{sectionSimulationWeek}
/student/week14/weeks/{sectionSimulationWeek}/defense
```

Faculty:

```text
/faculty/dashboard
/faculty/week-control
/faculty/causal-trace
/faculty/what-if
/faculty/week14/weeks/{sectionSimulationWeek}/teams/{teamSimulation}/assessment
```

## Demo Commands

Health check:

```powershell
php artisan halden:demo-health
```

Reset demo tenant and Week 4 baseline:

```powershell
php artisan halden:demo-reset
```

Full demo reset with database rebuild:

```powershell
php artisan halden:demo-reset --fresh
```

`halden:demo-health` verifies:

- demo tenant exists;
- demo faculty/student exist;
- Section A demo simulation exists;
- Week 4 runtime is open;
- Week 4 package and activation are valid;
- Week 4 decision/memo definitions exist;
- execution/KPI/interpretation services resolve.

## Verification Before Deployment

Run this from the repository root:

```powershell
php artisan migrate:fresh --seed
php artisan halden:demo-health
php artisan halden:demo-reset
php artisan halden:demo-health
php artisan test
composer validate
composer run lint:check
composer run types:check
npm run check
npm run types:check
npm run build
git diff --check
git status --short
```

Expected:

- all commands pass;
- PHPStan reports `0 errors`;
- `git status --short` is empty.

Focused product acceptance command:

```powershell
php artisan test `
  tests/Feature/Demo/DemoOperationsTest.php `
  tests/Feature/Student/StudentJourneyTest.php `
  tests/Feature/Faculty/FacultyOperationsDashboardTest.php `
  tests/Feature/Assessment/Week14AssessmentWorkflowTest.php `
  tests/Feature/Release/FinalProductReadinessTest.php `
  tests/Feature/Week4/Week4RuntimeActivationSmokeTest.php `
  tests/Feature/Week5/Week5RuntimeIntegrationSmokeTest.php `
  tests/Feature/Week6/Week6RuntimeActivationSmokeTest.php `
  tests/Feature/Week8/Week8RuntimeIntegrationSmokeTest.php `
  tests/Feature/Week9/Week9RuntimeIntegrationSmokeTest.php `
  tests/Feature/Week10/Week10RuntimeIntegrationSmokeTest.php `
  tests/Feature/Week11/Week11RuntimeIntegrationSmokeTest.php `
  tests/Feature/Week12/Week12RuntimeIntegrationSmokeTest.php `
  tests/Feature/Week13/Week13RuntimeIntegrationSmokeTest.php
```

Batch 32A result for that focused command:

```text
25 tests / 682 assertions
```

## Important Tests

Release readiness:

```text
tests/Feature/Release/FinalProductReadinessTest.php
```

Demo:

```text
tests/Feature/Demo/DemoOperationsTest.php
```

Student:

```text
tests/Feature/Student/StudentJourneyTest.php
```

Faculty:

```text
tests/Feature/Faculty/FacultyOperationsDashboardTest.php
tests/Feature/Faculty/FacultyCausalTraceUiTest.php
tests/Feature/Faculty/FacultyWhatIfConsoleTest.php
```

Week 14:

```text
tests/Feature/Assessment/Week14AssessmentWorkflowTest.php
```

Runtime smoke:

```text
tests/Feature/Week4/Week4RuntimeActivationSmokeTest.php
tests/Feature/Week5/Week5RuntimeIntegrationSmokeTest.php
tests/Feature/Week6/Week6RuntimeActivationSmokeTest.php
tests/Feature/Week8/Week8RuntimeIntegrationSmokeTest.php
tests/Feature/Week9/Week9RuntimeIntegrationSmokeTest.php
tests/Feature/Week10/Week10RuntimeIntegrationSmokeTest.php
tests/Feature/Week11/Week11RuntimeIntegrationSmokeTest.php
tests/Feature/Week12/Week12RuntimeIntegrationSmokeTest.php
tests/Feature/Week13/Week13RuntimeIntegrationSmokeTest.php
```

## Architecture Guardrails

Preserve these rules when extending the platform:

- Do not invent economics. Implement week mechanics only from authoritative package artifacts and golden fixtures.
- Keep economic engines pure and package-backed.
- Use persisted runtime evaluations as historical state. Do not let raw submissions satisfy future dependencies.
- Keep KPI/ranking values unavailable/null when no authoritative mapping exists.
- Create consequence links only when an authoritative consequence rule exists.
- Keep Week 14 assessment human-controlled. Do not derive grades from leaderboard rank.
- Keep student/faculty/package visibility boundaries strict.
- Keep faculty tools on Livewire unless there is a deliberate architecture change.
- Keep student-facing experiences on Inertia/Vue.
- Do not modify raw authoritative package artifacts unless the source package itself is replaced and revalidated.

## Known Limitations And Deferred Work

These are expected, documented limitations, not current defects:

- Some weeks have economic outputs but no authoritative seven-KPI mapping.
- Consequence mappings exist only where evidence-backed.
- Week 6 to Week 8 cohort response is implemented from `halden-window2-cohort-addendum/`; Week 7 compounding and the future market-data anchor refresh remain deferred.
- Week 14 does not calculate automatic grades, points, weights, or ranking-derived scores.
- Real LLM provider integration is deferred. The assistant boundary exists, but LLM output is not a grading authority.
- Production deployment, backups, monitoring, and support runbooks still need to be finalized.

## Deployment Preparation Notes

Before production deployment, create a deployment-specific checklist covering:

- production `.env` values;
- database host, user, password, database name, and SSL policy;
- app key management;
- queue worker configuration;
- scheduler configuration;
- storage permissions and backups;
- log retention;
- HTTPS/domain setup;
- mail provider setup if notifications are enabled;
- user provisioning policy;
- package artifact storage and read permissions;
- backup/restore rehearsal;
- rollback process.

Recommended next batch:

```text
Batch 33A - Production Deployment and Teaching Runbook
```

That batch should focus on operations, not new simulation mechanics.

## Quick Manual Smoke

1. Start backend and frontend.
2. Open `http://127.0.0.1:8000`.
3. Log in as `student11@example.test / password`.
4. Open `/student/dashboard`.
5. Open Week 4.
6. Confirm student-visible Week 4 content, transfer-price input, and memo controls render.
7. Log out or use another browser session.
8. Log in as `faculty@example.test / password`.
9. Open `/faculty/dashboard`.
10. Open `/faculty/week-control`.
11. Select Section A and Week 4.
12. Execute or inspect execution state depending on whether the demo has already been run.

## Useful Documentation

Start with:

- `docs/RELEASE_READINESS.md`
- `docs/DEMO_RUNBOOK.md`
- `docs/BATCH32A_IMPLEMENTATION.md`

For package/source history:

- `docs/AUTHORITATIVE_PACKAGE_BATCH_SOURCE_NOTE.md`
- `docs/AUTHORITATIVE_PACKAGE_BATCH_INVENTORY.md`
- `docs/HALDEN_SOURCE_INVENTORY.md`

For Week 14:

- `docs/WEEK14_SOURCE_INVENTORY.md`
- `docs/BATCH31A_IMPLEMENTATION.md`
- `docs/BATCH31B_IMPLEMENTATION.md`

For unresolved questions:

- `docs/OPEN_QUESTIONS.md`
