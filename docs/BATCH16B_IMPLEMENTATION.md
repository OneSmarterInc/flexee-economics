# Batch 16B Implementation

Batch 16B implemented the Week 6 capital economics engine against the authoritative Week 6 package.

## Source Boundary

The engine reads from:

`halden-week6-data-package/`

Runtime project values are not hard-coded in PHP. Cash flows, cohort discount rates, capital
envelopes, forecast haircuts, golden outputs, and provenance all come from the package.

## Engine Flow

```text
CapitalAllocationDecision
        |
        v
Week6CapitalReferencePackage
        |
        v
Week6CapitalEconomicInputs
        |
        v
Week6CapitalEconomicsEngine
        |
        v
CapitalAllocationEvaluation
```

## Calculations

The engine now calculates:

- project-level NPV at the applicable discount rate;
- project-level IRR;
- forecast-haircut adjusted NPV;
- selected-portfolio NPV;
- selected-portfolio IRR;
- selected capital required;
- capital-envelope feasibility.

NPV and IRR calculations use `Brick\Math\BigDecimal` and persisted values are stored as decimal
strings through the existing `CapitalAllocationEvaluation` decimal columns.

## Package Validation

Golden tests verify:

- package cash-flow loading;
- NPV parity with `fixtures/week6_golden.json` for disciplined, base, and lax cohorts;
- IRR parity with `fixtures/week6_golden.json`;
- persisted evaluation output for a Week 6 allocation decision;
- missing-package behavior remains explicit and safe.

## Deferred

Still deferred:

- Week 6 execution-pipeline integration;
- Week 6 UI;
- Week 6 KPI or ranking impact;
- Week 8 consequences;
- Week 6 faculty what-if;
- any mutation of downstream scoring or consequence history.
