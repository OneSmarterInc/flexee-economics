# Batch 20A - Week 10 Package Validation and Golden Reference Gate

Batch 20A validates the authoritative Week 10 reference package and establishes the implementation boundary for the future Week 10 convergence engine.

It intentionally does not implement Week 10 economics, runtime execution, KPI effects, ranking effects, consequences, what-if support, or faculty UI changes.

## Source Materials

Read order used for this batch:

- `storage/app/tmp/complete-handoff/HANDOFF-README.md`
- `storage/app/tmp/complete-handoff/SAKSHI-BATCH-NOTE.md`
- `halden-week10-data-package/MANIFEST.md`
- `halden-week10-data-package/VALIDATION_16A.md`
- `C:\Users\sakas\Documents\Flexee-economics\halden-week10-data-package-spec.md`
- `C:\Users\sakas\Documents\Flexee-economics\halden-constants-ledger.md`
- `halden-week10-data-package/fixtures/week10_golden.json`

## Package Status

The Week 10 package is a 16A PASS package. It includes:

- `MANIFEST.md`
- `VALIDATION_16A.md`
- `halden_week10.xlsx`
- `halden_week10_analysis.ipynb`
- `faculty/halden_week10_FACULTY_SOLUTION.xlsx`
- `faculty/halden_week10_FACULTY_SOLUTION.ipynb`
- `fixtures/provenance.json`
- `fixtures/week10_golden.json`
- canonical CSVs in `data/`

The registered package type remains:

```text
authoritative_week10_reference_package
```

## Canonical Data

The canonical Week 10 datasets are:

- `binding_rules.csv`
- `product_elasticities.csv`
- `recession_params.csv`
- `refinery_yields.csv`
- `team_prior_state.csv`

`team_prior_state.csv` contains two regression fixture teams:

- `reference_disciplined`
- `reference_constrained`

Those rows are not runtime student data. The future Week 10 engine must derive real team state from historical platform records.

## Golden Outputs

The package pins these headline outputs:

| Output                      | Golden value |
| --------------------------- | -----------: |
| `demand_hit_gasoline`       |    `-0.0105` |
| `demand_hit_diesel`         |    `-0.0255` |
| `demand_hit_jet`            |     `-0.048` |
| `blended_demand_hit`        |    `-0.0237` |
| `refinery_hit_br`           |   `-0.01992` |
| `refinery_hit_rot`          |   `-0.02226` |
| `refinery_hit_sing`         |   `-0.02622` |
| `hardest_hit_refinery`      |  `Singapore` |
| `disciplined_binding_count` |          `0` |
| `constrained_binding_count` |          `5` |

Golden comparisons should use the package tolerance:

```text
relative = 1e-3
absolute = 1e-5
```

## Runtime State Dependencies

Week 10 is a convergence week. Its future runtime implementation must consume:

| Runtime field               | Source                                            |
| --------------------------- | ------------------------------------------------- |
| `cancellable_capex_musd`    | Week 6 capital allocation/evaluation              |
| `crude_hedge_coverage`      | Week 5 hedge mandate or hedge position            |
| `br_reported_margin_strong` | Week 4 transfer-price resolution or consequence   |
| `straits_pacific_standing`  | Standing history                                  |
| `cash_cushion_musd`         | Week 8 economic evaluation or cash-position state |

The package fixture rows show the two extreme reference cases; they do not replace runtime state assembly.

## Decision Structure

The future Week 10 student decision should preserve at least:

- `run_rate_baton_rouge_pct`
- `run_rate_rotterdam_pct`
- `run_rate_singapore_pct`
- `capital_response`
- `hedge_response`
- `working_capital_release_musd`
- `binding_constraint_explanation`

This structure follows the specification's analytical ask: run rates, capital response, hedge response, working-capital response, and explicit identification of inherited binding constraints.

## Code Added

Added:

- `App\Domain\Economics\Week10\Week10ReferencePackage`
- `App\Domain\Economics\Week10\Week10ReferenceInputs`
- `tests/Feature/Economics/Week10PackageValidationGateTest.php`

The reader loads package inputs, provenance, golden fixture data, runtime dependency metadata, and the expected decision structure. It does not calculate Week 10 outcomes.

## Deferred

Still deferred:

- Week 10 convergence economic engine
- Week 10 runtime execution integration
- Week 10 KPI/ranking effects
- standing transition rules
- Week 10 consequence mapping
- Week 10 what-if support
- faculty causal trace UI changes
- LLM interpretation changes
