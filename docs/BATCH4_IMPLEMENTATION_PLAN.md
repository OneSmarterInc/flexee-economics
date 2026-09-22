# Batch 4 Implementation Plan

Inventory date: 2026-09-22.

## Readiness decision

BLOCKED.

The authoritative markdown specs are now available and reconcile enough to plan Week 4. Batch 4 Week 4 economics must still not be implemented until the worked `halden-week4-data-package/` is supplied and reconciled against the spec and ledger.

## Current platform anchors

- Batch 1 provides tenants, institutions, courses, sections, enrollments, teams, seats, policies, and demo seed data.
- Batch 2 provides reusable simulation definitions, runtime section simulations, runtime weeks, team simulations, seat assignments, lifecycle transitions, and audit events.
- Batch 3 provides generic versioned decision form definitions, memo definitions, draft/final submissions, immutable revisions, and completeness status.

## Authoritative Week 4 decision mapping

Primary student decision: internal transfer price for Permian crude delivered to Baton Rouge.

The Week 4 spec gives three anchor options:

- market-based: `$73.70/bbl`
- marginal-cost: `$18.70/bbl`
- midpoint/lazy: `$46.20/bbl`

The spec also permits an intermediate/custom value. Do not invent precision, bounds, increments, or display rules until the reference package is available.

Destination in the existing Batch 3 architecture:

- one versioned Week 4 decision form definition tied to the Week 4 simulation week
- a transfer-price field that can represent an anchor selection or custom/intermediate numeric value
- stored source metadata for anchors and definitions
- stored alternatives not taken for causal trace and LLM payloads

The app must not perform the student analytical work. Students compute integrated margin invariance, segment splits, compensation effects, and Geneva arbitrage externally in the Excel/Python package, then submit the decision and memo.

## Week 4 memo mapping

The authoritative memo structure has three sections:

1. Key assumptions
2. Analytical method and result
3. Decision and logic

Week 4-specific memo expectations:

- state assumptions about crude prices, segment-head reactions, and desk behavior
- report integrated margin, segment splits, and arbitrage analysis
- explain the transfer-price choice and how the team weighs integrated-optimal economics against political/standing cost

Faculty inconsistency flag:

- A team correctly demonstrates integrated-margin invariance but justifies its transfer-price decision as `maximizing segment margin`.

Do not implement LLM functionality in Batch 4 readiness. When implemented later, the inconsistency assist belongs behind the queued LLM service/job pattern, not in controllers.

## Deterministic economic engine boundary

Future destination after package reconciliation:

- `app/Domain/Economics/Week4/`
- immutable input DTOs from submitted decisions and package fixtures
- immutable configuration sourced from the ledger/spec/package version
- deterministic calculator services
- explicit result DTOs for deterministic outputs, faculty-only trace, and later score inputs
- failure types for missing fixtures, invalid source versions, and ledger/spec/package conflicts

Keep calculations out of controllers, Vue components, Livewire components, Eloquent model events, and Python runtime calls.

## Faculty-layer requirements

Later Stage 3 faculty tools for Week 4 must include:

- cohort transfer-price distribution
- benchmarks for marginal cost and market price
- causal trace from Week 4 to Geneva arbitrage, segment standing consequences, and later consequences
- what-if transfer-price rerun
- prepared teaching moment keyed to cohort distribution
- faculty LLM context payload

The Week 4 faculty LLM payload must preserve:

- chosen transfer price
- alternatives and anchors available but not taken
- computed integrated margin
- memo
- cohort transfer-price distribution
- incoming standing states

Faculty screens must be Livewire. Student screens must remain Inertia/Vue.

## Week 4 -> Week 6 consequence

The ledger clarifies the taxonomy:

- The fourteen-week arc has three cross-cohort market windows: Week 3 -> Week 5, Week 6 -> Week 8, and Week 7 -> Week 9.
- The Week 4 -> Week 6 transfer-pricing-discipline linkage is separate: it affects cost of capital and capital envelopes, not a cross-cohort market price.

Ledger schedule:

- disciplined: `6.5%`, `$1,520M`
- base: `8.5%`, `$1,150M`
- lax: `11.0%`, `$950M`

Do not implement this linkage until the reference package and exact aggregation/classification rule are available. The current markdown sources do not provide thresholds for mapping a cohort transfer-price distribution to disciplined/base/lax.

## Week 10 architectural implications

Week 10 proves the economic engine cannot be single-input-only. It requires:

- inherited decisions from Weeks 4-8
- binding constraints from prior allocation, mandate, standing, and cash position
- product-mix demand calculations
- standing-dependent execution
- causal trace over five converging threads
- multiple simultaneous decision fields
- prior-week state dependencies

Week 4 can have a narrow Week 4 calculator, but the shared persistence model must keep decisions, alternatives, memos, standing states, consequence links, predictions, outcomes, KPIs, score inputs, scores, and ranks separable.

## Calibration and content versioning

Current development must use supplied design calibration. The calibration review requires a final pre-launch market refresh, but that is not a Batch 4 task. Refresh inputs later; preserve the relationships that make the pedagogy work.

Role charters should be imported as versioned content per seat. Do not hard-code charter text into PHP classes.

## Numeric handling

Recommendation pending package confirmation:

- Use decimal-safe arithmetic for per-barrel values, rates, and percentages.
- Avoid PHP binary floats for parity-sensitive calculations.
- Treat tolerance as zero unless the package defines one.
- Do not choose rounding mode, intermediate rounding points, custom-price precision, or display format until the package supplies them.

## Failure behavior

Week 4 implementation should fail loudly when:

- package manifest/checksum does not match expected source metadata
- required package artifacts are absent
- required input columns are missing
- submitted decision definitions do not match the Week 4 source version
- a spec/package constant conflicts with the constants ledger
- expected golden outputs drift
- numeric scale/rounding cannot be determined from sources

## Required before implementation

- `halden-week4-data-package/`
- Week 4 package MANIFEST
- canonical CSVs
- student workbook and notebook
- faculty solution workbook/notebook
- expected outputs
- exact rounding/display conventions
- exact custom transfer-price validation constraints
- exact Week 4 -> Week 6 cohort aggregation/classification rule
