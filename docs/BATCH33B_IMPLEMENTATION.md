# Batch 33B Implementation

Batch 33B performed a local teaching pilot rehearsal through the actual browser UI.

No application code was changed.

## Baseline

Starting checkpoint:

```text
dd35222215e4ab037fdf7f9d0afb22a4a67b001e
test: validate final post-integration regression
```

Local URL:

```text
http://127.0.0.1:8000
```

Server command:

```bash
php artisan serve --host=127.0.0.1 --port=8000
```

## Accounts Used

Faculty:

- `faculty@example.test`

Students:

- `student11@example.test`
- `pilot-beta1@example.test`

Password:

- `password`

## Temporary Local Pilot Data

The seeded demo had one team in Section A. To rehearse a multi-team classroom flow, Batch 33B added a second local-only team directly to the development database:

- Team: `Team Beta`
- Students: `pilot-beta1@example.test` through `pilot-beta5@example.test`

This was rehearsal data only. No seeder or application code was changed.

## Faculty Rehearsal

The faculty dashboard and week control were exercised through the browser.

Confirmed:

- faculty login works;
- Section A is visible to the assigned faculty user;
- Week 1 package is active and validated;
- Week 1 submission readiness updated to `2 / 2 complete` after Team Alpha and Team Beta submitted;
- faculty execution completed Week 1 through the UI;
- execution steps were visible.

Observed Week 1 execution result:

```text
validate content package: completed
lock submissions: completed
resolve decisions: completed
apply kpi calculations: deferred
calculate rankings: deferred
generate consequences: deferred
apply cohort effects: deferred
publish allowed outputs: deferred
```

The deferred steps are acceptable for Week 1 because downstream KPI/ranking/consequence mappings are not implemented for that week.

## Student Rehearsal

Team Alpha submitted Week 1 through the actual student workspace.

Submitted inputs:

- Permian rig count: `12`
- Rotterdam review posture: `hold_review`
- First meeting choice: `vestergaard`
- Memo: completed

Team Beta submitted Week 1 through the actual student workspace.

Submitted inputs:

- Permian rig count: `10`
- Rotterdam review posture: `accelerate_review`
- First meeting choice: `delacroix`
- Memo: completed

Confirmed:

- student dashboard loads;
- student current-week workspace loads;
- decisions can be submitted;
- memos can be submitted;
- submitted work is locked;
- faculty execution changes the student result state to resolved/completed;
- students see their own history only.

## Representative Week Sampling

The student dashboard and representative workspaces were sampled for:

- Week 4: transfer pricing;
- Week 6: capital allocation;
- Week 8: OPEC/scenario prediction;
- Week 10: recession/convergence;
- Week 12: transition portfolio;
- Week 13: factor markets.

Confirmed:

- active content packages are visible;
- student-safe artifacts are visible;
- decision forms render for each inspected week;
- no faculty solution artifacts or golden fixtures appeared in the inspected student pages.

## Security Rehearsal

Student direct access to:

```text
/faculty/dashboard
```

returned:

```text
403 Forbidden
```

This confirms the core faculty/student boundary in the browser, not only in tests.

## Seven-Week Pilot Finding

The implemented seven-week sequence is documented and tested:

```text
1 -> 4 -> 6 -> 8 -> 10 -> 12 -> 14
```

However, the seeded browser experience currently presents:

- `Fourteen-week flagship 2026-demo`;
- Weeks 1 through 13 as active;
- Week 14 as upcoming.

This means the full platform is locally rehearsable, but a first-class seven-week pilot rehearsal is not yet cleanly exposed through the seeded UI.

See:

- `docs/BATCH33B_UX_DEFECT_REGISTER.md`

## Acceptance Classification

Batch 33B classification:

- Full local platform rehearsal: `READY WITH UX FRICTION`
- True seven-week teaching pilot rehearsal: `BLOCKED UNTIL SEVEN-WEEK VARIANT IS SEEDED/EXPOSED`

No feature code was changed in this batch because the findings are product/seed/pacing issues, not correctness failures in the underlying runtime.

## Deferred

Deferred intentionally:

- code changes for seven-week pilot seeding;
- faculty pacing controls;
- friendlier artifact display names;
- Week 14 operational polish;
- mobile viewport pass;
- new economic logic.
