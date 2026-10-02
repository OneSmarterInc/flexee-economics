# Step 4 - Week 2 Runtime

## Source Package

Week 2 uses the authoritative package at `halden-week2-data-package/`.

Validated package artifacts include:

- `MANIFEST.md`
- `VALIDATION_16A.md`
- `halden_week2.xlsx`
- `halden_week2_analysis.ipynb`
- `faculty/halden_week2_FACULTY_SOLUTION.xlsx`
- `faculty/halden_week2_FACULTY_SOLUTION.ipynb`
- `data/cluster_params.csv`
- `data/cluster_price_volume.csv`
- `data/pricing_params.csv`
- `data/fuel_nonfuel.csv`
- `data/worked_example_cluster.csv`
- `data/worked_example_params.csv`
- `fixtures/provenance.json`
- `fixtures/week2_golden.json`

## Runtime Architecture

```text
Week 2 decision submission
        |
        v
WeekExecutionService
        |
        v
Week2EconomicEvaluationService
        |
        v
Week2ReferencePackage
        |
        v
Week2EconomicEngine
        |
        v
Week2EconomicEvaluation
```

## Implemented Components

- `Week2ReferencePackage`
- `Week2EconomicInputs`
- `Week2Cluster`
- `Week2ClusterResult`
- `Week2EconomicEngine`
- `Week2EconomicResult`
- `Week2EconomicEvaluationService`
- `Week2EconomicEvaluation`
- `week2_economic_evaluations` migration
- Week 2 execution routing in `WeekExecutionService`
- Week 2 resolved-state visibility in student submission/dashboard views
- Week 2 economic summary visibility in the faculty operations dashboard
- Week 2 package-backed demo decision and memo definitions

## Economic Requirement

The Week 2 engine implements only package-supported elasticity mechanics:

- estimate each cluster's own-price elasticity as the slope of `ln_volume` on `ln_price`;
- compute Cordell and Europe volume-weighted elasticity estimates;
- compute Cordell weighted pass-through;
- compute package-defined volume response percentages for rack-price cuts;
- validate the worked example from package data.

## Student Workflow

Students receive the Week 2 package, submit cluster elasticity estimates, predicted response fields, an optional pricing strategy note, and a memo.

The submitted answers are preserved in `Week2EconomicEvaluation.decision_snapshot`.

## Faculty Workflow

Faculty execute Week 2 through the same week-control path used by the other implemented runtime weeks. Execution creates immutable `Week2EconomicEvaluation` records.

## Seven-Week Variant

Week 2 is not part of the authoritative seven-week variant. This batch does not add Week 2 to the compressed arc and does not alter Window 1, Window 2, or Window 3 cohort behavior.

## Deferred

- Week 2 KPI/ranking mapping.
- Week 2 standing changes.
- Week 2 consequence links.
- Week 2 what-if support.
- Week 2-specific custom UI beyond the generic decision workspace.
- Any Week 9 dealer/cooperation consequence from Week 2 elasticity or pricing behavior.

No downstream effects were inferred from narrative text.
