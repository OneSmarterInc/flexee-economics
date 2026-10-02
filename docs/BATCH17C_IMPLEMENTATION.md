# Batch 17C - Week 6 to Week 8 Response Function Reconciliation

Batch 17C searched the authoritative source locations for the then-missing Week 6 -> Week 8 cohort response function. It did not implement a production response function at that time.

## Superseded Status

The later authoritative package:

```text
halden-window2-cohort-addendum/
```

now supplies the executable Window 2 parameters, golden fixture, faculty solution artifacts, and provenance hashes. The production implementation uses this addendum through the existing `CohortResponseFunction` / `CohortFeedbackService` / `CohortFeedbackEffect` path.

The active production mapping is:

```text
Week 6 Baton Rouge crude-flexibility funding share
    -> asymmetric pivoted cohort response
    -> Week 8 Gulf Coast refining-crack shift
```

The response remains separate from the Week 8 OPEC propagation coefficient `-0.35`.

## Search Scope

The reconciliation reviewed the local source folder:

```text
C:\Users\sakas\Documents\Flexee-economics
```

and the application repository:

```text
C:\Users\sakas\Documents\ChatGPT\Flexee-economics
```

The search targeted:

- Week 6 -> Week 8 cohort response parameters;
- Gulf Coast capacity-addition aggregation rules;
- refining-margin response curves;
- calibration or anchor episodes;
- real elasticity values;
- pedagogical multipliers;
- bounded response rules;
- disciplined/base/aggressive mappings;
- expected Week 8 margin effects;
- golden expected outputs.

## Sources Found

The authoritative sources confirm the mechanism exists.

### Constants Ledger

`halden-constants-ledger.md` names the second cross-cohort market window:

```text
Week 6 Gulf Coast capacity additions -> Week 8 GC refining margin
```

It does not provide the production curve, anchor episode, multiplier, elasticity, bounds, or expected output table for Window 2.

### Design Document

`halden-energy-design-v2.md` states that:

- Week 6 Gulf Coast capacity additions affect Week 8 regional refining margin;
- response functions are anchored on documented historical episodes;
- real elasticities and published multipliers should be carried in faculty materials;
- magnitudes are bounded around the parallel-universe baseline;
- cohort behavior is hidden during the decision week and revealed with the response.

This defines the architecture and calibration standard, but not the executable Window 2 parameters.

### Week 6 Package And Spec

The Week 6 package and `halden-week6-data-package-spec.md` supply the capital-allocation economics:

- project cash flows;
- NPV/IRR results;
- cost-of-capital schedule;
- capital envelope schedule;
- forecast haircuts;
- golden Week 6 fixtures.

They do not include the Week 6 -> Week 8 Gulf Coast refining-margin response function. The existing Batch 17A test already protects this by asserting the current Week 6 package lacks the production Window 2 parameter strings.

### Week 7 Spec

`halden-week7-data-package-spec.md` says Week 7 refining capacity responses can compound the Week 6 Window 2 overbuild. It also fully specifies the separate Week 7 -> Week 9 retail response function.

This is adjacent context, not the missing Week 6 -> Week 8 response function.

### Week 8 Spec

`halden-week8-data-package-spec.md` defines the OPEC-shock propagation mechanics:

- Brent-WTI spread relationship;
- upstream realization movement;
- refining crack compression per crude move;
- retail pump pass-through and short-run elasticity;
- three Week 8 OPEC scenarios and weights.

It references Week 6 balance-sheet position as Week 8 context, but it does not define the Week 6 capacity-addition response function that adjusts the Week 8 Gulf Coast refining margin.

### Calibration Review

`halden-calibration-review.md` identifies the Week 8 refining crack-crude coefficient as high-priority to verify. It does not provide Window 2 capacity-addition response parameters.

## Reconciliation Result

Known:

- Trigger: Week 6 Gulf Coast capacity additions.
- Target: Week 8 Gulf Coast refining margin.
- Mechanism type: cross-cohort market feedback, not an individual consequence.
- Visibility rule: hidden during Week 6, revealed with the Week 8 response.
- Design standard: documented historical anchor, real elasticity, pedagogical multiplier, bounded magnitude, parallel-universe baseline.
- Production addendum: `halden-window2-cohort-addendum/`.
- Cohort metric: share of teams funding Baton Rouge crude flexibility in Week 6.
- Pivot: `0.50`.
- Above-pivot response: `-6.0 * (share - 0.50)`.
- Below-pivot response: `+3.0 * (0.50 - share)`.
- Response range reached by canonical cohort states: `-3.00` to `+1.50` $/bbl.
- Integration rule: `Week 8 crack = 21.50 + (-0.35 * ΔWTI) + Window 2 shift`.

Still deferred:

- anchor episode;
- real elasticity;
- Week 7 compounding term;
- seven-week variant use, which remains explicitly excluded.

## Implementation Decision

The existing Batch 17A runtime boundary remains correct and is now populated by the addendum:

```text
CapitalAllocationDecision
    -> CohortDecisionAggregate
    -> CohortFeedbackEffect
    -> Week 8 runtime context
```

## Deferred

Still deferred:

- Week 7 compounding of Window 2 overbuild;
- Window 1 and Window 3 cohort feedback runtime;
- seven-week variant cohort-window behavior;
- Week 8 what-if expansion for the cohort adjustment.
