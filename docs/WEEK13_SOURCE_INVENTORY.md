# Week 13 Source Inventory

Batch 28A validates the authoritative Week 13 factor-markets package as a readiness gate only. It does not implement the Week 13 economic engine, runtime evaluation, KPI/ranking effects, standing changes, consequences, UI, what-if behavior, or LLM behavior.

## Readiness Status

`READY`

The Week 13 package is ready for a future package-backed economic engine implementation.

## Authoritative Sources

- `C:\Users\sakas\Documents\Flexee-economics\old files\HANDOFF-README.md`
- `docs/AUTHORITATIVE_PACKAGE_BATCH_SOURCE_NOTE.md`
- `C:\Users\sakas\Documents\Flexee-economics\old files\halden-development-instructions.md`
- `C:\Users\sakas\Documents\Flexee-economics\old files\halden-week13-data-package-spec.md`
- `halden-week13-data-package/MANIFEST.md`
- `halden-week13-data-package/VALIDATION_16A.md`
- `halden-week13-data-package/fixtures/week13_golden.json`
- `C:\Users\sakas\Documents\Flexee-economics\old files\halden-constants-ledger.md`
- `C:\Users\sakas\Documents\Flexee-economics\old files\halden-calibration-review.md`
- `docs/WEEK_SPECIFIC_PARAMETER_REGISTER.md`

`SAKSHI-BATCH-NOTE.md` is represented in the repository as `docs/AUTHORITATIVE_PACKAGE_BATCH_SOURCE_NOTE.md`; no standalone file by that name is present in the current repo or supplied source folder.

## Package Tree

```text
halden-week13-data-package/
├── MANIFEST.md
├── VALIDATION_16A.md
├── halden_week13.xlsx
├── halden_week13_analysis.ipynb
├── data/
│   ├── norway_union.csv
│   ├── permian_labor.csv
│   ├── turnaround.csv
│   ├── wage_benchmarks.csv
│   └── worked_example_labor.csv
├── faculty/
│   ├── halden_week13_FACULTY_SOLUTION.ipynb
│   └── halden_week13_FACULTY_SOLUTION.xlsx
└── fixtures/
    ├── provenance.json
    └── week13_golden.json
```

## Provenance Validation

`fixtures/provenance.json` declares 12 hashed artifacts. Batch 28A recomputed every declared SHA-256 hash against the local package files.

Result:

- declared artifacts: `12`
- missing declared artifacts: `0`
- hash mismatches: `0`
- package version: `1.0.0-draft`
- artifact versions: `1.0.0-draft`

`fixtures/provenance.json` itself is intentionally outside the self-hash artifact map, while the application manifest service registers it as a provenance artifact at runtime.

## Canonical CSV Inventory

| File                            | Rows | Schema                                    | Missing cells | Duplicate first-column keys |
| ------------------------------- | ---: | ----------------------------------------- | ------------: | --------------------------: |
| `data/norway_union.csv`         |    3 | `parameter`, `value`                      |             0 |                           0 |
| `data/permian_labor.csv`        |    3 | `parameter`, `value`                      |             0 |                           0 |
| `data/turnaround.csv`           |    5 | `parameter`, `value`                      |             0 |                           0 |
| `data/wage_benchmarks.csv`      |    3 | `market`, `structure`, `benchmark_wage_k` |             0 |                           0 |
| `data/worked_example_labor.csv` |    5 | `parameter`, `value`                      |             0 |                           0 |

Numeric fields parse successfully where expected:

- all `value` fields in `norway_union.csv`, `permian_labor.csv`, `turnaround.csv`, and `worked_example_labor.csv`;
- all `benchmark_wage_k` fields in `wage_benchmarks.csv`.

## Manifest Validation

The manifest reports Batch 16A status `PASS` and identifies the package as:

```text
Halden Energy — Week 13 Authoritative Package
Factor markets — three labor structures
```

Canonical data sources:

- `norway_union.csv`
- `permian_labor.csv`
- `turnaround.csv`
- `wage_benchmarks.csv`
- `worked_example_labor.csv`

Student materials:

- `halden_week13.xlsx`
- `halden_week13_analysis.ipynb`

Faculty materials:

- `faculty/halden_week13_FACULTY_SOLUTION.xlsx`
- `faculty/halden_week13_FACULTY_SOLUTION.ipynb`

Expected headline outputs:

- `norway_gross_cost_musd = 33.6`
- `norway_after_tax_cost_musd = 7.392`
- `permian_mrp_k = 507.6`
- `mrp_to_wage = 3.5007`
- `turnaround_peak_cost_musd = 81.0`
- `delay_expected_cost_musd = 78.0`
- `delay_saving_musd = 3.0`

## Week-Specific Parameters

The package introduces these parameters that are not yet folded into the central constants ledger:

- Norwegian offshore wage bill: `$420M`
- Permian marginal worker productivity: `9,000 bbl/year`
- Permian loaded wage: `$145k`
- turnaround labor base: `$60M`
- delayed-outage probability: `12%`
- outage cost: `$150M`
- asset-health penalty: `3 points`

These are package-authoritative for future Week 13 implementation. Batch 28A does not update the central ledger.

## Workbook Validation

`halden_week13.xlsx`:

- sheets: `README`, `D_norway_union`, `D_permian_labor`, `D_turnaround`, `D_wage_benchmarks`, `D_worked_example_labor`, `Worked Example`, `Your Analysis`
- hidden sheets: none
- formulas: `3`
- external links: `0`
- formula/error literals: none found

`faculty/halden_week13_FACULTY_SOLUTION.xlsx`:

- sheets: `README`, `D_norway_union`, `D_permian_labor`, `D_turnaround`, `D_wage_benchmarks`, `D_worked_example_labor`, `Worked Example`, `Your Analysis`
- hidden sheets: none
- formulas: `11`
- external links: `0`
- formula/error literals: none found

The Batch 16A report states that both workbooks recalculate with zero errors and that faculty workbook live formulas match the Python engine. Batch 28A inspected structure and formulas but did not modify or recalculate workbook economics.

## Notebook Validation

`halden_week13_analysis.ipynb`:

- cells: `7`
- code cells: `3`
- machine-specific paths: none found
- execution status: passed from the package root

`faculty/halden_week13_FACULTY_SOLUTION.ipynb`:

- cells: `4`
- code cells: `3`
- machine-specific paths: none found
- execution status: passed from the `faculty/` directory
- result: `FACULTY NOTEBOOK REPRODUCES GOLDEN FIXTURES: PASS`

## Golden Fixture

`fixtures/week13_golden.json` declares:

- package version: `1.0.0-draft`
- relative tolerance: `1e-3`
- absolute tolerance: `1e-5`

Expected outputs:

- `norway_gross_cost_musd = 33.6`
- `norway_after_tax_cost_musd = 7.392`
- `norway_after_tax_share = 0.22`
- `permian_mrp_k = 507.6`
- `mrp_to_wage = 3.50069`
- `turnaround_peak_cost_musd = 81.0`
- `delay_expected_cost_musd = 78.0`
- `delay_saving_musd = 3.0`
- `delay_saving_pct = 0.037037`
- `asset_health_penalty_pts = 3.0`

Worked example:

- `mrp = 50000.0`
- `mrp_minus_wage = 10000.0`
- `after_tax_wage_increase = 50.0`

Ordering assertions all pass:

- Tax shield: Norwegian concession costs Halden 22% of face value.
- Permian marginal worker MRP covers market wage.
- Contractor peak is 35% above base.
- Turnaround timing is a genuine tradeoff, with expected costs within 5%.
- Delaying carries an asset-health KPI penalty.

## Student / Faculty Separation

Runtime artifact authorization was validated through `SimulationContentResolver`:

- students can see student workbook/notebook and shared canonical data;
- students cannot see faculty solution or golden/provenance fixtures;
- faculty can see solution and fixture artifacts.

## Non-Blocking Implementation Notes

- The package pins `asset_health_penalty_pts = 3.0`, but the platform asset-health mechanic is not implemented yet. A future Week 13 engine can expose the package-backed output without creating asset-health KPI mutation until the scoring mechanic exists.
- Week 13 consequences, standing transitions, what-if behavior, and LLM context are future batches.

## Deferred

- Week 13 economic engine;
- Week 13 runtime evaluation;
- Week 13 KPI/ranking integration;
- asset-health state mutation;
- standing changes;
- consequence links;
- student/faculty UI refinement;
- what-if support;
- LLM behavior.
