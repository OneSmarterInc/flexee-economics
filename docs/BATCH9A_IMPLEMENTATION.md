# Batch 9A Implementation

Batch 9A adds the cohort feedback framework. It does not implement the authoritative Week 3, Week 6, or Week 7 response functions yet.

## Architecture

```text
Section Simulation
        |
        v
CohortResponseFunction
        |
        v
CohortDecisionAggregate
        |
        v
CohortFeedbackEffect
        |
        v
Future Week Adjustment
```

The framework records how individual team decisions aggregate into a cohort signal and how that signal becomes a future-week effect.

## Response Functions

`CohortResponseFunction` stores:

- key
- name and description
- version
- source week number
- target week number
- input definition
- output definition
- bounds
- parameters
- active flag

Batch 9A supports a generic configured response calculation for framework verification. The real Week 3/6/7 functions remain deferred until authoritative packages exist.

## Aggregate Records

`CohortDecisionAggregate` stores:

- tenant
- section simulation
- source runtime week
- target runtime week
- function key/version
- individual decision snapshot
- aggregate snapshot
- response snapshot
- calculation actor and timestamp

Aggregates are immutable once created.

## Future Effects

`CohortFeedbackEffect` stores:

- source and target runtime weeks
- aggregate reference
- response function reference
- effect key/version
- effect snapshot
- reveal timestamp
- applied timestamp

Effects are immutable once created.

## Hidden Decision Window

The service refuses to resolve cohort feedback while the source week is still `released` or `open`.

Students can view only revealed future effects. Student-visible effect snapshots do not include individual peer decision snapshots.

## Deferred

The following remain intentionally out of scope:

- actual Week 3 European run-rate response
- actual Week 6 Gulf Coast capacity response
- actual Week 7 retail pricing response
- Week 4 to Week 6 discount-rate linkage
- synthetic path generation
- Python calibration
- Week 5/8/9 mechanics
