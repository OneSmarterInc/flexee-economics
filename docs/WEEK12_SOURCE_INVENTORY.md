# Week 12 Source Inventory

Batch 26A classification: **READY**.

Week 12 is no longer quarantined. The complete handoff bundle supersedes the earlier blocked state and records the accepted design revision:

- Adjacent-transition ceiling: `$1,200M`
- Euro retail divestment proceeds: `$550M`
- Helix Rotterdam cost: `$1,200M`
- Helix Rotterdam plus offshore wind cost: `$1,750M`
- Capital envelope with divestment: `$1,750M`

This readiness gate does not implement Week 12 economics, runtime execution, KPI/ranking effects, standing changes, consequence links, what-if support, UI, or LLM behavior.

## Authoritative Sources

The reconciled repository source note records the complete handoff as authoritative:

- `docs/AUTHORITATIVE_PACKAGE_BATCH_SOURCE_NOTE.md`
- `docs/AUTHORITATIVE_PACKAGE_BATCH_INVENTORY.md`
- `docs/WEEK_SPECIFIC_PARAMETER_REGISTER.md`
- `halden-week12-data-package/MANIFEST.md`
- `halden-week12-data-package/VALIDATION_16A.md`
- `halden-week12-data-package/fixtures/week12_golden.json`
- `halden-week12-data-package/fixtures/provenance.json`

The Week 12 package root is:

```text
halden-week12-data-package
```

## Package Tree

```text
halden-week12-data-package/
├── MANIFEST.md
├── VALIDATION_16A.md
├── halden_week12.xlsx
├── halden_week12_analysis.ipynb
├── data/
│   ├── buckets.csv
│   ├── carbon_scenarios.csv
│   ├── demand_scenarios.csv
│   ├── envelope.csv
│   ├── projects.csv
│   └── worked_example_projects.csv
├── faculty/
│   ├── halden_week12_FACULTY_SOLUTION.ipynb
│   └── halden_week12_FACULTY_SOLUTION.xlsx
└── fixtures/
    ├── provenance.json
    └── week12_golden.json
```

## Provenance Hashes

The provenance file records SHA-256 hashes for the package artifacts:

| Artifact                                       | SHA-256                                                            |
| ---------------------------------------------- | ------------------------------------------------------------------ |
| `MANIFEST.md`                                  | `c19baf7b580bf81bf582bbbdfabec3bb44e6d6fa4505ed37321a11af0de8962d` |
| `VALIDATION_16A.md`                            | `494e8958d0a80068d8b4cf974e69be61b6936f1dcb25bca9589b3d28b8027316` |
| `halden_week12.xlsx`                           | `917cd38526352a3b200d12e8f3cef161e3dc339ad2605f536184f03ccb57dc2e` |
| `halden_week12_analysis.ipynb`                 | `9cb51b2c814378a0630b05586def695ce1f5997ab677e7062813d461c66fd870` |
| `data/buckets.csv`                             | `24ec9addb42b79b3a13405abb0310684834c89c643359c070d514857957270f8` |
| `data/carbon_scenarios.csv`                    | `5f50d3ec4d1173190fc88ed42f8a96181dc773f7f9432dd3796d9a5df4619d16` |
| `data/demand_scenarios.csv`                    | `e3dd9a8d388572dabfa0a1acf1403d80bc1231b3ed9626b8130c6f032276225c` |
| `data/envelope.csv`                            | `29b85e5b2b2cb7b66cf14d8920f06a70314a35377f0ea1a0b8fd266ac086501b` |
| `data/projects.csv`                            | `d2c598b57827294b281d02593e6769e0770aa55a6acc9d0466a47d08ca4edf55` |
| `data/worked_example_projects.csv`             | `e2b17cabbd5e166d44986ee746b423c99f4aaa01150da40c9091cee698a7851d` |
| `faculty/halden_week12_FACULTY_SOLUTION.ipynb` | `8765273e9fef10ddd8582c88300958cfaf08fdfe1e8f17f646d1cd364dc0580b` |
| `faculty/halden_week12_FACULTY_SOLUTION.xlsx`  | `ca04337158ab8f2a7f4c62c0d305891fe6f2d86f93e05c137787b35a51832a7a` |
| `fixtures/week12_golden.json`                  | `b2f1fafaead76d9113fcced10f40e55dca887fee8e4fa39be753b88f5bdda18f` |

## Helix Conflict Reconciliation

Earlier handoff material blocked Week 12 because Helix Rotterdam cost `$1,200M` exceeded an adjacent-transition bucket ceiling of `$900M`, while divestment proceeds were only `$320M`.

The complete handoff resolves that blocker through the package and source note:

- `data/buckets.csv` now sets the adjacent bucket ceiling to `$1,200M`.
- `data/projects.csv` keeps Helix Rotterdam at `$1,200M`.
- `data/projects.csv` sets Euro retail divestment cost to `-$550M`.
- `fixtures/week12_golden.json` pins `adjacent_ceiling = 1200.0`.
- `fixtures/week12_golden.json` pins `helix_rotterdam_cost = 1200.0`.
- `fixtures/week12_golden.json` pins `envelope_with_divest = 1750.0`.
- `fixtures/week12_golden.json` pins `hr_plus_wind_cost = 1750.0`.
- `fixtures/week12_golden.json` pins `hr_plus_wind_needs_divest = true`.

Result: Helix Rotterdam is feasible within the adjacent-transition bucket and fills the discretionary envelope alone. Helix Rotterdam plus offshore wind is feasible only with divestment proceeds.

## Validation Status

`VALIDATION_16A.md` reports:

- manifest and provenance validation passed;
- canonical CSV inventory passed;
- student workbook recalculation passed;
- student notebook execution passed;
- faculty workbook validation passed;
- faculty notebook validation passed;
- golden fixture validation passed;
- student/faculty separation passed.

Batch 26A additionally inspected the workbook and notebook structures:

- student workbook sheets: `README`, canonical `D_*` tabs, `Worked Example`, and `Your Analysis`;
- faculty workbook contains the same sheets with additional formulas;
- no external workbook links were found;
- no hidden sheets were found;
- no formula error literals were found;
- student notebook reads package CSVs from `data/`;
- faculty notebook reads package CSVs and `fixtures/week12_golden.json`.

## Golden Fixture

The golden fixture records:

- package version: `1.0.0-draft`;
- relative tolerance: `1e-3`;
- absolute tolerance: `1e-5`;
- discretionary capital: `1200.0`;
- feasible portfolios: `17`;
- feasible portfolios with Helix Rotterdam: `3`;
- portfolios unlocked by divestment: `2`;
- all project scenario NPV sign-swing assertions passed;
- Helix Rotterdam plus offshore wind requires divestment.

## Readiness Decision

Week 12 is **READY** for a future economic engine implementation batch.

The future implementation must consume the package artifacts as data and validate against `fixtures/week12_golden.json`. It must not reintroduce the earlier `$900M` adjacent-ceiling blocker or infer formulas from narrative text.
