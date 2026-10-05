# Batch 34A: KPI/Consequence Package and Week 10 Integrity

## Source

This batch implements the two high-priority integrity fixes from `SAKSHI-AUDIT-NOTE-2026-10-04.md`:

- Week 10 inherited constraints are derived from evaluated historical state and immutable consequences, not student-entered values.
- Week 4 to Week 6 discount rate/capital envelope is a section-level cohort mechanism, not a placeholder or team-specific classification.

The authoritative package is `halden-kpi-consequence-package` version `1.0.1`.

## Package Ingestion

The KPI/consequence package is loaded through the existing package/content architecture rather than a new package system. The reference reader validates the package provenance and exposes:

- `data/state_opening.csv`
- `data/kpi_rules.csv`
- `data/consequence_catalog.csv`
- package reference fixtures, including consequence-resolution examples when present

The corrected `halden-week10-data-package` is version `1.0.1` and remains the authoritative Week 10 economic package.

## Derived Week 10 Constraints

Week 10 no longer accepts these inherited constraints from student submissions:

- `br_reported_margin_strong`
- `cancellable_capex_musd`
- `crude_hedge_coverage`
- `cash_cushion_musd`

The Week 10 inherited-state assembler now reads consequence links and standing state:

| Week 10 dependency   | Source                                                                            |
| -------------------- | --------------------------------------------------------------------------------- |
| Delacroix cover      | `week4_tp_delacroix_cover` consequence                                            |
| Crude hedge coverage | `week5_hedge_coverage` consequence derived from Whitaker standing                 |
| Cancellable capex    | `week6_cancellable_capex_musd` consequence derived from Week 6 capital evaluation |
| Cash cushion         | `week8_cash_cushion_musd` consequence derived from prior evaluated state          |
| Straits Pacific flex | Standing model; strained/hostile binds                                            |

Missing prior state leaves Week 10 in `unresolved_dependency`; no default or client-submitted fallback is accepted.

## Consequence Chain

### Delacroix Cover

`week4_tp_delacroix_cover` is created from the Week 4 economic resolution. Refining segment margin above target sets cover to `1`; below target sets cover to `0`.

### Whitaker to Hedge Coverage

Week 4 transfer-pricing posture sets Whitaker standing. That qualitative standing maps to Week 10 hedge coverage through the authoritative catalog:

- cooperative/obliged: `0.70`
- watchful/guarded: `0.45`
- strained/hostile: `0.25`

### Cancellable Capex

`week6_cancellable_capex_musd` is derived from the Week 6 capital evaluation and resolved discount-rate consequence:

```text
(capital envelope - 880 if Helix funded) * 0.30
```

Helix remains contractual and non-cancellable.

### Cash Cushion

`week8_cash_cushion_musd` is derived as:

```text
250 + 0.25 * Week 8 EBITDA effect
    - 0.10 * (Week 6 outlay + 600 * Week 7 capacity match + Week 9 rebrand cost)
```

For the seven-week variant, Weeks 7 and 9 are absent, so those terms resolve to zero without synthetic evaluations.

### Straits Pacific

The Straits Pacific Week 10 flex constraint binds only when the standing model says `strained` or `hostile`.

## Week 4 to Week 6 Cohort Classification

At Week 4 execution, the discount-rate service now evaluates finalized transfer-price decisions across the section:

- within +/-10% of `$18.70`: marginal-cost
- within +/-10% of `$73.70`: market-based
- otherwise: neither

The section state is shared by every participating team:

| Section state | Condition                  | Discount rate | Capital envelope |
| ------------- | -------------------------- | ------------: | ---------------: |
| disciplined   | at least 70% marginal-cost |          6.5% |          $1,520M |
| lax           | at least 70% market-based  |         11.0% |            $950M |
| base          | otherwise                  |          8.5% |          $1,150M |

Intermediate prices do not classify as either anchor merely because they are closer.

## Seven-Week Behavior

The seven-week variant keeps only the Week 4 to Week 6 cohort path. Window 1, Window 2, and Window 3 remain excluded. Week 10 derived constraints still come from persisted consequences and standing state; Weeks 7 and 9 contribute zero to the cash-cushion formula because they are absent.

## Visibility

The cohort classification remains hidden during Week 4 decision-making. After Week 4 is locked and Week 6 context is available, students may see the resulting rate/envelope but not peer transfer prices, aggregate percentages, or hidden response parameters.

## Tests

Focused coverage was added for:

- rejection/ignoring of client-supplied inherited Week 10 values
- derived Delacroix cover
- Whitaker standing to hedge coverage
- cancellable capex from envelope and Helix selection
- cash cushion from evaluated prior state
- Straits Pacific standing binding
- missing dependency handling
- tenant/team isolation
- disciplined/base/lax section classification
- exact 70% boundary and below-boundary cases
- +/-10% transfer-price classification bands
- seven-week exclusion of Window 1/2/3 and absent Week 7/9 cash-cushion terms

## Remaining Limitations

- The transfer-price +/-10% band is audit-approved pending formal inclusion in package `v1.0.2`.
- Week 7 capacity compounding remains explicitly out of scope.
- No new KPI redesign, consequence mappings, LLM behavior, grading logic, or deployment work was implemented in this batch.
