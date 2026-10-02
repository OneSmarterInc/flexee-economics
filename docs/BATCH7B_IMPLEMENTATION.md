# Batch 7B Implementation

Batch 7B adds the faculty causal trace query foundation. It does not add a visual trace UI, LLM interpretation, what-if tooling, or prepared teaching moments.

## Architecture

The read-side trace layer is:

Historical simulation records
-> CausalTraceService
-> Typed trace nodes

`CausalTraceService` assembles stored history. It does not infer missing consequences or generate narrative interpretation.

## Supported Trace Directions

Batch 7B supports:

- forward trace from a decision submission
- backward trace from a consequence link

Forward answers: what did this decision affect?

Backward answers: which prior records explain this consequence?

## Typed Nodes

Trace nodes are explicit DTOs:

- `DecisionNode`
- `EconomicNode`
- `KpiNode`
- `RankingNode`
- `StandingNode`
- `ConsequenceNode`
- `AdvisorNode`

This keeps the faculty layer explainable without introducing a generic graph engine.

## Included Historical Sources

The service can include existing records from:

- decision submissions
- economic resolutions
- KPI snapshots
- ranking snapshots
- standing events triggered by the decision
- consequence links sourced from the economic resolution
- advisor consultations from the same team/week context

Nodes are returned in deterministic order by trace stage and model id.

## Security

The causal trace is a faculty/admin foundation only.

Students are denied even for their own team. Faculty must be assigned to the section simulation's section, and all trace requests are tenant-scoped.

## Deferred Features

The following remain intentionally out of scope:

- visual causal trace UI
- cross-week future-constraint interpretation beyond stored links
- LLM interpretation
- faculty what-if console
- prepared teaching moments
- student-facing causal trace
