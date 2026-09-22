# Week 4 Golden Test Plan

Inventory date: 2026-09-22.

## Test oracle status

The Week 4 spec and ledger are now present, so spec-derived invariant tests can be planned. Full golden-master testing is still blocked because `halden-week4-data-package/` is missing.

## Spec-derived invariant tests now possible

These tests can be written once the Week 4 calculator exists, using values from the authoritative markdown sources. They are not replacements for reference-package parity.

| Test                               | Inputs                                                   | Expected result                              |
| ---------------------------------- | -------------------------------------------------------- | -------------------------------------------- |
| Delivered marginal cost            | lifting `8.80`, gathering `2.10`, transport `3.20`       | `$14.10/bbl`                                 |
| Marginal-cost transfer price       | delivered marginal cost `14.10`, capital recovery `4.60` | `$18.70/bbl`                                 |
| Market wellhead realization        | WTI `74.00`, discount `3.50`                             | `$70.50/bbl`                                 |
| Market transfer anchor             | authoritative anchor                                     | `$73.70/bbl`                                 |
| Midpoint anchor                    | market `73.70`, marginal `18.70`                         | `$46.20/bbl`                                 |
| Upstream contribution at market    | transfer `73.70`, delivered marginal cost `14.10`        | `$59.60/bbl`, or `+14.60` vs target          |
| Upstream contribution at marginal  | transfer `18.70`, delivered marginal cost `14.10`        | `$4.60/bbl`, or `-40.40` vs target           |
| Upstream contribution at midpoint  | transfer `46.20`, delivered marginal cost `14.10`        | `$32.10/bbl`, or `-12.90` vs target          |
| Refining contribution at market    | target `30.00`, delta `-12.85`                           | `$17.15/bbl`                                 |
| Refining contribution at marginal  | target `30.00`, delta `+42.15`                           | `$72.15/bbl`                                 |
| Refining contribution at midpoint  | target `30.00`, delta `+14.65`                           | `$44.65/bbl`                                 |
| Integrated-margin invariance       | any of the three anchor prices                           | `$76.75/bbl`                                 |
| Geneva midpoint capture per barrel | `(73.70 - 46.20) * 35%`                                  | `$9.625/bbl` before package-defined rounding |
| Cost-of-capital schedule values    | disciplined/base/lax                                     | `6.5%`, `8.5%`, `11.0%`                      |
| Capital-envelope schedule values   | disciplined/base/lax                                     | `$1,520M`, `$1,150M`, `$950M`                |

Additional property-style tests can assert `Integrated(T) = (T - 14.10) + (90.85 - T) = 76.75` for custom/intermediate prices, once package-defined validation bounds and precision are known.

## Reference-package golden tests still blocked

These tests require the actual `halden-week4-data-package/`:

- package MANIFEST/checksum parity
- canonical CSV schema and content parity
- workbook expected-output parity
- notebook expected-output parity
- faculty solution workbook/notebook parity
- worked-example parity for the prior-period WTI `$68.00` case
- exact rounding and display conventions
- exact custom transfer-price precision/bounds
- exact monthly-volume convention for Geneva arbitrage
- exact expected faculty trace payloads, if supplied

## Expected output categories

Keep these categories separate in tests and persistence:

- deterministic economic outputs
- faculty-only causal trace
- KPI values
- score inputs
- scores
- ranks
- published visibility state

Do not compare scores/ranks until authoritative expected outputs or scoring rules exist for those layers.

## Drift failures

The test suite should fail when:

- a source fixture changes without source-version metadata
- ledger values used by Week 4 differ from the ledger snapshot
- package/spec values conflict with the ledger
- calculated outputs differ from expected outputs beyond authorized precision
- rounding behavior changes
- score/rank results are recomputed from the wrong layer
