# Batch 8A Implementation

Batch 8A adds the first faculty-facing causal trace surface. It exposes the existing trace service through a Livewire page without adding graph visualization, LLM interpretation, what-if behavior, or prepared teaching moments.

## Architecture

The implemented flow is:

Faculty Causal Trace Page
-> FacultyCausalTrace Livewire component
-> CausalTraceService
-> Typed trace nodes

The page is read-only. It does not create or mutate trace records.

## Faculty Page

The route is:

`faculty/causal-trace`

The page lets authorized faculty/admin users filter by:

- section simulation
- team
- runtime week
- source type

Batch 8A supports source types:

- decision
- consequence

## Trace Presentation

The trace is rendered as an explainable timeline of typed nodes. Each node shows:

- node type
- label
- runtime week reference
- stored payload fields

This keeps the first faculty surface auditable and easy to validate before any graph visualization is introduced.

## Permissions

Students are denied.

Faculty can only see assigned sections. Administrators can see tenant-scoped section simulations.

## Deferred Features

The following remain intentionally out of scope:

- visual graph layout
- LLM interpretation
- prepared teaching moments
- faculty what-if console
- automatic narrative generation
