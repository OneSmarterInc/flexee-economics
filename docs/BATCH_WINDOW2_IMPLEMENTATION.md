# Window 2 Cohort Feedback Implementation

This implementation activates the authoritative Week 6 -> Week 8 cohort feedback addendum:

```text
halden-window2-cohort-addendum/
```

## Source Package

The addendum includes:

- `MANIFEST.md`
- `VALIDATION_16A.md`
- `data/window2_params.csv`
- `data/cohort_states.csv`
- `data/week8_link.csv`
- `data/baton_rouge_margin.csv`
- `data/worked_example_window.csv`
- `fixtures/provenance.json`
- `fixtures/window2_golden.json`
- faculty solution artifacts

The package is platform/faculty calibration material. It does not add a student-facing Week 6 or Week 8 content artifact.

## Runtime Architecture

```text
Week 6 CapitalAllocationDecision records
        ↓
CohortFeedbackService
        ↓
CohortDecisionAggregate
        ↓
CohortFeedbackEffect
        ↓
Week8EconomicEvaluationService
        ↓
Week8EconomicEngine
```

## Response Function

The registered function is:

```text
key: week6_gulf_coast_capacity_to_week8_margin_window2
source_week: 6
target_week: 8
effect_key: week6_gulf_coast_capacity_to_week8_margin
```

The input metric is the share of teams in the section that funded the Baton Rouge crude flexibility upgrade in Week 6.

The response is:

```text
share >= 0.50: shift = -6.0 * (share - 0.50)
share <  0.50: shift = +3.0 * (0.50 - share)
```

The canonical package states produce:

| State    | Share | Shift $/bbl |
| -------- | ----- | ----------- |
| all_add  | 1.00  | -3.00       |
| most_add | 0.75  | -1.50       |
| split    | 0.50  | 0.00        |
| few_add  | 0.25  | 0.75        |
| none_add | 0.00  | 1.50        |

## Week 8 Integration

Week 8 now keeps OPEC and cohort contributions separate:

```text
Week 8 crack = 21.50 + (-0.35 * delta WTI) + Window 2 shift
```

The OPEC coefficient `-0.35` remains the OPEC propagation coefficient. It is not reused as the capacity-response function.

## Guardrails

- The seven-week variant is excluded from Window 2.
- Week 7 compounding is deferred.
- No Window 1 or Window 3 runtime behavior is introduced here.
- No new KPI, ranking, standing, what-if, or LLM behavior is introduced here.
