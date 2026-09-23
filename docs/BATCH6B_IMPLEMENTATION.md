# Batch 6B Implementation

Batch 6B adds the consequence link framework. It creates durable causal records but does not apply Week 4 consequence rules, advisor consultations, causal trace UI, what-if tools, or LLM behavior.

## Architecture

The consequence foundation is:

Decision or event
-> ConsequenceLink
-> Future state or constraint

`ConsequenceService` owns link creation, validation, and retrieval. Controllers, `WeekResolutionService`, and `StandingService` do not own consequence-link logic.

## Definition Model

`consequence_definitions` stores versioned consequence templates:

- key
- name
- description
- source type
- target type
- effect type
- version
- active flag
- metadata

Definitions describe what kind of causal link is being recorded. They are not scores.

## Link Model

`consequence_links` stores immutable causal instances for a team simulation:

- tenant
- section simulation
- team simulation
- team
- source runtime week
- source entity
- target runtime week
- target entity
- consequence definition
- definition key/version snapshot
- effect type
- explanation
- metadata
- actor
- timestamp

The definition key/version are copied onto each link so historical causal records remain interpretable if later definitions evolve.

## Query Direction

The service supports both trace directions needed by future faculty tools:

- forward: what did this decision or event affect?
- backward: why is this team constrained now?

## Guardrails

Consequence links validate:

- source and target model types match the definition
- runtime weeks belong to the team simulation
- source and target entities belong to the same tenant/team context when those fields exist
- actors belong to the same tenant

Links are immutable once created.

## Deferred Features

The following remain intentionally out of scope:

- actual Week 4 consequence mapping
- standing transition rules from economic outcomes
- advisor consultations
- causal trace UI
- faculty what-if console
- LLM
- Week 10 execution constraints
