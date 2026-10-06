# Batch 34E - Reference Team Runtime Reconciliation

## Status

Batch 34E reconciles the reference-team end-to-end runtime test with the authoritative KPI/consequence package boundary.

No commit or push was made during reconciliation.

## Authoritative Sources

Read and reconciled:

- `HANDOFF-README.md`
- `SAKSHI-BATCH-NOTE.md`
- `SAKSHI-AUDIT-NOTE-2026-10-04.md`
- `SAKSHI-AUDIT-FOLLOWUP-2026-10-05.md`
- `halden-week8-data-package/MANIFEST.md`
- `halden-week8-data-package/fixtures/week8_golden.json`
- Week 8 canonical CSVs
- `halden-kpi-consequence-package/MANIFEST.md`
- `halden-kpi-consequence-package/data/reference_team_decisions.csv`
- `halden-kpi-consequence-package/data/reference_team_inputs.csv`
- `halden-kpi-consequence-package/data/input_dictionary.csv`
- `halden-kpi-consequence-package/data/consequence_catalog.csv`
- `halden-kpi-consequence-package/data/consequence_resolution.csv`

`SAKSHI-KPI-CONSEQUENCE-NOTE.md` and `SAKSHI-DECISIONS-NOTE.md` were not present as standalone files in the repo, source folder, or handoff zip during this pass. The package manifest and audit notes contain the relevant current instructions.

## Reference-Team Fixture Meaning

The KPI/consequence package manifest explicitly states:

```text
Reference team inputs for Weeks 2, 5, 8, 11, 12, 13 are synthetic test paths chosen to exercise the rules. They are fixtures for the scoring engine, not economic claims.
```

Therefore `reference_team_inputs.csv` is the authoritative oracle for the package-backed KPI/consequence state engine. It is not, by itself, proof that every field is a runtime output of the corresponding economic engine.

## Week 8 Runtime Boundary

The Week 8 package golden fixture pins:

- OPEC scenario probabilities;
- resolved WTI values;
- propagation coefficients;
- upstream realization;
- refining crack response;
- retail pass-through and elasticity;
- integration-conflict ordering.

The Week 8 package does not define an EBITDA-effect formula that maps:

```text
Week8EconomicEvaluation
        ->
i8_ebitda_effect_musd
```

The KPI/consequence package defines `i8_ebitda_effect_musd` as:

```text
Week 8 engine EBITDA effect including hedges and positioning, excluding window 2
```

but no authoritative runtime mapping from the current Week 8 OPEC package output to that EBITDA value was found.

## 228.6 vs 182.425

### 228.6

Source:

- `halden-kpi-consequence-package/data/reference_team_inputs.csv`
- `halden-kpi-consequence-package/data/consequence_resolution.csv`
- `halden-kpi-consequence-package/fixtures/kpi_consequence_golden.json`

Meaning:

The disciplined reference-team scoring fixture uses:

```text
i8_ebitda_effect_musd = 600.0
i6_outlay_musd = 1520.0
i7_capacity_matched = 0
i9_rebrand_cost_musd = 194.0

cash = 250 + 0.25 * 600 - 0.10 * (1520 + 600 * 0 + 194)
     = 228.6
```

This is the KPI/consequence package's scoring-fixture path.

### 182.425

Source:

- actual `Week8EconomicEvaluation` created by `WeekExecutionService`;
- `DerivedWeek10ConstraintService`;
- actual runtime Week 6/7/9 persisted state.

Meaning:

The runtime cash-cushion consequence uses the current persisted Week 8 OPEC evaluation output as its Week 8 EBITDA effect input. That persisted runtime output is scenario-based and is not the synthetic `600.0` reference-team scoring input.

## Test Oracle Split

Batch 34E uses two distinct oracles:

### Package Parity

`reference_team_inputs.csv` is fed into `KpiFinancialStateService::fromInputs()` and then into `KpiCalculationService`. This asserts exact parity with `kpi_consequence_golden.json`.

### Runtime Wiring

`reference_team_decisions.csv` is submitted through the actual student/faculty runtime:

```text
DecisionSubmission
        ->
WeekExecutionService
        ->
Economic Evaluation
        ->
ConsequenceLink / CohortFeedbackEffect
        ->
KpiSnapshot
        ->
RankingSnapshot
```

The runtime test asserts completion, persisted evaluations, provenance, KPI/ranking availability, historical-state consumption, tenant/team isolation, and idempotency. It does not assert equality between synthetic scoring inputs and runtime economic outputs where no authoritative mapping exists.

## Local Runtime Fixes Reviewed

### Consequence definition catalog

Kept.

Reason: active consequence definitions can be registered first from generic catalog paths, then requested through typed helpers such as `week4_whitaker_standing`. The typed helpers must refresh source/target morph types so immutable consequence links validate against the actual persisted source and target models.

### Week 6 zero-project allocation

Kept.

Reason: the authoritative reference-team decisions include a team that funds no Week 6 projects. The runtime should support an explicit allocation decision with an empty selected portfolio and rejected alternatives, producing a calculated zero-portfolio evaluation rather than an unresolved/missing historical state.

### Week 9 no-rebrand decision

Kept.

Reason: the authoritative reference-team decisions include a zero-cost/no-rebrand case. The runtime now distinguishes an explicit all-false/no-market decision from a missing decision submission.

### Week 9 zero payback

Kept.

Reason: an explicit no-rebrand decision has zero cost and zero gain. The engine returns deterministic zero payback for the zero/zero case while preserving the positive-gain requirement for non-zero portfolios.

## Geneva Regression

The reference-team runtime test preserves the Batch 34C Geneva behavior:

```text
marginal-cost -> capture 0
market-based  -> capture 0
lazy midpoint -> capture 9.625
```

That behavior flows into Whitaker standing and hedge coverage through persisted runtime consequences.

## Seven-Week Boundary

No seven-week sequence changes were made in this reconciliation. Window 1, Window 2, and Window 3 remain excluded from the seven-week variant; the retained seven-week cohort path remains Week 4 -> Week 6 discount-rate/capital envelope.

## Remaining Limitation

No authoritative mapping was found between the current Week 8 OPEC runtime outputs and the KPI/consequence package's synthetic `i8_ebitda_effect_musd` reference-team input.

No mapping was invented.
