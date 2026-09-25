# Week 8 Reference Map

Week 8 is now package-backed. This map reconciles the package against the Week 8 spec and constants ledger without implementing runtime economics.

## Package Scope

```text
OPEC scenario package
    -> scenario probabilities and WTI outcomes
    -> propagation coefficients
    -> worked example
    -> student workbook/notebook
    -> faculty solution
    -> golden fixture
```

Out of scope for this package:

- Week 6 -> Week 8 cohort capacity response;
- KPI/ranking effects;
- Week 10 cash-position consequences;
- runtime scenario resolution.

## Baseline State

| Parameter             | Package | Week 8 spec / ledger | Status |
| --------------------- | ------: | -------------------: | ------ |
| WTI pre-shock         | `74.00` |              `74.00` | Match  |
| Brent pre-shock       | `78.50` |         WTI + `4.50` | Match  |
| Gulf Coast crack base | `21.50` |              `21.50` | Match  |
| Pump base             |  `3.20` |               `3.20` | Match  |

## OPEC Scenarios

| Scenario        | Probability | Resolved WTI | Delta WTI | Reconciliation               |
| --------------- | ----------: | -----------: | --------: | ---------------------------- |
| `holds_full`    |      `0.35` |      `88.00` |   `14.00` | Matches spec, ledger, golden |
| `holds_partial` |      `0.40` |      `81.00` |    `7.00` | Matches spec, ledger, golden |
| `fails`         |      `0.25` |      `70.00` |   `-4.00` | Matches spec, ledger, golden |

Probability-weighted expected WTI is `80.70`.

## Propagation Coefficients

| Channel                    | Coefficient | Meaning                                        | Reconciliation |
| -------------------------- | ----------: | ---------------------------------------------- | -------------- |
| `upstream_realization`     |      `1.00` | Upstream realization moves 1:1 with benchmark  | Match          |
| `refining_crack`           |     `-0.35` | Crack compresses short-run per `$1` crude rise | Match          |
| `retail_passthrough`       |      `0.60` | Crude move reaching pump price                 | Match          |
| `retail_demand_elasticity` |     `-0.05` | Short-run gasoline demand elasticity           | Match          |

These are OPEC shock propagation coefficients. They are not the Week 6 -> Week 8 cohort-feedback response function.

## Golden Outputs

| Scenario        | Upstream impact |   Crack | Retail volume | Reconciliation |
| --------------- | --------------: | ------: | ------------: | -------------- |
| `holds_full`    |         `14.00` | `16.60` |     `-0.312%` | Match          |
| `holds_partial` |          `7.00` | `19.05` |     `-0.156%` | Match          |
| `fails`         |         `-4.00` | `22.90` |      `0.089%` | Match          |

## Student/Faculty Separation

- Student workbook leaves current-week probabilities blank.
- Student notebook leaves current-week probability estimate in TODO cells.
- Faculty solution workbook fills sim probabilities `0.35`, `0.40`, `0.25` and shows the solved reference.

## Reconciliation Result

The package is internally consistent and reconciles to the Week 8 spec and constants ledger for OPEC scenario propagation.

Non-blocking caveat: workbook formula recalculation was inspected via cached values and formula/error scans using openpyxl. Native Excel recalculation was not performed in this environment.
