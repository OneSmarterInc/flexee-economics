# Week 13 Implementation Plan

Batch 28A classifies the authoritative Week 13 package as `READY`. This plan scopes a future Week 13 economic engine batch only; it does not implement the engine.

## Future Architecture

```text
halden-week13-data-package
        ↓
Week13ReferencePackage
        ↓
Week13EconomicInputs
        ↓
Week13EconomicEngine
        ↓
Week13EconomicResult
```

The future engine should follow the package-backed pattern used for Weeks 5, 8, 9, 10, 11, and 12.

## Future Reference Reader

Create:

```text
Week13ReferencePackage
```

Responsibilities:

- load canonical CSVs;
- load `week13_golden.json`;
- expose package version and tolerances;
- expose the worked example;
- expose package source/provenance snapshots.

Do not hard-code Week 13 package values into the engine.

## Future Input Model

Create:

```text
Week13EconomicInputs
```

Likely fields from the package:

- Norwegian wage bill;
- Norwegian union wage demand;
- Norwegian tax shield;
- Permian marginal worker productivity;
- Permian loaded wage;
- Permian margin per barrel;
- turnaround labor base;
- contractor peak premium;
- delayed outage probability;
- outage cost;
- asset-health penalty points.

Only model fields present in canonical CSVs and the golden fixture.

## Future Engine Outputs

Create:

```text
Week13EconomicResult
```

Expected package-backed outputs:

- `norway_gross_cost_musd`
- `norway_after_tax_cost_musd`
- `norway_after_tax_share`
- `permian_mrp_k`
- `mrp_to_wage`
- `turnaround_peak_cost_musd`
- `delay_expected_cost_musd`
- `delay_saving_musd`
- `delay_saving_pct`
- `asset_health_penalty_pts`
- worked-example snapshot
- ordering assertion snapshot
- engine/package version metadata

## Package Formulas to Validate

The future engine should infer formulas only from canonical CSVs, faculty solution, notebook, and golden fixture.

The expected relationships include:

- Norwegian gross cost equals wage bill multiplied by wage demand.
- Norwegian after-tax cost applies the 78% tax shield, leaving a 22% company share.
- Permian MRP uses marginal worker production and margin per barrel.
- MRP-to-wage compares Permian MRP to loaded wage.
- contractor peak cost applies the 35% peak premium to base turnaround labor cost.
- delayed expected cost adds expected outage cost to off-peak labor cost.
- delay saving compares peak and delayed expected costs.

These relationships must be verified against the faculty workbook/notebook and `fixtures/week13_golden.json` before runtime integration.

## Precision

Use decimal-safe arithmetic and the package tolerance unless a future package revision says otherwise:

- relative: `1e-3`
- absolute: `1e-5`

## Future Tests

Add focused engine tests for:

- package loading and provenance;
- CSV schema and required values;
- golden fixture parity;
- worked-example parity;
- tax-shield calculation;
- Permian MRP and MRP-to-wage;
- contractor peak premium;
- delay expected cost and savings;
- asset-health penalty output as package data only;
- determinism and decimal safety.

## Deferred Beyond Engine

- `Week13EconomicEvaluation` persistence;
- `WeekExecutionService` integration;
- KPI/ranking integration;
- asset-health state mutation;
- standing transitions;
- consequence links;
- faculty what-if for timing and tax-shield alternatives;
- LLM context expansion;
- student/faculty UI refinement.

## Open Implementation Questions

- Which exact decision fields should the application collect for Week 13 runtime submission?
- Should the future engine evaluate all package-defined options regardless of submitted choice, or only the submitted strategy plus available alternatives?
- Which Week 13 outputs, if any, become KPI values once the asset-health mechanic exists?
- What standing transitions, if any, should Norwegian union decisions create?
- What consequence links, if any, should turnaround timing or asset-health penalty create?
