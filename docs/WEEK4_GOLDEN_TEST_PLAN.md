# Week 4 Golden Test Plan

Inventory date: 2026-09-22.

## Test oracle status

The Week 4 spec, ledger, flat CSVs, student workbook, and student notebook are now present. Partial immutable fixtures exist under `tests/Fixtures/Week4/`. Full golden-master testing is still blocked because the package lacks a Week 4 manifest, executable notebook path layout, faculty solution artifacts, and expected-output files.

## Spec-derived invariant tests now possible

These tests can be written once the Week 4 calculator exists, using values from the authoritative markdown sources and `tests/Fixtures/Week4/expected_outputs.json`. They are not replacements for complete reference-package parity.

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

These tests require a corrected/completed Week 4 package:

- Week 4 package MANIFEST/checksum parity
- notebook execution parity from the supplied package layout
- faculty solution workbook/notebook parity
- expected-output file parity
- exact rounding method for half-cent/three-decimal outputs
- exact custom transfer-price precision/bounds
- exact monthly-volume convention for Geneva arbitrage
- exact expected faculty trace payloads, if supplied

## Fixture files

Partial fixtures created from the available package files:

- `tests/Fixtures/Week4/source_hashes.json`
- `tests/Fixtures/Week4/inputs/permian_lifting.csv`
- `tests/Fixtures/Week4/inputs/cost_constants.csv`
- `tests/Fixtures/Week4/inputs/segment_comp.csv`
- `tests/Fixtures/Week4/inputs/worked_example_prior.csv`
- `tests/Fixtures/Week4/expected_outputs.json`

Fixture tolerance policy:

- canonical CSV ingestion: exact string/decimal equality;
- worked-example and current-week two-decimal per-barrel values: exact decimal equality at two decimals;
- Geneva `capture_per_bbl`: exact decimal equality at `9.625`;
- daily Geneva cap calculation: exact decimal equality at `385000.000` if used;
- no broad binary-float tolerance is allowed for these fixtures.

The fixtures explicitly record that the supplied notebook is path-broken and that no faculty solution or expected-output source file was supplied.

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
