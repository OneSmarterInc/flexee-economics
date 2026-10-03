# Seven-Week Variant Sequence

Updated: 2026-10-03

## Authoritative Sequence

The seven-week variant is not a mechanical Weeks 1-7 truncation. It preserves selected concept weeks from the fourteen-week arc:

| Runtime concept week | Seven-week role                                                                                    |
| -------------------- | -------------------------------------------------------------------------------------------------- |
| Week 1               | Asset register, economic versus accounting profit, heavier cost-structure reading                  |
| Week 4               | Transfer pricing and the origin of the single Week 4 -> Week 6 discount-rate/capital-capacity link |
| Week 6               | Capital allocation, NPV/IRR, and folded currency exposure/natural-hedge work                       |
| Week 8               | OPEC scenario reasoning, prediction versus realization                                             |
| Week 10              | Recession convergence plus folded Norwegian union/factor-market content                            |
| Week 12              | Transition portfolio and divestment/feasibility mechanics                                          |
| Week 14              | Board defense and faculty assessment                                                               |

Dedicated fourteen-week Weeks 2, 3, 5, 7, 9, 11, and 13 are omitted as standalone runtime weeks.

## Folded Concepts

| Original concept                                  | Seven-week representation                                                                |
| ------------------------------------------------- | ---------------------------------------------------------------------------------------- |
| Cost-structure reading from early operating weeks | Folded into Week 1 asset-register analysis                                               |
| Dedicated Week 5 currency crisis                  | Folded into Week 6 through Helix Rotterdam currency exposure and natural-hedge reasoning |
| Week 13 factor markets                            | Folded into Week 10 through the Norwegian union/recession negotiation thread             |

The folded Week 6 hedge/currency state is consumed by Week 10 from persisted Week 6 evaluation state in the seven-week variant. The fourteen-week variant continues to consume persisted Week 5 economic evaluation state.

## Cohort Windows

The authoritative seven-week variant excludes the three full-arc cohort windows:

- Window 1: Week 3 European run rates -> Week 5 NWE crack.
- Window 2: Week 6 Gulf Coast capacity -> Week 8 refining margin.
- Window 3: Week 7 retail pricing -> Week 9 non-fuel margin.

The variant retains only the Week 4 -> Week 6 discount-rate/capital-capacity link. In the current implementation this is represented by the evidence-backed `DiscountRateConsequence` pathway from Week 4 economic resolution into Week 6 capital allocation context. The separate cohort aggregation threshold for disciplined/base/lax remains an open content question; no additional cohort response function is invented for this regression.

## Role Rotation

The authoritative role rotation occurs between Week 8 and Week 10:

- Weeks 1, 4, 6, and 8 belong to the first seat phase.
- Weeks 10, 12, and 14 belong to the second seat phase.

The current platform stores current seat assignment rather than effective-dated role history. The validated path preserves team identity across the rotation and captures role phase/seat metadata in versioned decision-definition snapshots so causal trace reconstruction can distinguish the decision context.

## Historical State

Week 10 consumes persisted historical state, not raw submissions:

- Week 4 `EconomicResolution` supplies Baton Rouge/Delacroix condition when explicitly present in the resolution snapshot.
- Week 6 `CapitalAllocationEvaluation` supplies cancellable capex and, for seven-week variants, folded crude hedge coverage when explicitly present in the evaluation snapshot.
- Standing state supplies Straits Pacific standing.
- Week 8 `Week8EconomicEvaluation` supplies cash cushion when explicitly present in the evaluation snapshot.

Missing historical state remains `unresolved_dependency`.
