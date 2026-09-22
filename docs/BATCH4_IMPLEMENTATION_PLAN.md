# Batch 4 Implementation Plan

Inventory date: 2026-09-22.

## Readiness decision

BLOCKED.

Batch 4 Week 4 economics cannot be implemented safely yet because the authoritative Week 4 specification, Week 4 reference package, development instructions, and calibration review are missing.

This plan maps the expected destination for Week 4 elements once the missing sources are supplied. It does not define formulas beyond constants-ledger relationships and does not implement economics.

## Current platform anchors

- Batch 1 provides tenants, institutions, courses, sections, enrollments, teams, seats, policies, and demo seed data.
- Batch 2 provides reusable simulation definitions, runtime section simulations, runtime weeks, team simulations, seat assignments, lifecycle transitions, and audit events.
- Batch 3 provides generic versioned decision form definitions, memo definitions, draft/final submissions, immutable revisions, and completeness status.

## Decision definitions

Destination: Batch 3 decision definition tables:

- `decision_form_definitions`
- `decision_field_definitions`

Blocked mapping:

- Exact Week 4 student decision fields are unknown because `halden-week4-data-package-spec.md` is missing.
- Allowed values, validation rules, field-level seat permissions, units, help text, and ordering are unknown.

Implementation rule once sources arrive:

- Encode Week 4 fields as versioned definitions tied to the Week 4 `simulation_weeks` row.
- Preserve source metadata for every field and option.
- Do not hard-code fields in Vue, controllers, or Eloquent event hooks.

## Memo

Destination: Batch 3 memo definition tables:

- `memo_definitions`
- `memo_submissions`
- `memo_submission_revisions`

Blocked mapping:

- Exact Week 4 memo prompt, structure, rubric, word/character limits, and scoring relationship are unknown.

Implementation rule once sources arrive:

- Store the memo prompt as a versioned memo definition.
- Keep memo submission separate from deterministic economic outputs and scoring.

## Data package

Destination options already present or expected:

- `week_content_versions` for source/package metadata.
- `generated_artifacts` for generated or distributed artifacts.
- Future storage path or manifest model if development instructions require one.

Blocked mapping:

- Student datasets, package manifest, file names, columns, and visibility rules are unknown because the Week 4 package is missing.

Implementation rule once sources arrive:

- Store package metadata and checksums.
- Treat package reference outputs as test oracles, not mutable app-generated truth.
- Do not silently rewrite reference outputs.

## Economic engine

Proposed destination:

- `app/Domain/Economics/Week4/`

Expected shape:

- immutable input DTOs for submitted decisions and package data
- immutable configuration object sourced from the constants ledger and Week 4 package/spec
- deterministic calculator services
- explicit result DTOs for economic outputs
- explicit failure types for missing inputs, invalid package data, and ledger/spec conflicts

Keep calculations out of:

- controllers
- Vue components
- Livewire components
- Eloquent model event hooks

Blocked mapping:

- Exact calculation stages, package inputs, formulas, and rounding points are unknown.

## Offline Python artifacts

Blocked.

The handoff says the missing development instructions define the Python-offline/Laravel-runtime split for synthetic paths and response functions. No Python files or notebooks were found in Week 4 or Week 10 packages because those packages are missing.

Implementation rule once sources arrive:

- Only generate offline artifacts in Python if explicitly required by the development instructions or package manifest.
- Laravel should consume versioned generated artifacts rather than recreate Python-only synthetic path generation at request time.

## Results

Keep these concepts separate:

1. economic outputs
2. KPIs
3. score inputs
4. scores
5. ranks
6. published visibility state

Blocked mapping:

- Which Week 4 outputs become KPIs, score inputs, and ranking dimensions is unknown.

Implementation rule once sources arrive:

- Implement deterministic economic outputs first.
- Map outputs to KPIs only where the spec says to.
- Map KPIs to scores only where the scoring/rubric source says to.
- Rank only after score publication rules are known.

## Faculty visibility

Likely destinations:

- Livewire faculty tools for lifecycle/status and future cohort/faculty views.
- Future domain queries over economic outputs and causal traces.

Blocked mapping:

- Week 4 faculty-only outputs, causal trace, what-if console expectations, and interpretive assistant requirements are unknown.
- `halden-faculty-teaching-guide.md` is missing.

## Numeric handling

Recommendation pending source confirmation:

- Do not use ordinary PHP float arithmetic for golden-master economic parity.
- Use integer minor units where values are inherently fixed currency amounts.
- Use decimal arithmetic for rates, percentages, per-barrel values, and rounded outputs.
- Define rounding mode and scale per output only from the Week 4 spec/package.

Blocked:

- Exact rounding points and permitted precision are unknown.

## Failure behavior

Week 4 implementation should fail loudly when:

- package manifest/checksum does not match expected source metadata
- required input columns are missing
- submitted decision definitions do not match the Week 4 source version
- a spec/package constant conflicts with the constants ledger
- expected golden outputs drift
- numeric scale/rounding cannot be determined from sources

## Required before implementation

- `halden-development-instructions.md`
- `halden-week4-data-package/`
- `halden-week4-data-package-spec.md`
- `halden-calibration-review.md`
- Week 4 expected outputs
- precision/rounding rules
- KPI/score/rank definitions
