# Week 4 Execution Flow

## Source constraints

The Week 4 specification and constants ledger are now present, but the worked Week 4 reference package is still missing from the current workspace. This flow describes the platform sequence required by the authoritative sources without implementing Week 4 economics or inventing package-level formulas, rounding, datasets, manifests, or expected outputs.

## Pre-activation

1. Administrator or faculty creates/imports tenant, institution, course, section, users, teams, and seat assignments.
2. Halden simulation and Week 4 content package are loaded as a versioned content record.
3. Week 4 package manifest, datasets, constants hash, generated artifact hashes, decision schema, memo schema, and scoring engine identifier are recorded.
4. Faculty reviews roster, teams, seats, opening time, closing time, and publication settings.

## Faculty activates Week 4

Operation type:

- faculty-controlled;
- synchronous for state transition;
- audited.

Expected actions:

1. Faculty activates Week 4 for a section.
2. Application creates or updates the section Week 4 lifecycle record.
3. Application locks the content version and constants/artifact references for that section run.
4. Audit event records actor, timestamp, section, week, content version, and schedule.

## Student views assignment and data

Operation type:

- synchronous;
- authorization-gated;
- tenant- and section-scoped.

Expected actions:

1. Student logs in.
2. Application resolves tenant, enrollment, section, team, and assigned seat.
3. Student sees Week 4 only if it is open for the section and the student belongs to that section/team.
4. Student sees assigned role/seat content and permitted Week 4 datasets.
5. Student cannot see another team, section, tenant, or unpublished faculty-only information.

## Student/team submits decisions

Operation type:

- synchronous validation and persistence;
- deterministic validation;
- audited.

Expected actions:

1. Student opens the Week 4 decision form.
2. Application validates payload against the versioned Week 4 decision schema.
3. Application verifies the week is open and the user can act for the team.
4. Application stores the decision submission with payload, version, submitter, and timestamp.
5. Resubmission behavior follows the versioned Batch 3 submission framework unless the missing reference package or a later instruction supplies stricter Week 4 rules.

## Student/team submits memo

Operation type:

- synchronous validation and persistence;
- audited.

Expected actions:

1. Student submits memo response and any allowed attachments.
2. Application validates against the versioned memo schema.
3. Application stores memo submission with submitter, timestamp, body, and metadata.
4. Memo remains scoped to the team and faculty for the section until publication rules allow otherwise.

## Submission closes

Operation type:

- faculty-controlled or scheduled;
- synchronous state transition;
- queued follow-up may begin scoring.

Expected actions:

1. At close time or faculty action, Week 4 changes from open to closed.
2. Application prevents ordinary student resubmission.
3. Audit event records close action.
4. Missing or invalid team submissions are marked according to the versioned decision/memo definition rules, with any package-specific behavior deferred until the reference package is supplied.

## Calculation executes

Operation type:

- deterministic;
- may be queued for operational resilience;
- no external AI dependency.

Expected actions:

1. Scoring job or service loads the locked Week 4 content version.
2. It loads the locked constants snapshot and generated artifact references.
3. It loads each team's final decision submission and memo submission.
4. It runs Week 4 calculation services.
5. It stores `scoring_runs`, `kpi_results`, and `scores`.
6. It records source versions, constants hash, artifact hashes, engine version, and any warnings.

## KPIs and scores are stored

Operation type:

- deterministic persistence;
- auditable.

Expected actions:

1. Each KPI is stored with key, value, unit, precision, and scoring run.
2. Each score component is stored with raw, normalized, weighted values, and scoring run.
3. Historical results remain reproducible even if later content versions are imported.

## Rankings calculate

Operation type:

- deterministic;
- can be queued after scoring completes.

Expected actions:

1. Application ranks teams within the section.
2. Application prepares cross-section comparison if the specification requires it and authorization allows it.
3. Tie handling and rounding must follow the Week 4 specification or package; do not invent.
4. Ranking snapshot is stored immutably.

## Faculty reviews results

Operation type:

- synchronous UI;
- faculty-controlled.

Expected actions:

1. Faculty reviews submissions, KPIs, scores, rankings, and calculation trace.
2. Faculty sees warnings, missing submissions, scoring failures, and audit history.
3. Faculty may rerun deterministic scoring only against an explicitly selected content/constants version.
4. Any override or rerun is audited.

## Faculty publishes results

Operation type:

- faculty-controlled;
- synchronous state transition with optional queued notifications;
- audited.

Expected actions:

1. Faculty selects publication visibility.
2. Application verifies scoring/ranking completeness.
3. Publication record references the ranking snapshot.
4. Students gain access only to permitted published results.

## Students view permitted results

Operation type:

- synchronous;
- authorization-gated.

Expected actions:

1. Student sees own/team KPIs and score components allowed by publication settings.
2. Student sees within-section ranking if published.
3. Student sees cross-section comparison only if required and permitted.
4. Student does not see unpublished faculty-only traces, other tenants, unauthorized sections, or restricted submissions.

## Missing Week 4 specifics

The following cannot be completed until the Week 4 reference package is present:

- exact package manifest and artifact checksums;
- canonical CSV schemas and contents;
- workbook and notebook formulas;
- custom transfer-price validation bounds, precision, and labels;
- package-specific memo limits or rubric;
- KPI definitions;
- score weights;
- ranking/tie rules;
- rounding and precision rules;
- golden expected outputs;
- package-backed faculty-visible trace fixtures.
