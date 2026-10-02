# Batch 27A Implementation

Batch 27A reconciles Week 11 and Week 12 economic outputs against the existing consequence framework. It does not implement consequence links because the current authoritative sources do not define downstream target states, target weeks, standing transitions, KPI mappings, or future runtime consumers.

## Status

Documentation/reconciliation only.

## Consequence Inventory

The full inventory is recorded in:

```text
docs/WEEK11_WEEK12_CONSEQUENCE_INVENTORY.md
```

## Confirmed Rules

No Week 11 or Week 12 consequence rule is confirmed for implementation in the current package set.

The package-backed outputs remain economic evaluation records:

- `Week11EconomicEvaluation`
- `Week12EconomicEvaluation`

## Week 11 Findings

The authoritative Week 11 package supports:

- realized Kessana price;
- profit oil;
- annuity factor;
- take-grid company margins;
- PV of staying at current/mid/demanded/harsh take levels;
- exit value;
- stay-minus-exit;
- indifference take;
- comparable fiscal-term range;
- sunk-capital invariance.

The sources do not define:

- a future operating constraint;
- a Tetteh/Kessana standing transition;
- a future economic input;
- a KPI mapping;
- a target future week for consequence consumption.

## Week 12 Findings

The authoritative Week 12 package supports:

- selected/rejected/available portfolio projects;
- discretionary and divestment-adjusted envelope;
- bucket feasibility;
- selected-portfolio feasibility;
- divestment unlock behavior;
- scenario NPV ranges;
- feasible-portfolio counts;
- Helix/offshore-wind/divestment interlock.

The sources do not define:

- future capability effects from selected projects;
- transition standing changes;
- future capital availability beyond the Week 12 evaluation;
- a KPI mapping;
- a target future week for consequence consumption.

## Unsupported Assumptions

The following are explicitly rejected until a future authoritative source supplies them:

- Kessana fiscal decisions create numeric or qualitative standing penalties.
- Kessana stay/exit analysis creates future operating constraints.
- Week 12 project selections automatically create future operational capabilities.
- Euro retail divestment automatically affects relationship standing or retail state.
- Week 12 feasibility results map to ROACE, FCF, debt, or asset-health KPIs.
- Week 11/12 individual consequences should be modeled as cohort feedback.

## Implemented Links

None.

No `ConsequenceDefinition`, `ConsequenceLink`, resolver, standing transition, KPI mapping, cohort feedback effect, what-if behavior, LLM behavior, or UI behavior was added.

## Tests

`tests/Feature/Consequences/Week11Week12ConsequenceMappingReviewTest.php` verifies:

- the inventory document exists and records the documentation-only status;
- Week 11 package sources do not define application consequence-link targets;
- Week 12 package sources do not define application consequence-link targets;
- no Week 11/12 consequence definitions are registered by the existing catalogs;
- Week 11/12 runtime smoke tests continue to assert that execution creates no consequence links, standing changes, or cohort effects.

## Deferred Items

- authoritative Week 11 consequence mapping;
- authoritative Week 11 standing transition rules;
- authoritative Week 11 KPI mappings beyond unavailable/null snapshots;
- authoritative Week 12 consequence mapping;
- authoritative Week 12 standing transition rules;
- authoritative Week 12 KPI mappings;
- future runtime consumers for Week 11/12 outputs;
- causal trace expansion for Week 11/12 once real links exist.
