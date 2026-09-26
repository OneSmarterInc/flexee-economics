# Batch 21A - Multi-Week Integration Regression

## Purpose

Batch 21A is a verification and hardening batch. It does not add new simulation mechanics.

The goal is to prove that Week 10 consumes persisted runtime history through:

```text
Week 4 / Week 5 / Week 6 / Week 8 / Standing
        |
        v
Week10InheritedStateAssembler
        |
        v
Week10ConvergenceEconomicEngine
        |
        v
Week10EconomicEvaluation
```

## Starting Point

The batch starts from:

```text
7443930114e9772a8fb72a8e4581f33a4ee26761
feat: integrate Week 10 runtime evaluation
```

That commit was not amended.

## PHP Environment Check

The requested checks were run:

```powershell
Get-Command php -ErrorAction SilentlyContinue
where.exe php
Test-Path "C:\Users\sakas\.config\herd-lite\bin\php.exe"
```

Result:

- sandboxed lookup could not access the Herd path;
- escalated lookup found `C:\Users\sakas\.config\herd-lite\bin\php.exe`;
- PHP version: `8.4.0`.

No PHP runtime was installed or substituted.

## Historical Dependency Map

Week 10 inherited state is assembled from persisted runtime records:

| Week 10 dependency          | Runtime source                                  | Record field / contract                                            |
| --------------------------- | ----------------------------------------------- | ------------------------------------------------------------------ |
| `br_reported_margin_strong` | Week 4 `EconomicResolution`                     | `output_snapshot.week10_inherited_state.br_reported_margin_strong` |
| `crude_hedge_coverage`      | Week 5 submitted `DecisionSubmission`           | `answers.week10_inherited_state.crude_hedge_coverage`              |
| `cancellable_capex_musd`    | Week 6 calculated `CapitalAllocationEvaluation` | `output_snapshot.week10_inherited_state.cancellable_capex_musd`    |
| `cash_cushion_musd`         | Week 8 calculated `Week8EconomicEvaluation`     | `output_snapshot.week10_inherited_state.cash_cushion_musd`         |
| `straits_pacific_standing`  | current `StandingState` for Straits Pacific     | `state`                                                            |

The assembler does not read Week 10 fixture rows for runtime teams and does not infer missing values.

## Regression Test

Added:

```text
tests/Feature/Economics/MultiWeekHistoricalIntegrationTest.php
```

Coverage:

- persisted historical chain produces Week 10 golden outputs;
- disciplined history produces `0` binding constraints;
- constrained history produces `5` binding constraints;
- changing persisted historical values changes Week 10 inherited context;
- missing Week 6 dependency returns `unresolved_dependency`;
- wrong-team and wrong-tenant history do not satisfy dependencies;
- Week 9 records cannot masquerade as Week 8 cash state;
- persisted Week 10 evaluation snapshots remain immutable after later standing changes;
- `WeekExecutionService` runs Week 10 through the existing pipeline.

Golden comparisons use the package tolerance:

```text
relative = 1e-3
absolute = 1e-5
```

## Golden Outputs Checked

The multi-week regression verifies the authoritative Week 10 fixture outputs:

- gasoline demand hit `-0.0105`;
- diesel demand hit `-0.0255`;
- jet demand hit `-0.048`;
- blended demand hit `-0.0237`;
- Baton Rouge refinery hit `-0.01992`;
- Rotterdam refinery hit `-0.02226`;
- Singapore refinery hit `-0.02622`;
- hardest-hit refinery `Singapore`;
- disciplined binding count `0`;
- constrained binding count `5`.

## Missing Dependency Behavior

When a required source record is absent, Week 10 persists:

```text
status = unresolved_dependency
```

The missing dependency key is preserved in `unresolved_dependencies`, and binding constraints are not calculated.

## Tenant, Team, And Week Safeguards

The test suite specifically seeds wrong-team and wrong-tenant historical records and verifies they do not satisfy Team A's Week 10 state. It also seeds a Week 9 evaluation carrying a Week 10-shaped cash value and verifies it cannot satisfy the Week 8 cash-cushion dependency.

## Execution Pipeline

The regression confirms the existing pipeline:

```text
WeekExecutionService
        |
        v
Week10EconomicEvaluationService
        |
        v
Week10InheritedStateAssembler
        |
        v
Week10ConvergenceEconomicEngine
        |
        v
Week10EconomicEvaluation
```

No second execution pathway was created.

## Deferred

Still deferred:

- Week 10 KPI/ranking integration;
- standing mutation;
- new consequence links;
- cohort feedback;
- Week 10 what-if;
- LLM interpretation;
- new Week 10 UI.

## Verification Status

PHP/Laravel checks were run with:

```text
C:\Users\sakas\.config\herd-lite\bin\php.exe
```

PHP version:

```text
8.4.0
```

Verification completed:

- focused Batch 21A and Week 10 runtime tests: `13 tests / 128 assertions`;
- existing Week 4, Week 6, Week 8, Week 9, and Week 10 smoke regressions: `5 tests / 298 assertions`;
- full Laravel suite: `326 tests / 1,613 assertions`;
- `composer validate`: passed;
- `composer run lint:check`: passed;
- `composer run types:check`: passed with `0` PHPStan errors;
- `npm run check`: passed;
- `npm run types:check`: passed;
- `npm run build`: passed;
- `git diff --check`: passed.

The destructive clean-database check was not run:

```text
php artisan migrate:fresh --seed
```

That remains a deliberate pending verification item because it resets the local database.

## Defects Found And Fixed

The regression pass found and fixed three issues:

- Week 10 evaluation persistence was reading refinery result keys as `baton_rouge`, `rotterdam`, and `singapore`, while the engine output uses package refinery names: `Baton Rouge`, `Rotterdam`, and `Singapore`.
- Week 10 historical-state assembly used direct JSON and date attributes in places where typed Eloquent access made PHPStan correctly flag impossible or unsafe types. The assembler now reads JSON snapshots and timestamps through typed helper accessors.
- The wrong-team regression fixture attempted to create team membership without first creating the matching enrollment; the fixture now mirrors the real membership constraint.
