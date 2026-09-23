# Batch 8B Implementation

Batch 8B adds the faculty what-if console foundation. It gives authorized instructors a way to run counterfactual Week 4 transfer-price calculations without mutating the simulation record.

## Architecture

```text
Historical Economic Resolution
        |
        v
Faculty What-if Request
        |
        v
Week4WhatIfSimulationService
        |
        v
Week4EconomicEngine
        |
        v
WhatIfRun
```

The economic engine remains pure. What-if execution sits in a separate service and never writes economic resolutions, KPI snapshots, ranking snapshots, standing events, or consequence links.

## Stored Run Model

`WhatIfRun` stores an immutable counterfactual record:

- tenant
- section simulation
- runtime week
- team simulation and team
- source economic resolution
- requesting user
- scenario type
- `counterfactual = true`
- scenario input snapshot
- calculated output snapshot
- engine identifier and version
- request timestamp

The model validates that its source economic resolution belongs to the same tenant, team, section simulation, and runtime week. Updates and deletes are rejected so a historical faculty analysis remains interpretable.

## Week 4 Scope

The only supported scenario is `week4_transfer_price`.

The service rebuilds the original Week 4 input package from the source decision submission, applies the alternative transfer price, and calculates:

- integrated margin
- segment margins
- transfer price
- Geneva arbitrage result
- deltas from the original economic resolution

## Faculty UI

`FacultyWhatIfConsole` is a Livewire page at `faculty/what-if`.

Faculty can filter by authorized section simulation, team simulation, and source economic resolution, then run a counterfactual transfer price. Administrators can see tenant-scope section simulations. Students are denied.

## Guardrails

What-if runs are explicitly not real simulation results.

They do not create or update:

- economic resolutions
- KPI snapshots
- ranking snapshots
- standing events
- consequence links

## Deferred

The following remain deferred:

- leaderboard or ranking UI
- graph visualization
- prepared teaching moments
- LLM interpretation
- automatic narrative generation
- standing or consequence mutation from counterfactuals
- what-if support for weeks beyond Week 4
