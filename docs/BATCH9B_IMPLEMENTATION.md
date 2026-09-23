# Batch 9B Implementation

Batch 9B adds the Week 4 to Week 6 discount-rate consequence framework. This is separate from cohort feedback.

## Architecture

```text
Week 4 Economic Resolution
        |
        v
DiscountRateConsequenceService
        |
        v
DiscountRateSchedule
        |
        v
DiscountRateConsequence
        |
        v
Week 6 Capital Allocation Context
```

This is a company-performance consequence channel, not a room-level market feedback mechanism.

## Schedule Model

`DiscountRateSchedule` stores:

- key
- name and description
- version
- source week number
- target week number
- configurable classification rules
- classification outcomes
- active flag

The schedule can store the ledger outcomes:

- disciplined: `6.5%`, `$1,520M`
- base: `8.5%`, `$1,150M`
- lax: `11.0%`, `$950M`

Batch 9B does not invent authoritative thresholds. Thresholds are configurable rules.

## Consequence Model

`DiscountRateConsequence` stores:

- tenant
- section simulation
- source runtime week
- target runtime week
- team simulation and team
- source economic resolution
- schedule key/version
- status
- classification
- discount rate
- capital envelope
- input snapshot
- result snapshot
- resolver and timestamp

Records are immutable once created.

## Unresolved Rules

If classification rules are missing or no rule matches, the service creates an `unresolved` record with no discount rate or capital envelope. This preserves auditability without guessing.

## Separation

The service does not create:

- cohort feedback aggregates
- cohort feedback effects
- KPI snapshots
- ranking snapshots

It also does not alter the economic engine or ranking service.

## Deferred

The following remain out of scope:

- authoritative Week 4 discipline thresholds
- Week 6 capital allocation mechanics
- student/faculty UI for capital allocation
- automatic grading or scoring impact
