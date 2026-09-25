# Batch 16C Implementation

Batch 16C integrated Week 6 capital economics into the week execution pipeline.

## Execution Flow

```text
CapitalAllocationDecision
        |
        v
WeekExecutionService
        |
        v
Week6CapitalEconomicsService
        |
        v
Week6CapitalEconomicsEngine
        |
        v
CapitalAllocationEvaluation
```

`WeekExecutionService` now recognizes Week 6 during the decision-resolution step and evaluates
existing `CapitalAllocationDecision` records. It does not contain NPV or IRR formulas.

## Week 6 Behavior

The Week 6 execution path:

- requires an active Week 6 content package;
- counts capital allocation decisions during submission locking;
- evaluates each allocation through `Week6CapitalEconomicsService`;
- stores immutable `CapitalAllocationEvaluation` records;
- records evaluation status counts in the `WeekExecutionRecord` output snapshot.

Missing discount-rate context remains safe: the Week 6 engine returns an unavailable context status
instead of substituting a base-case discount rate.

## Deferred

Still deferred after Batch 16C:

- Week 6 KPI impact;
- Week 6 ranking impact;
- Week 8 consequences;
- standing changes;
- Week 6 what-if;
- Week 6 student/faculty UI changes beyond existing workspace/control surfaces.
