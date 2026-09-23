# Batch 8C Implementation

Batch 8C adds the faculty interpretive assistant foundation. It does not add a final assistant UI or make external LLM calls. The batch creates the durable boundary, context payload, queue job, and immutable storage needed for a future provider-backed assistant.

## Architecture

```text
Faculty Request
        |
        v
InterpretiveAssistantService
        |
        v
FacultyInterpretationContextBuilder
        |
        v
InterpretationRequest
        |
        v
GenerateInterpretationJob
        |
        v
InterpretiveAssistantProvider
        |
        v
InterpretationResult
```

Controllers and components should not call an LLM directly. They should create an interpretation request through `InterpretiveAssistantService`; the queued job owns provider execution.

## Context Payload

The context builder creates a versioned structured snapshot containing:

- team and runtime week
- decisions
- memos
- economic resolutions
- KPI snapshots
- ranking snapshots
- standing states
- standing events
- consequence links
- advisor consultations
- what-if alternatives

The request stores both `context_version` and `context_hash` so future results can be tied to the exact context the provider received.

## Stored Records

`InterpretationRequest` stores:

- tenant
- section simulation
- runtime week
- team
- requester
- focus
- context version/hash
- prompt version
- full context snapshot
- requested timestamp

`InterpretationResult` stores:

- tenant
- request reference
- provider/model
- prompt version
- context hash
- response text
- response snapshot
- generated timestamp

Both request and result records are immutable.

## Provider Boundary

Batch 8C binds `InterpretiveAssistantProvider` to a deterministic structured placeholder provider. This preserves the integration seam without introducing real LLM behavior or external network dependency.

The placeholder response explicitly avoids:

- grading decisions
- ranking teams
- replacing instructor judgment

## Permissions

Only administrators and faculty assigned to the section simulation can request interpretations. Students are denied.

## Deferred

The following remain out of scope:

- final assistant UI
- external LLM/API configuration
- streaming responses
- prepared teaching moments
- grading recommendations
- autonomous judgments
- student-facing assistant access
