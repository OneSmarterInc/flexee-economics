# Release Readiness

Current release-readiness checkpoint: Batch 32A.

Current baseline command results:

- PHP: `8.4.0`
- Composer: `2.8.12`
- Node: `v20.19.5`
- npm: `10.8.2`
- Clean rebuild: passed
- Demo health/reset: passed
- Focused acceptance: `25 tests / 682 assertions`, passed
- Full Laravel suite: `416 tests / 2,681 assertions`, passed
- PHPStan: `0 errors`
- Frontend check/types/build: passed

## Supported Platform Capabilities

### Content

- Authoritative package registration and activation.
- Provenance and artifact checksum validation.
- Student/faculty/solution artifact separation.
- Golden fixtures for implemented computational weeks.

### Simulation Runtime

Implemented runtime paths:

- Week 4: transfer pricing.
- Week 5: currency, FX, and hedging.
- Week 6: capital allocation, NPV, and IRR.
- Week 8: OPEC/scenario economics.
- Week 9: market and retail economics.
- Week 10: convergence economics from persisted historical state.
- Week 11: Kessana fiscal decision economics.
- Week 12: portfolio transition economics.
- Week 13: factor markets.

Week 14 is intentionally non-computational and is implemented as a board-defense assessment workflow.

### Student Experience

- Multi-week dashboard and timeline.
- Current week content and submission workspace.
- Decision and memo submission.
- Own results/status history.
- Week 14 board-defense submission.
- Published assessment feedback visibility.

Students must not see peer submissions, faculty-only artifacts, solution artifacts, causal trace, what-if tools, unpublished assessment feedback, or faculty private notes.

### Faculty Experience

- Operations dashboard.
- Week readiness and execution status.
- Week control and execution.
- Result review with incomplete KPI/ranking states preserved.
- Causal trace entry point.
- What-if entry point.
- Week 14 board-defense review, rubric capture, and feedback publication.

Faculty access is limited to assigned sections unless the user is a tenant administrator.

## Acceptance Commands

### Clean Database

```bash
php artisan migrate:fresh --seed
```

Expected: pass.

Batch 32A result: passed.

### Demo Baseline

```bash
php artisan halden:demo-health
php artisan halden:demo-reset
php artisan halden:demo-health
```

Expected: pass.

Batch 32A result: passed.

### Focused Acceptance

```bash
php artisan test \
  tests/Feature/Demo/DemoOperationsTest.php \
  tests/Feature/Student/StudentJourneyTest.php \
  tests/Feature/Faculty/FacultyOperationsDashboardTest.php \
  tests/Feature/Assessment/Week14AssessmentWorkflowTest.php \
  tests/Feature/Release/FinalProductReadinessTest.php \
  tests/Feature/Week4/Week4RuntimeActivationSmokeTest.php \
  tests/Feature/Week5/Week5RuntimeIntegrationSmokeTest.php \
  tests/Feature/Week6/Week6RuntimeActivationSmokeTest.php \
  tests/Feature/Week8/Week8RuntimeIntegrationSmokeTest.php \
  tests/Feature/Week9/Week9RuntimeIntegrationSmokeTest.php \
  tests/Feature/Week10/Week10RuntimeIntegrationSmokeTest.php \
  tests/Feature/Week11/Week11RuntimeIntegrationSmokeTest.php \
  tests/Feature/Week12/Week12RuntimeIntegrationSmokeTest.php \
  tests/Feature/Week13/Week13RuntimeIntegrationSmokeTest.php
```

Expected: pass.

Batch 32A result: `25 tests / 682 assertions`, passed.

### Full Suite

```bash
php artisan test
```

Expected: pass.

Batch 32A result: `416 tests / 2,681 assertions`, passed.

### Quality Checks

```bash
composer validate
composer run lint:check
composer run types:check
npm run check
npm run types:check
npm run build
git diff --check
```

Expected: pass.

Batch 32A result: passed.

## Known Limitations

- KPI and ranking outputs are intentionally incomplete where authoritative package mappings are absent.
- Consequence links are created only where authoritative consequence mappings exist.
- Week 6 to Week 8 cohort-response production parameters are supplied by `halden-window2-cohort-addendum/`; Week 7 compounding and the future market-data anchor refresh remain deferred.
- Week 14 assessment does not calculate automatic grades, points, weights, or ranking-derived scores.
- LLM interpretation remains behind the existing service/provider boundary and is not a grading authority.

## Release Gate

The platform is ready for a local faculty/student pilot when:

- clean database rebuild passes;
- demo health/reset passes;
- focused acceptance tests pass;
- full test suite passes;
- quality checks pass;
- working tree is clean.
