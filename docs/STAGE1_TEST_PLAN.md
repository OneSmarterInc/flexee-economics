# Stage 1 Test Plan

## Source constraints

This was the initial Stage 1 test plan. The Week 4 markdown specification is now present, but the worked reference package is still missing, so exact golden fixtures cannot be written yet.

## Unit tests

Economic calculation tests:

- transfer price option calculations;
- segment split values against ledger-provided Week 4 values;
- integrated margin invariance across transfer price options;
- Week 4 KPI calculations from reference inputs;
- score calculations from reference KPIs;
- ranking calculations;
- tie handling;
- rounding and precision;
- validation rules for each decision field.

Domain lifecycle tests:

- activating a section week locks content/constants versions;
- closing a week prevents ordinary resubmission;
- finalized submissions are not mutated;
- scoring records all version metadata;
- publication references an immutable ranking snapshot.

## Feature tests

Authentication and access:

- student login;
- faculty login;
- administrator login;
- unauthenticated users cannot access simulation pages.

Tenant/course/section isolation:

- one tenant cannot access another tenant;
- one course cannot access another course without permission;
- one section cannot access another section without permission;
- student cannot access another team's submission;
- student cannot access another student's private state.

Student Week 4 flow:

- assigned student can view Week 4 when open;
- student sees only assigned seat/role content;
- team member can submit decisions while open;
- invalid decisions are rejected;
- valid decisions are stored with version and submitter;
- memo submission succeeds while open;
- resubmission behavior matches the specification;
- submissions are rejected after close unless an audited faculty override applies.

Faculty Week 4 flow:

- faculty can activate Week 4 for assigned section;
- faculty can monitor submissions;
- faculty can close submissions;
- faculty can trigger/review scoring;
- faculty can publish results;
- faculty cannot act outside assigned scope without administrator rights.

Results visibility:

- students cannot see unpublished results;
- students can see permitted published team KPIs/scores;
- students can see permitted ranking views;
- faculty-only traces remain hidden from students.

## Economic regression tests

Use the existing Week 4 reference package as golden data when supplied.

Required checks:

- Laravel imports the same reference inputs as the package;
- Laravel reproduces expected Week 4 outputs within explicitly permitted precision;
- expected values are not changed merely to make tests pass;
- discrepancies are investigated and documented;
- constants/spec conflicts are flagged rather than silently resolved.

Initial ledger-based regression checks that can be prepared before the package arrives:

- WTI reference equals `$74.00/bbl`;
- Brent spread equals `$4.50/bbl`;
- delivered marginal cost to Baton Rouge equals `$14.10/bbl`;
- integrated margin equals `$76.75/bbl` at reference prices;
- transfer price options equal `$73.70`, `$18.70`, and `$46.20`;
- segment splits match the constants ledger;
- Week 4 discipline can persist into the Week 6 discount-rate schedule.

## Security tests

At minimum:

- student A cannot access student B's submission;
- a team cannot access another team's draft or final submission unless publication rules allow a specific aggregate;
- one tenant cannot access another tenant's users, courses, sections, teams, submissions, scores, or rankings;
- one section cannot access another section without permission;
- student cannot execute faculty actions;
- faculty permissions remain section/course scoped;
- unpublished ranking snapshots are inaccessible to students;
- generated artifacts are not downloadable unless the user is authorized for the owning section/content.

## Queue and audit tests

- scoring job is idempotent for the same finalized input/version;
- failed scoring jobs record failure state and error context;
- publication job does not publish incomplete scoring runs;
- audit events are written for activation, close, submission, scoring, rerun, override, and publication;
- queued LLM assistant failures do not block deterministic scoring or student submission.

## End-to-end Stage 1 test

The final Stage 1 test should cover:

1. Admin/faculty creates tenant/course/section/team/seat assignments.
2. Faculty activates Week 4.
3. Student logs in and views Week 4 content.
4. Team submits decisions and memo.
5. Week closes.
6. Deterministic scoring computes KPIs and scores.
7. Rankings are generated.
8. Faculty reviews and publishes.
9. Student sees permitted results.
10. Unauthorized tenant/section/team access is denied throughout.
