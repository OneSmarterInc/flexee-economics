# Batch 10A Implementation

Batch 10A adds the Week 6 capital allocation framework. It does not implement Week 6 project economics, NPV, IRR, faculty what-if tools, or leaderboard effects.

## Architecture

```text
Week 4 DiscountRateConsequence
        |
        v
CapitalAllocationContext
        |
        v
CapitalAllocationDecision
        |
        v
Selected and Rejected Projects
```

This establishes the first multi-week chain from Week 4 transfer pricing into Week 6 capital allocation context.

## Capital Project Catalog

`CapitalProject` stores:

- project key
- name
- category
- version
- cash flow reference
- risk class
- required inputs
- metadata
- active flag

Projects are data records, not hard-coded conditionals.

## Allocation Decisions

`CapitalAllocationDecision` stores:

- tenant
- section simulation
- runtime week
- team simulation and team
- discount-rate consequence reference
- submitted-by user
- selected projects
- rejected projects
- context snapshot
- memo references
- submitted timestamp

Decisions are immutable once submitted.

## Discount-Rate Integration

`CapitalAllocationService::contextFor()` reads the applicable `DiscountRateConsequence` for the team and Week 6 runtime week.

Context states:

- `available`
- `missing_discount_rate_consequence`
- `unavailable_discount_rate`

Unresolved classification is preserved safely without guessing a discount rate or capital envelope.

## Deferred

The following remain out of scope:

- Week 6 project cash flows
- NPV/IRR calculation
- Week 6 faculty what-if console
- full capital allocation UI
- KPI/ranking impact
- Week 6 downstream consequence propagation
