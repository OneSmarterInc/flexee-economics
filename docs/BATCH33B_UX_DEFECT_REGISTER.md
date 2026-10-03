# Batch 33B UX Defect Register

Batch 33B rehearsed the local teaching pilot through the actual browser UI against the local Laravel server.

Date: 2026-10-03

Baseline before rehearsal:

- `dd35222215e4ab037fdf7f9d0afb22a4a67b001e`
- Step 7 final post-integration regression passed.

## Summary Classification

`READY WITH UX FRICTION` for a local full-arc rehearsal.

`BLOCKED FOR TRUE SEVEN-WEEK PILOT REHEARSAL` until the demo environment exposes a seven-week pilot section or variant as a first-class selectable experience.

The core platform works through the actual UI for:

- faculty login and week control;
- student dashboard;
- student decision submission;
- student memo submission;
- faculty execution;
- student resolved-state visibility;
- student denial from faculty routes.

## Defects and Friction

### B33B-001: Demo UI Defaults to Fourteen-Week Flagship

Severity: High for seven-week pilot.

Observed:

- Student dashboard shows `Fourteen-week flagship 2026-demo`.
- Student timeline shows Weeks 1 through 13 as active and Week 14 as upcoming.
- Faculty dashboard and week control show the full Week 1 through Week 14 list.

Impact:

- A faculty member trying to rehearse the documented seven-week pilot cannot clearly identify a seven-week section.
- Students see many active future weeks at once, which weakens the paced course experience.

Recommendation:

- Add an explicit seven-week pilot demo section or demo reset option.
- Display the variant label prominently.
- Limit the visible runtime sequence to `1 -> 4 -> 6 -> 8 -> 10 -> 12 -> 14` for that variant.

Status: Open.

### B33B-002: All Computational Weeks Are Open Simultaneously

Severity: Medium.

Observed:

- Weeks 1 through 13 appear as `ACTIVE` for students.
- Students can open later weeks before the prior weeks have been completed in the UI.

Impact:

- This is useful for developer validation but confusing for a live student pilot.
- It makes the intended weekly flow less obvious.

Recommendation:

- Add a faculty-controlled pacing mode for pilot/demo sections.
- Alternatively seed only the current week as open and keep later weeks published/upcoming until faculty opens them.

Status: Open.

### B33B-003: No Seeded Multi-Team Pilot by Default

Severity: Medium.

Observed:

- The default demo seed created one team in Section A.
- Batch 33B added a second team manually in the local database for rehearsal only.

Impact:

- A realistic local pilot needs at least two teams to validate cohort/faculty readiness.
- Manual database setup is not suitable for faculty onboarding.

Recommendation:

- Add a non-production pilot seeder or command that creates multiple teams and students.
- Keep `halden:demo-reset` deterministic.

Status: Open.

### B33B-004: Content Artifact Labels Are Technical

Severity: Low to Medium.

Observed:

- Student materials show labels such as `week1_data_benchmarks_csv` and `week8_data_opec_scenarios_csv`.

Impact:

- Students can access the right artifacts, but the labels are not course-friendly.

Recommendation:

- Add display names to content artifacts or derive readable labels from package metadata.

Status: Open.

### B33B-005: Week 14 Is Not Rehearsable From the Seeded Student Journey Without Additional Faculty Setup

Severity: Medium.

Observed:

- Week 14 appears as `UPCOMING` in the student dashboard.
- The board-defense workflow exists, but the default pilot path does not guide faculty through opening or staging the defense week.

Impact:

- The Week 14 assessment workflow is implemented, but the local teaching rehearsal needs a clearer operational path.

Recommendation:

- Add Week 14 to the pilot runbook as an explicit faculty setup step.
- Consider exposing a faculty action to prepare/open the board-defense week from the operations dashboard.

Status: Open.

### B33B-006: Mobile Browser Rehearsal Not Fully Verified

Severity: Low.

Observed:

- The automated browser surface used during rehearsal did not expose a reliable viewport resize method in this session.
- Prior implementation uses responsive cards and grids, but Batch 33B did not complete a true mobile viewport inspection.

Impact:

- Mobile readiness is not rejected, but it remains less directly verified than desktop.

Recommendation:

- Perform a manual phone-size browser pass before a real student pilot.

Status: Open.

## Non-Issues Confirmed

- Students cannot access the faculty dashboard directly; the browser received `403 Forbidden`.
- Student materials did not expose faculty solution artifacts or golden fixtures during the inspected flows.
- Faculty Week 1 execution succeeded once both local teams submitted decisions and memos.
- Student resolved-state visibility matched faculty execution after Week 1 execution completed.
