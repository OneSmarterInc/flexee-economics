# Step 5 - Form Definition Versioning

Current checkpoint before this change: `e6fce6da7df48cfe6268612b475295f70a9ff01f`.

## Purpose

Step 5 verifies that a submitted decision can be reconstructed from the decision context that existed at submission time:

```text
Simulation run -> exact form definition version -> available alternatives -> submitted decision
```

The goal is historical reconstruction, not new economics.

## Versioning Audit

Before this change, `decision_submissions` and `decision_submission_revisions` stored:

- the `decision_form_definition_id`;
- the submitted answer payload;
- revision metadata.

That preserved the definition row relationship, but it did not freeze the field labels, validation rules, or option sets that were available when the team submitted. If a definition row or field row changed later, the historical answer could become harder to interpret.

## Implementation

The submission flow now stores a `definition_snapshot` on both:

- `decision_submissions`;
- `decision_submission_revisions`.

The snapshot is created by `DecisionDefinitionSnapshotter` and includes:

- definition ID, ULID, key, name, version, status, and metadata;
- ordered field definitions;
- field validation rules;
- select/radio options;
- submitted answers;
- reconstructed available alternatives;
- not-selected options for option fields.

`SubmissionService` captures the snapshot during draft save and final submit. Once a decision is submitted, the existing immutability guard also protects the snapshot from mutation.

## Causal Trace

`CausalTraceService` now prefers the stored historical snapshot when creating `DecisionNode` payloads. Older rows without a stored snapshot fall back to the current definition relationship for backward compatibility.

The decision node payload now exposes:

- `definition`;
- `definition_version`;
- `definition_snapshot`;
- `available_alternatives`;
- submitted answers.

This allows faculty causal trace views to reconstruct what the team could choose at the time of the decision.

## Multi-Field Decisions

The snapshot supports multiple fields in one decision form. Tests cover both:

- an option/radio field with available alternatives;
- a decimal field with validation bounds and units.

## Security

This change does not alter authorization. Causal trace remains faculty/admin scoped through the existing section and tenant checks. Student-facing access remains limited to the student's own team workflow.

## Deferred

This step intentionally does not add:

- seven-week end-to-end execution tests;
- Window 1/2/3 changes;
- new economics;
- new KPI mappings;
- changes to Week 1-13 golden fixtures;
- seven-week variant execution.
