# Batch 34F - Effective-Dated Seat Assignments

## Status

Batch 34F makes Halden seat ownership historically reconstructable without changing economics, KPI calculations, ranking, cohort windows, consequences, Week 14 grading, or LLM behavior.

## Problem

Before this batch, the platform stored `simulation_seat_assignments` as current state. Seven-week role phase and seat metadata could be preserved in decision-definition snapshots, but the platform could not independently answer:

```text
Which seat did this student hold when this decision was made?
```

That becomes insufficient once seats rotate:

- fourteen-week arc: roles rotate between Week 7 and Week 8;
- seven-week arc: roles rotate between Week 8 and Week 10.

## Current Storage Audit

| Requirement             | Current storage before 34F                     | Historical storage after 34F                                         | Pass/Gap |
| ----------------------- | ---------------------------------------------- | -------------------------------------------------------------------- | -------- |
| Team membership         | `team_members`                                 | unchanged                                                            | pass     |
| Current runtime seat    | `simulation_seat_assignments`                  | unchanged as current/fallback state                                  | pass     |
| Historical seat period  | none                                           | `simulation_seat_assignment_periods`                                 | pass     |
| Effective start/end     | none                                           | week-number and timestamp effective bounds                           | pass     |
| Decision reconstruction | form snapshot only                             | form snapshot plus seat context snapshot and effective-period lookup | pass     |
| Week 14 history         | simulation history packet without seat history | history packet includes `seat_history`                               | pass     |

## Data Model

New table:

```text
simulation_seat_assignment_periods
```

Core fields:

- tenant;
- team simulation;
- team;
- user;
- seat;
- role phase;
- effective start week;
- effective end week;
- optional effective start timestamp;
- optional effective end timestamp;
- source;
- metadata.

The existing `simulation_seat_assignments` table remains the current assignment/fallback surface. This avoids replacing the existing role system and adds the missing historical dimension.

## Effective-Date Semantics

Seat resolution now works from:

```text
Decision submission
  -> runtime week number and submitted timestamp
  -> effective seat assignment period
  -> seat/role context
```

If no effective period exists, the resolver can fall back to the current assignment for backward compatibility, but newly submitted decisions capture `seat_context` in the immutable decision snapshot.

## Authoritative Transitions

### Seven-Week Variant

Authoritative:

```text
Weeks 1, 4, 6, 8  -> first seat
Weeks 10, 12, 14  -> second seat
```

The model supports this through explicit effective periods:

```text
first seat:  effective_from_week_number = 1,  effective_until_week_number = 8
second seat: effective_from_week_number = 10, effective_until_week_number = 14
```

The seven-week sequence is unchanged, and Window 1, Window 2, and Window 3 remain excluded.

### Fourteen-Week Arc

Authoritative material states that role rotation happens between Week 7 and Week 8. The model supports:

```text
first seat:  Weeks 1-7
second seat: Weeks 8-14
```

The source does not define an automatic algorithm for assigning each student's second seat. This batch therefore supports storage and lookup of the effective periods without inventing a second-seat schedule.

## Historical Decision Behavior

Decision submissions now store both historical dimensions:

```text
Form definition snapshot
+
Seat/role context snapshot
```

Later edits to current seat assignment do not mutate submitted decisions. Causal trace reads the stored seat context when present and falls back to effective lookup only for older records.

## Causal Trace

Decision nodes now include:

- definition snapshot;
- available alternatives;
- selected answer;
- submitted timestamp;
- seat context at decision time.

This preserves form-version history and role/seat history as complementary facts.

## Week 14 Interaction

Week 14 remains faculty-controlled assessment. No automatic grading or role-performance scoring was added.

The board-defense faculty history packet now includes `seat_history`, so faculty can see which student held which role during the simulation arc.

## Security

This batch does not broaden access:

- causal trace remains faculty/admin scoped;
- Week 14 private notes remain faculty-only;
- student dashboards only receive the student's own seat context;
- tenant/team isolation is enforced by the assignment-period model and resolver.

## Tests

Focused tests cover:

- assignment-period creation;
- effective start/end lookup;
- historical decision reconstruction;
- seven-week first/second seat schedule;
- fourteen-week Week 7/8 rotation support;
- multiple team members;
- team/tenant isolation;
- causal trace seat context;
- Week 14 history packet seat context;
- immutability against current-assignment changes.

## Deferred

- Automatic second-seat assignment order remains undefined by the authoritative sources.
- Role charter content versioning remains a separate content-management question.
- Cross-section ranking, deployment, grading automation, LLM behavior, and economics are unchanged.
