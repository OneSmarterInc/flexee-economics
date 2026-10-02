# Step 3 — Week 3/7 Runtimes and Windows 1/3

## Source Authority

Implemented from the authoritative packages:

- `halden-week3-data-package/`
- `halden-week7-data-package/`

Read sources included each package `MANIFEST.md`, `VALIDATION_16A.md`, canonical CSVs, faculty solution material, and golden fixtures. No formulas were inferred from the scenario names.

## Week 3 Runtime

Added:

- `Week3ReferencePackage`
- `Week3EconomicInputs`
- `Week3EconomicEngine`
- `Week3EconomicResult`
- `Week3EconomicEvaluationService`
- `Week3EconomicEvaluation`

The engine reproduces the package shutdown-point calculations:

- refinery contribution;
- refinery net;
- shutdown crack;
- Rotterdam idle delta;
- Window 1 NWE crack states.

Runtime execution now evaluates submitted Week 3 decisions and stores immutable Week 3 evaluations.

## Window 1

Added `Window1CohortResponseFunctionCatalog`.

The response uses the package-backed relationship:

```text
NWE crack = base_crack + slope * (base_util - cohort_utilization)
```

Registered through the existing `CohortResponseFunction` and resolved by `CohortFeedbackService`.

The effect is:

```text
Week 3 European utilization
        ↓
Week 5 NWE refining crack handoff
```

The effect remains excluded from seven-week variants.

## Week 7 Runtime

Added:

- `Week7ReferencePackage`
- `Week7EconomicInputs`
- `Week7EconomicEngine`
- `Week7EconomicResult`
- `Week7EconomicEvaluationService`
- `Week7EconomicEvaluation`

The engine reproduces the package competitive-response calculations:

- cluster at-risk percentage;
- match cost;
- ignore cost;
- retail decision;
- capacity-game EVs;
- breakeven build probability;
- Window 3 non-fuel margin states.

Runtime execution now evaluates submitted Week 7 decisions and stores immutable Week 7 evaluations.

## Window 3

Added `Window3CohortResponseFunctionCatalog`.

The response uses the package-backed relationship:

```text
non-fuel margin = base_nonfuel + slope * (pivot - cohort_aggression)
```

Registered through the existing `CohortResponseFunction` and resolved by `CohortFeedbackService`.

The effect is:

```text
Week 7 retail pricing aggression
        ↓
Week 9 Cordell non-fuel margin handoff
```

The effect remains excluded from seven-week variants.

## Week 5 / Week 9 Handoffs

Week 5 and Week 9 engines were not rewritten.

Their evaluation services now record cohort handoff snapshots when effects are available:

- Week 5 stores `nwe_crack_handoff`;
- Week 9 stores `nonfuel_margin_handoff`.

This preserves base/effect/final values as historical context without inventing additional economics.

## Deferred

- Week 2 implementation.
- Full seven-week end-to-end variant execution.
- Form-versioning overhaul.
- Window 2 economic changes.
- KPI/ranking mappings for Weeks 3 and 7.
- Consequence links from Weeks 3 and 7 beyond the authoritative cohort effects.
