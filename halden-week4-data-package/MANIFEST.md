# Halden Energy - Week 4 Data Package Manifest

## Purpose

Week 4 transfer-pricing reference package for the Halden Managerial Economics simulation. This package normalizes the supplied flat Week 4 materials into one package root without changing authoritative economic values.

## Authority Order

1. `halden-constants-ledger.md`
2. `halden-week4-data-package-spec.md`
3. this package's files

If a package file disagrees with the constants ledger, flag the conflict. Do not silently edit economic values.

## Canonical Sources

The canonical CSVs live in `data/`.

| File                            | Conceptual Week 4 datasets represented                                                                        |
| ------------------------------- | ------------------------------------------------------------------------------------------------------------- |
| `data/permian_lifting.csv`      | Permian lifting costs by vintage                                                                              |
| `data/cost_constants.csv`       | Baton Rouge yield economics, Gulf Coast market differentials, Geneva arbitrage capability, and cost constants |
| `data/segment_comp.csv`         | Segment compensation plan                                                                                     |
| `data/worked_example_prior.csv` | Integrated-margin worked-example basis                                                                        |

The Week 4 spec describes six conceptual datasets. This package intentionally preserves the supplied four-CSV layout instead of splitting data solely to match that conceptual count.

## Student Deliverables

- `student/halden_week4.xlsx`
- `student/halden_week4_analysis.ipynb`

The student workbook embeds CSV-derived values in sheets and does not dynamically read the CSV files. The student notebook loads the canonical CSVs from `data/`.

## Faculty Deliverables

- `faculty/halden_week4_solution.xlsx`
- `faculty/halden_week4_solution.ipynb`

Both faculty deliverables use the same source data and complete the current-week analysis.

## Expected Outputs

- `expected/worked_example.json`
- `expected/week4_reference.json`

The prior-period worked example must reproduce `$68.25` integrated margin.

Current Week 4 invariants:

- delivered marginal cost: `14.10`
- marginal transfer price: `18.70`
- market transfer price: `73.70`
- midpoint transfer price: `46.20`
- integrated margin: `76.75`
- Geneva midpoint capture: `9.625/bbl`

## Unresolved Rules

The package does not define custom transfer-price min/max/increment/precision.

The package does not define a monthly or annual Geneva time basis. Keep Geneva capture as `9.625 x applicable barrels` until an authoritative period rule is supplied.

The package does not define Week 4 to Week 6 cohort classification thresholds.

## Validation

From the application repository root:

```bash
python scripts/validate_week4_package.py
```

The validation script checks required files, source hashes, CSV arithmetic, worked-example output, student/faculty notebook execution, expected-output consistency, and workbook formulas.
