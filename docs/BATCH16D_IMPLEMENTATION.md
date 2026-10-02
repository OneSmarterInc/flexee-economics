# Batch 16D - Week 6 Runtime Integration

Batch 16D connects the package-backed Week 6 capital economics path to the student and faculty runtime surfaces.

## Runtime Flow

```text
Week 6 content activation
    -> Student simulation week workspace
    -> CapitalAllocationDecision
    -> MemoSubmission
    -> FacultyWeekControl
    -> WeekExecutionService
    -> Week6CapitalEconomicsService
    -> CapitalAllocationEvaluation
```

The Week 6 student workspace uses the existing `CapitalProject` catalog and `CapitalAllocationService`; it does not introduce a second submission system.

## Student Experience

For Week 6, the student submission page now includes a capital allocation workspace:

- active Week 6 content artifacts are resolved through `SimulationContentResolver`;
- only student/shared artifacts are visible to students;
- available projects come from active `CapitalProject` records;
- discount-rate context comes from the existing Week 4 -> Week 6 `DiscountRateConsequence`;
- submitted allocations are immutable.

The generic Week 4 decision form path remains unchanged.

## Faculty Experience

`FacultyWeekControl` now executes Week 6 with `week6_capital_allocation` package validation and shows capital allocation submission counts alongside decisions and memos.

Execution continues through the existing `WeekExecutionService`; NPV/IRR formulas are not placed in Livewire components.

## Verification

`Week6RuntimeActivationSmokeTest` proves the classroom flow:

```text
student opens Week 6
    -> sees Week 6 student package artifacts
    -> submits selected/rejected capital projects
    -> submits memo
    -> faculty executes Week 6
    -> CapitalAllocationEvaluation is stored
    -> output is checked against week6_golden.json
```

## Deferred

This batch intentionally does not implement:

- Week 6 KPI calculations;
- ranking impact;
- Week 8 consequence mechanics;
- cohort capacity feedback;
- standing changes;
- causal-trace changes;
- Week 6 what-if.
