# Week 12 Implementation Plan

Batch 26A readiness result: **READY**.

This plan is for a future Week 12 economic engine batch. Batch 26A does not implement the engine, persistence, runtime execution, KPI/ranking, standing, consequences, what-if support, UI, or LLM behavior.

## Intended Architecture

```text
halden-week12-data-package
        ↓
Week12ReferencePackage
        ↓
Week12EconomicInputs
        ↓
Week12EconomicEngine
        ↓
Week12EconomicResult
        ↓
Golden fixture tests
```

The future engine should follow the same pattern as Weeks 5, 8, 9, 10, and 11:

- keep package data in the package reader;
- keep formulas in the deterministic engine;
- keep persistence out of the result object;
- validate against the golden fixture before runtime integration.

## Package Inputs

The future reference reader should load only canonical package artifacts:

- `data/envelope.csv`
    - `total_envelope`
    - `sustaining_floor`
- `data/buckets.csv`
    - bucket key
    - floor
    - ceiling
- `data/projects.csv`
    - project key
    - bucket
    - cost
    - base NPV
    - carbon sensitivity
    - demand sensitivity
- `data/carbon_scenarios.csv`
    - carbon scenario key
    - carbon price
- `data/demand_scenarios.csv`
    - demand scenario key
    - demand code
- `data/worked_example_projects.csv`
    - package worked-example inputs
- `fixtures/week12_golden.json`
    - expected outputs
    - ordering assertions
    - tolerance

Do not hard-code project costs, bucket limits, scenario values, or fixture outputs in PHP classes.

## Package-Defined Relationships

The manifest defines the scenario NPV model:

```text
NPV = base + carbon_sensitivity × (carbon - 40) + demand_sensitivity × demand_code
```

The future engine should use this package-defined formula and validate it against workbook, notebook, faculty solution, and golden fixture outputs.

Known package values include:

- total envelope: `$1,800M`;
- sustaining floor: `$600M`;
- discretionary envelope: `$1,200M`;
- adjacent-transition ceiling: `$1,200M`;
- Helix Rotterdam cost: `$1,200M`;
- Euro retail divestment proceeds: `$550M`;
- Helix Rotterdam plus offshore wind cost: `$1,750M`;
- envelope with divestment: `$1,750M`.

## Expected Outputs

The future result object should include, at minimum:

- engine identifier and version;
- package version;
- input snapshot;
- output snapshot;
- scenario NPV table;
- project sign-swing classification where package-supported;
- portfolio feasibility results;
- bucket feasibility results;
- divestment-unlocked portfolio count;
- worked-example outputs;
- status.

Golden output targets include:

- `discretionary = 1200.0`;
- `feasible_portfolios = 17`;
- `feasible_with_helix_rotterdam = 3`;
- `portfolios_unlocked_by_divest = 2`;
- `helix_rotterdam_cost = 1200.0`;
- `adjacent_ceiling = 1200.0`;
- `hr_plus_wind_cost = 1750.0`;
- `envelope_with_divest = 1750.0`;
- `hr_plus_wind_needs_divest = true`.

## Numeric Precision

Use decimal-safe arithmetic. Match the package tolerance unless a later Week 12 package revision says otherwise:

- relative tolerance: `1e-3`;
- absolute tolerance: `1e-5`.

Avoid binary float comparisons in engine parity tests.

## Testing Plan

The future Week 12 engine batch should add focused tests for:

- reference package loading;
- provenance/hash preservation;
- scenario NPV calculations;
- worked-example parity;
- project sign-swing assertions;
- portfolio enumeration;
- bucket and envelope feasibility;
- divestment-unlocked portfolios;
- golden fixture parity;
- determinism;
- decimal precision.

Runtime integration should be a separate later batch after engine parity passes.

## Non-Blocking Questions

- The exact application decision-form fields for selecting Week 12 portfolios are not modeled yet.
- The exact KPI/ranking mapping for Week 12 outputs is not defined in the readiness gate.
- The exact consequence and standing effects of transition portfolio choices remain deferred.

These do not block the engine because the package contains the economic inputs, formula, and golden outputs needed for deterministic engine implementation.

## Deferred Functionality

Deferred until later batches:

- Week 12 economic engine implementation;
- Week 12 runtime evaluation persistence;
- `WeekExecutionService` integration;
- student/faculty Week 12 UI refinements;
- KPI/ranking integration;
- standing changes;
- consequence links;
- what-if support;
- LLM interpretation.
