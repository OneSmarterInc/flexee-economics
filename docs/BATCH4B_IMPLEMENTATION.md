# Batch 4B Implementation

Batch 4B connects final Week 4 decision submissions to the deterministic Week 4 economic engine through a reusable resolution boundary.

## Resolution Architecture

The implemented flow is:

Decision Submission
-> Week Resolution Service
-> Week 4 Economic Engine
-> Economic Resolution Record

`App\Domain\Economics\Resolution\WeekResolutionService` is the orchestration boundary. Controllers and UI components should call the service rather than invoking `Week4EconomicEngine` directly.

## Submission-To-Engine Flow

`WeekResolutionService::resolveSubmittedDecision()` accepts a `DecisionSubmission`, optional actor, and process label.

The service validates that:

- the submission is final
- the runtime week is `open`, `closed`, or `published`
- the runtime simulation week is Week 4
- the decision definition belongs to the same simulation version and week
- the submission team belongs to the runtime section simulation
- the actor, when present, belongs to the same tenant and can access the team

`Week4ResolutionInputMapper` translates stored decision answers into `Week4EconomicInputs` plus the selected transfer price. The deterministic engine remains the only place where economic formulas are applied.

## Stored Result Model

`economic_resolutions` records that a simulation week has been resolved for one team. The record stores:

- tenant
- section simulation
- runtime week
- team simulation and team
- decision submission reference
- economic engine identifier
- engine version
- input snapshot
- output snapshot
- integrated margin
- segment margins
- selected transfer price
- compensation deltas
- Geneva arbitrage outputs
- resolution actor/process/timestamp

Database numeric result columns use fixed decimal storage, not floating-point columns. Snapshots preserve the evaluated input package and submitted answer payload for historical reproducibility.

## Versioning

The Week 4 engine exposes:

- `week4_transfer_pricing`
- `week4_transfer_pricing_v1`

Every resolution stores both values, so historical results can answer which calculation produced them.

## Idempotency

The database enforces one resolution per tenant/runtime-week/team-simulation:

`tenant_id + section_simulation_week_id + team_simulation_id`

The service also locks and returns the existing resolution if the same team/week is resolved again. Replacement is intentionally not automatic; any rerun policy should be a deliberate future change.

## Student And Faculty Visibility

Batch 4B adds storage and service-level access checks only. Students may verify whether their own team has a resolution through service-backed behavior, but no final result dashboard is introduced.

Faculty/admin verification can be built on the stored resolution timestamp and engine version. Cohort views are deferred.

## Deferred Features

The following remain intentionally out of scope:

- KPIs
- leaderboard
- scoring
- ranking
- standing updates
- causal trace
- faculty tooling beyond minimal verification data
- LLM behavior
- cohort feedback
- Python generation
