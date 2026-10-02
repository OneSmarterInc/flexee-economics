# Batch 6A Implementation

Batch 6A adds the standing model foundation. It does not connect Week 4 decisions to standing consequences, and it does not implement causal trace, consequence links, advisor consultations, leaderboard UI, or publication behavior.

## Architecture

The standing foundation is:

TeamSimulation
-> StandingState
-> StandingEvent history

Standing is qualitative and relationship-specific. It is scoped to a tenant team simulation and counterparty.

## Counterparties

`CounterpartyCatalog` defines the initial Halden counterparties as data records rather than hard-coding them into business logic:

- Delacroix
- Vestergaard
- Kuhn
- Whitaker
- Straits Pacific
- Tetteh
- Board
- Unions

## Standing States

`standing_states` stores the current qualitative state for a team/counterparty pair.

Allowed states are:

- cooperative
- guarded
- strained
- watchful
- obliged

No numeric standing score is stored or exposed.

## Standing History

`standing_events` records every initialization or transition with:

- old state
- new state
- reason
- optional triggering model reference
- optional runtime week
- timestamp

Standing events are immutable once created.

## Service Boundary

`StandingService` owns standing initialization, transitions, student-safe view shaping, and access checks.

It does not apply Week 4 transfer-price effects yet. Those consequence rules remain blocked until authoritative thresholds exist.

## Student Visibility

The student-facing shape includes:

- qualitative state
- reason
- history

It excludes numeric scores and hidden internal calculations.

## Deferred Features

The following remain intentionally out of scope:

- Week 4 standing consequence mapping
- consequence links
- causal trace
- advisor consultations
- faculty what-if tools
- leaderboard UI
- publication rules
- standing-dependent Week 10 execution
- LLM
