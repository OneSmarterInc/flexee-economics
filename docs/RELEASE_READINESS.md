# Release Readiness

Current release-readiness checkpoint: Batch 35E GitHub production safety gate.

Current baseline command results:

- PHP: `8.4.0`
- Composer: `2.8.12`
- Node: `v18.20.8`
- npm: `10.8.2`
- Migration files: `36`
- Clean rebuild: passed with `36` migrations applied
- Demo health/reset: passed
- Targeted post-integration regression: `49 tests / 1,033 assertions`, passed
- Focused seven-week/student/faculty regression: `26 tests / 408 assertions`, passed
- Core runtime/cohort regression: `23 tests / 424 assertions`, passed
- Reference-team runtime reconciliation: focused runtime test passed locally during Batch 34E reconciliation; final full-suite release verification pending.
- Full Laravel suite: `492 tests / 4,591 assertions`, passed
- PHPStan: `0 errors`
- Frontend check/types/build: passed
- MySQL release gate: CONFIGURED / AWAITING REMOTE EXECUTION in GitHub Actions with `mysql:8.4`; remote run required before production approval
- GitHub default branch: BLOCKED. GitHub currently reports `master`; required release branch is `main`.
- Main branch protection: BLOCKED. GitHub public branch metadata reports `main.protected=false`.
- Production approval gate: BLOCKED. GitHub environment inspection found `Production` with no protection rules.
- Legacy master deployment path: BLOCKED. `origin/master` still contains an active push-to-`master` production deployment workflow.
- Local teaching pilot rehearsal: ready with UX friction
- Seven-week pilot rehearsal: completed through browser UI from Week 1 through Week 14
- Post-audit seven-week browser rehearsal: completed locally with targeted UX fixes; final full-suite verification passed.
- MySQL identifier compatibility: all detected over-64-character migration identifiers normalized with explicit names.
- Release branch strategy: `main` remains the only release source; `origin/master` remains present and must be disabled/deleted only after GitHub-side verification.

## Supported Platform Capabilities

### Content

- Authoritative package registration and activation.
- Provenance and artifact checksum validation.
- Student/faculty/solution artifact separation.
- Golden fixtures for implemented computational weeks.

### Simulation Runtime

Implemented runtime paths:

- Week 1: asset register.
- Week 2: elasticity estimation.
- Week 3: shutdown point.
- Week 4: transfer pricing.
- Week 5: currency, FX, and hedging.
- Week 6: capital allocation, NPV, and IRR.
- Week 7: competitive response.
- Week 8: OPEC/scenario economics.
- Week 9: market and retail economics.
- Week 10: convergence economics from persisted historical state.
- Week 11: Kessana fiscal decision economics.
- Week 12: portfolio transition economics.
- Week 13: factor markets.

Week 14 is intentionally non-computational and is implemented as a board-defense assessment workflow.

### Cohort Windows

- Window 1: Week 3 European utilization produces the Week 5 NWE crack handoff.
- Window 2: Week 6 Baton Rouge capacity share produces the Week 8 Gulf Coast crack handoff and remains separate from the Week 8 OPEC propagation component.
- Window 3: Week 7 retail-pricing aggression produces the Week 9 non-fuel margin handoff.

All three full-arc cohort windows are excluded from the seven-week compressed variant. The seven-week variant retains the Week 4 -> Week 6 discount-rate/capital-capacity path only.

### Seven-Week Variant

The validated compressed sequence is:

```text
1 -> 4 -> 6 -> 8 -> 10 -> 12 -> 14
```

The variant preserves folded concepts through versioned decision/evaluation snapshots:

- Week 1 folds heavier cost-structure reading.
- Week 6 folds currency exposure/natural-hedge context for Week 10.
- Week 10 folds Norwegian union/factor-market context.
- Role rotation occurs between Week 8 and Week 10.

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

Step 7 result: passed.

Batch 33D browser result: passed through the seeded seven-week pilot sequence.

`php artisan migrate:status` confirmed all `36` migrations ran.

### Demo Baseline

```bash
php artisan halden:demo-health
php artisan halden:demo-reset
php artisan halden:demo-health
```

Expected: pass.

Step 7 result: passed before and after `halden:demo-reset`.

### Targeted Post-Integration Regression

```bash
php artisan test \
  tests/Feature/Week4/Week4RuntimeActivationSmokeTest.php \
  tests/Feature/Week5/Week5RuntimeIntegrationSmokeTest.php \
  tests/Feature/Week6/Week6RuntimeActivationSmokeTest.php \
  tests/Feature/Week8/Week8RuntimeIntegrationSmokeTest.php \
  tests/Feature/Week9/Week9RuntimeIntegrationSmokeTest.php \
  tests/Feature/Week10/Week10RuntimeIntegrationSmokeTest.php \
  tests/Feature/Week11/Week11RuntimeIntegrationSmokeTest.php \
  tests/Feature/Week12/Week12RuntimeIntegrationSmokeTest.php \
  tests/Feature/Week13/Week13RuntimeIntegrationSmokeTest.php \
  tests/Feature/Assessment/Week14AssessmentWorkflowTest.php \
  tests/Feature/CohortFeedback/Window1Window3RuntimeTest.php \
  tests/Feature/CohortFeedback/Window2CohortFeedbackTest.php \
  tests/Feature/Variants/SevenWeekVariantEndToEndTest.php \
  tests/Feature/Submissions/StudentAndFacultySubmissionUiTest.php \
  tests/Feature/CausalTrace/CausalTraceServiceTest.php \
  tests/Feature/Release/FinalProductReadinessTest.php
```

Expected: pass.

Step 7 result: `49 tests / 1,033 assertions`, passed.

### Full Suite

```bash
php artisan test
```

Expected: pass.

Step 7 result: `451 tests / 3,138 assertions`, passed.

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

Step 7 result: passed.

## Known Limitations

- KPI and ranking outputs are intentionally incomplete where authoritative package mappings are absent.
- Consequence links are created only where authoritative consequence mappings exist.
- Cross-section ranking is deferred. Within-section ranking remains implemented; cross-section population,
  normalization, visibility, and publication timing require an authoritative specification before implementation.
- Week 6 to Week 8 cohort-response production parameters are supplied by `halden-window2-cohort-addendum/`; Week 7 compounding and the future market-data anchor refresh remain deferred.
- The seven-week Week 4 -> Week 6 capital-capacity path remains implemented through the evidence-backed discount-rate consequence. The separate authoritative aggregate classification rule for disciplined/base/lax remains unresolved.
- KPI/consequence package `reference_team_inputs.csv` is a scoring-engine fixture, not a runtime economic-output oracle for Weeks 2, 5, 8, 11, 12, or 13.
- Week 8 runtime now uses the audit-authorized interim bridge `interim_week8_ebitda_bridge_v0` for both Week 10 cash cushion and KPI input `i8_ebitda_effect_musd`: `delta_wti * 0.65 * 91.25`. The future position-aware mapping that incorporates hedge coverage and production posture remains deferred until an authoritative addendum supplies it.
- Seat assignment history is effective-dated for historical reconstruction. The authoritative automatic second-seat assignment order remains undefined, so second-seat periods must be supplied by faculty/admin configuration rather than inferred.
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

The platform is ready for production only after:

- the GitHub Actions MySQL release-gate job passes;
- GitHub default branch is confirmed as `main`;
- branch protection requires pull requests and the MySQL test job;
- the GitHub `production` environment requires approval;
- the remote `master` branch can no longer deploy production.

See:

- `docs/BATCH35B_MYSQL_AND_RELEASE_BRANCH.md`
- `docs/BATCH35C_WEEK8_INTERIM_EBITDA_BRIDGE.md`
- `docs/BATCH35D_MYSQL_CI_VALIDATION.md`
- `docs/BATCH35E_GITHUB_PRODUCTION_SAFETY_GATE.md`

## Batch 35E GitHub Production Safety Gate

Batch 35E verifies GitHub-side production safety without pushing or deploying.

Current status:

- `main` is the required release branch, but GitHub currently reports `master` as the repository default branch.
- `main` exists but is not protected according to public branch metadata.
- The MySQL 8.4 CI job is configured in `.github/workflows/tests.yml`, but it cannot be considered a required production gate until branch protection requires it.
- The main-branch deployment workflow is structurally safe: it deploys only after the `tests` workflow succeeds on `main`, checks out the tested SHA, uses the production environment, runs Composer before frontend build, enters maintenance mode, requires backup and backup verification commands before migration, runs migrations with `--force`, rebuilds caches, and performs health checks.
- Production environment approval is not currently enforced. The inspected GitHub environment is `Production` and has no protection rules.
- `origin/master` still contains an active push-to-`master` production deploy workflow with no test, approval, or backup gate.
- Production secret names required by the main deployment workflow are documented, but secret existence was not verified because the GitHub API returned `401 Unauthorized` for Actions secrets.

Production remains blocked until GitHub is manually reconciled:

1. Set the repository default branch to `main`.
2. Protect `main`.
3. Require pull requests or equivalent review control for `main`.
4. Require the MySQL 8.4 CI job before merge/deploy.
5. Configure the production environment with required reviewer approval.
6. Disable or remove the legacy `master` deployment path.
7. Verify required production secrets are present.

Batch 35E did not push code, trigger remote CI, deploy production, delete branches, or modify application logic.

## Batch 33B Teaching Pilot Rehearsal

Batch 33B exercised the actual browser UI at `http://127.0.0.1:8000` with one faculty account and two student/team accounts.

Confirmed:

- faculty dashboard and week control load;
- Week 1 readiness updates when two teams submit;
- faculty can execute Week 1;
- student decision and memo submission works through the UI;
- student resolved state updates after faculty execution;
- student access to `/faculty/dashboard` is denied with `403 Forbidden`;
- representative student workspaces for Weeks 4, 6, 8, 10, 12, and 13 render with student-safe content.

Classification after Batch 33B:

- Full local platform rehearsal: `READY WITH UX FRICTION`.
- True seven-week pilot rehearsal: `BLOCKED UNTIL SEVEN-WEEK VARIANT IS SEEDED/EXPOSED`.

Primary UX findings:

- the local seeded UI defaults to the fourteen-week flagship simulation;
- Weeks 1 through 13 are open simultaneously;
- the default demo seed does not provide multiple teams without manual setup;
- some student artifact labels are technical rather than course-friendly;
- Week 14 needs a clearer faculty operational path for a real board-defense rehearsal.

## Batch 33D Seven-Week Browser Pilot

Batch 33D rehearsed the seeded seven-week pilot through the browser UI:

```text
1 -> 4 -> 6 -> 8 -> 10 -> 12 -> 14
```

Confirmed:

- both Team Alpha and Team Beta can submit the active student workspaces;
- faculty can execute Weeks 1, 4, 6, 8, 10, and 12 in sequence;
- Week 4 execution creates the configured Week 6 discount-rate consequence;
- Week 6 shows classification, discount rate, and capital envelope;
- Week 10 calculates from persisted Week 4, Week 6, Week 8, and standing history;
- Week 14 now has a student board-defense form in the submission workspace;
- Week 14 now has a faculty assessment page with feedback publication;
- published feedback is visible to the student;
- private faculty notes are not visible to the student.

Batch 33D fixed the browser blockers documented in `docs/BATCH33D_IMPLEMENTATION.md`.

See:

- `docs/BATCH33B_IMPLEMENTATION.md`
- `docs/BATCH33B_UX_DEFECT_REGISTER.md`

## Batch 34H Post-Audit Browser Rehearsal

Batch 34H repeated the seven-week pilot browser rehearsal after the Batch 34A-G simulation-integrity fixes.

Confirmed:

- Week 1, Week 4, Week 6, Week 8, Week 10, and Week 12 can be submitted/executed through the local browser flow.
- Week 4 -> Week 6 discount-rate/capital-envelope state is section-level and hidden until the appropriate downstream context.
- Week 10 inherited constraints are derived from persisted consequences/standing history, not student-entered fields.
- Week 14 board-defense submission, faculty assessment, feedback publication, and student feedback visibility work through the UI.
- Student access to the faculty Week 14 assessment route returns `403 Forbidden`.
- Student dashboard and Week 14 workspace were checked at a `390 x 844` viewport.

Batch 34H fixes:

- demo seed prunes deprecated inherited-state fields from upgraded local databases;
- student dashboard current-week selection skips resolved open weeks when a later unresolved week is open;
- student dashboard renders Week 14 board-defense status instead of decision/memo status.

Batch 34H verification:

- focused student/faculty seven-week regression: `14 tests / 185 assertions`, passed;
- broader focused regression including Week 10 constraints: `23 tests / 333 assertions`, passed;
- full Laravel suite: `492 tests / 4,591 assertions`, passed;
- Composer validation, Pint/lint, PHPStan, frontend check/types/build, and `git diff --check`: passed.

See:

- `docs/BATCH34H_SEVEN_WEEK_BROWSER_REHEARSAL.md`

## Batch 33C Seven-Week Pilot Exposure

Batch 33C resolves the Batch 33B blocker for a true seven-week pilot rehearsal.

Seeded pilot:

- section simulation: `Seven-Week Pilot Halden Energy`
- variant: `Seven-Week Variant`
- version: `2026-seven-week-pilot`
- sequence: `1 -> 4 -> 6 -> 8 -> 10 -> 12 -> 14`
- teams: `Team Alpha`, `Team Beta`
- students: five per team
- initial lifecycle: Week 1 open, later pilot weeks draft/upcoming

Faculty UI:

- operations dashboard identifies the seven-week variant and version;
- week control lists only the seven pilot weeks for the pilot section;
- the pilot path is shown directly in the week-control surface.

Student UI:

- dashboard shows the seven-week sequence;
- role rotation is explained as first seat through Week 8 and second seat from Week 10 through Week 14;
- Week 14 is labeled as the Board Defense endpoint;
- package materials use course-friendly artifact labels in both dashboard and workspace;
- validation/provenance/expected-output artifacts are hidden from student views.

The seven-week variant continues to exclude Window 1, Window 2, and Window 3 cohort effects and retains only the Week 4 -> Week 6 discount-rate/capital-capacity path.

Batch 33C browser rehearsal confirmed:

- Team Alpha and Team Beta can submit Week 1 decisions/memos in the seven-week pilot section;
- faculty sees `2 / 2 complete`;
- faculty execution completes Week 1;
- student resolved-state visibility updates after execution.

Batch 33C verification:

- focused seven-week/student/faculty regression: `26 tests / 408 assertions`;
- core runtime/cohort regression: `23 tests / 424 assertions`;
- full Laravel suite: `457 tests / 3,228 assertions`;
- Composer validate, Pint, PHPStan, frontend check/types/build, and `git diff --check`: passed.

See:

- `docs/BATCH33C_IMPLEMENTATION.md`

## Batch 34E Reference-Team Runtime Reconciliation

Batch 34E adds a reference-team runtime reconciliation test that submits the four KPI/consequence package reference teams through the actual runtime from Weeks 1 through 13.

The test deliberately separates:

- exact KPI/consequence package parity through `reference_team_inputs.csv`;
- actual runtime wiring through persisted submissions, evaluations, consequences, KPI snapshots, and ranking snapshots.

This distinction is required because the authoritative KPI/consequence package labels the reference-team inputs for Weeks 2, 5, 8, 11, 12, and 13 as synthetic scoring-engine paths rather than economic claims. The Week 8 package pins OPEC propagation outputs, but it does not define a runtime formula from `Week8EconomicEvaluation` to `i8_ebitda_effect_musd`.

See:

- `docs/BATCH34E_REFERENCE_TEAM_RUNTIME_RECONCILIATION.md`
