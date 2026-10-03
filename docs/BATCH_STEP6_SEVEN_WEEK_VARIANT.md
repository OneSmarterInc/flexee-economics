# Batch Step 6 Seven-Week Variant Validation

Updated: 2026-10-03

## Scope

Step 6 validates the seven-week compressed variant end to end through the existing runtime architecture. It does not create a second runtime path, change Window 2, add Week 14 economics, or invent missing formulas.

## Sources

Read and applied:

- `HANDOFF-README.md`
- `SAKSHI-BATCH-NOTE.md`
- `halden-development-instructions.md`
- `halden-energy-seven-week-variant.md`
- `halden-7wk-week1-spec.md`
- `halden-7wk-week4-spec.md`
- `halden-7wk-week6-spec.md`
- `halden-7wk-week8-spec.md`
- `halden-7wk-week10-spec.md`
- `halden-7wk-week12-spec.md`
- `halden-7wk-week14-spec.md`
- `halden-window2-cohort-addendum/MANIFEST.md`
- `halden-window2-cohort-addendum/VALIDATION_16A.md`

## Authoritative Sequence

The validated sequence is:

```text
Week 1 -> Week 4 -> Week 6 -> Week 8 -> Week 10 -> Week 12 -> Week 14
```

The variant is not a mechanical Week 1 through Week 7 run.

## Folded Concepts

- Week 1 folds heavier cost-structure reading into the asset-register work.
- Week 6 folds currency exposure and natural-hedge reasoning into the capital-allocation week.
- Week 10 folds the Norwegian union/factor-market thread into the recession convergence week.

## Cohort Window

The validated Week 4 -> Week 6 link uses the existing `DiscountRateConsequence` pathway:

```text
Week 4 decision
  -> Week 4 economic resolution
  -> discount-rate consequence
  -> Week 6 capital allocation context
```

No separate seven-week `CohortResponseFunction` was added because the authoritative materials still do not define the production aggregate classification rule or thresholds.

## Window Exclusion

Window 1, Window 2, and Window 3 are excluded for seven-week variants. The regression asserts no `CohortDecisionAggregate` or `CohortFeedbackEffect` is created, including no Window 2 Week 6 -> Week 8 effect.

## Role Rotation

The regression rotates from `commercial_operations` to `operations_finance` between Week 8 and Week 10. Team identity remains unchanged. Role phase is captured in versioned decision-definition snapshots:

- Week 8: `first_seat`
- Week 10: `second_seat`

## Student Workflow

The student path validates:

- student dashboard timeline for the seven authoritative weeks;
- student-safe content access;
- Week 1 decision and memo submission;
- Week 4 decision and memo submission;
- Week 6 capital allocation and memo submission;
- Week 8 prediction/realization decision and memo submission;
- Week 10 convergence decision and memo submission;
- Week 12 transition portfolio decision and memo submission;
- Week 14 board defense submission.

The student path does not expose faculty materials, golden fixtures, peer decisions, faculty tools, or hidden cohort effects.

## Faculty Workflow

The faculty path validates:

- faculty operations dashboard access for the assigned section;
- normal `FacultyWeekControl` execution for Weeks 1, 4, 6, 8, 10, and 12;
- Week 14 assessment save path.

No variant-specific faculty infrastructure was added.

## Historical State

The regression verifies Week 10 consumes persisted historical state:

- Week 4 persisted `EconomicResolution`;
- Week 6 persisted `CapitalAllocationEvaluation`;
- Week 8 persisted `Week8EconomicEvaluation`;
- persisted Straits Pacific standing.

The seven-week-specific defect found during validation was that Week 10 hedge coverage only looked for Week 5 history. The runtime now falls back to Week 6 folded currency state only when the simulation variant duration is seven weeks.

## Form-Version Reconstruction

The regression verifies versioned decision-definition snapshots preserve:

- selected answers;
- available alternatives;
- role-phase metadata;
- causal trace reconstruction for at least one variant decision.

## Failure And Recovery

The regression deliberately withholds folded Week 6 hedge coverage and verifies Week 10 records `unresolved_dependency` instead of defaulting. A fresh run with the persisted Week 6 folded hedge state calculates normally.

## Deferred

- No new Week 4 -> Week 6 aggregate cohort threshold is implemented.
- No Window 2 behavior is changed.
- No new economic formulas are added.
- No Week 14 grading automation is added.
