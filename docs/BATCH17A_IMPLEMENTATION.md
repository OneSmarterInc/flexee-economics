# Batch 17A - Week 6 to Week 8 Cohort Feedback Boundary

Batch 17A activates the runtime boundary needed for the Week 6 -> Week 8 cohort feedback window without inventing the missing market-response calibration.

## What Is Now Connected

The cohort feedback framework can now consume Week 6 capital allocation records:

```text
CapitalAllocationDecision
    -> CohortDecisionAggregate
    -> CohortFeedbackEffect
    -> future Week 8 runtime context
```

`CohortFeedbackService` supports an input source of `capital_allocation`, allowing a configured `CohortResponseFunction` to aggregate selected project metrics from immutable `CapitalAllocationDecision` snapshots.

## Week 6 Capacity Aggregation

A Week 6 response function can declare:

```php
[
    'source' => 'capital_allocation',
    'project_keys' => ['baton_rouge'],
    'project_metric' => 'gc_capacity_addition_mkbd',
]
```

The service then:

- reads submitted Week 6 capital allocation decisions;
- includes only selected matching projects;
- sums the configured project metric;
- stores individual decision snapshots in `CohortDecisionAggregate`;
- stores only aggregate/effect data in `CohortFeedbackEffect`.

This preserves hidden peer decisions while still making the resulting revealed market effect available to the future Week 8 runtime.

## Parallel-Universe Baseline

`response_snapshot` now preserves `parallel_universe_baseline` from response-function parameters when supplied. This is the hook faculty tools will use later to answer:

> What would the team have experienced without the cohort effect?

## Authoritative Calibration Status

The current Week 6 package and source documents identify the Window 2 mechanism:

```text
Week 6 Gulf Coast capacity additions -> Week 8 Gulf Coast refining margin
```

They do not provide the actual calibrated response-function parameters, anchor episode, multiplier, or golden expected outputs for this window.

Therefore this batch does not create a production Week 6 -> Week 8 response function. Tests use an explicit fixture function only to verify the runtime path.

## Deferred

Still deferred until an authoritative Week 6/8 response package is available:

- production Window 2 response parameters;
- golden Week 6 -> Week 8 expected outputs;
- Week 8 OPEC economics;
- Week 8 student UI;
- KPI/ranking impact;
- standing changes;
- causal-trace UI changes;
- LLM interpretation.
