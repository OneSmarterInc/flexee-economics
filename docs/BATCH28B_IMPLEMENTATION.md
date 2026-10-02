# Batch 28B Implementation

Batch 28B adds the package-backed Week 13 factor-markets economic engine. It does not add runtime persistence, WeekExecutionService integration, KPI/ranking population, standing changes, consequence links, UI, what-if, or LLM behavior.

## Package Source

Authoritative package:

```text
halden-week13-data-package/
```

Validated artifacts:

- `MANIFEST.md`
- `VALIDATION_16A.md`
- `data/norway_union.csv`
- `data/permian_labor.csv`
- `data/turnaround.csv`
- `data/wage_benchmarks.csv`
- `data/worked_example_labor.csv`
- `halden_week13.xlsx`
- `halden_week13_analysis.ipynb`
- `faculty/halden_week13_FACULTY_SOLUTION.xlsx`
- `faculty/halden_week13_FACULTY_SOLUTION.ipynb`
- `fixtures/provenance.json`
- `fixtures/week13_golden.json`

The raw package artifacts were not modified.

## Architecture

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

The reference reader loads canonical CSVs, provenance hashes, and the golden fixture. The engine performs deterministic calculations from typed package inputs only.

## Input Model

`Week13EconomicInputs` exposes:

- Norwegian wage bill, union wage demand, and tax rate;
- Permian marginal worker production, margin per barrel, and market wage;
- turnaround base labor cost, peak multiplier, delayed outage probability, outage cost, and asset-health penalty points;
- wage benchmark rows by market;
- worked-example values;
- golden fixture and source hashes;
- package version.

## Formulas

The engine implements only package-demonstrated formulas:

- `norway_gross_cost_musd = wage_bill_musd × union_demand_pct`
- `norway_after_tax_share = 1 - tax_rate`
- `norway_after_tax_cost_musd = norway_gross_cost_musd × norway_after_tax_share`
- `permian_mrp_k = bbl_per_marginal_worker × margin_per_bbl / 1000`
- `mrp_to_wage = permian_mrp_k / market_wage_k`
- `turnaround_peak_cost_musd = labor_cost_base_musd × peak_multiplier`
- `delay_expected_cost_musd = labor_cost_base_musd + outage_probability_if_delayed × outage_cost_musd`
- `delay_saving_musd = turnaround_peak_cost_musd - delay_expected_cost_musd`
- `delay_saving_pct = delay_saving_musd / turnaround_peak_cost_musd`

The package-defined `asset_health_penalty_pts` is preserved as an economic output only. It does not mutate asset-health state.

## Output Model

`Week13EconomicResult` includes:

- engine identifier and version;
- package version;
- input snapshot;
- output snapshot;
- Norway wage concession tax-shield outputs;
- Permian MRP and MRP-to-wage outputs;
- turnaround peak/delay tradeoff outputs;
- asset-health penalty points as package data;
- worked-example outputs;
- package ordering assertions.

## Golden Tests

`tests/Feature/Economics/Week13EconomicEngineTest.php` verifies:

- package loading and SHA-256 provenance;
- golden fixture parity;
- worked-example parity;
- factor-market ordering assertions;
- deterministic output snapshots;
- decimal-safe display values;
- no downstream state creation.

Tolerance follows the package standard:

- relative: `1e-3`
- absolute: `1e-5`

## Deferred

- Week 13 runtime evaluation persistence;
- `WeekExecutionService` integration;
- KPI/ranking integration;
- asset-health state model integration;
- standing changes;
- consequence links;
- Week 13 UI;
- what-if;
- LLM interpretation.
