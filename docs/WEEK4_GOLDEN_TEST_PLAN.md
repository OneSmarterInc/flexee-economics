# Week 4 Golden Test Plan

Inventory date: 2026-09-22.

## Test oracle status

The normalized Week 4 package is present under `halden-week4-data-package/` and is the canonical golden reference for Batch 4 economic tests.

Status: `READY WITH NON-BLOCKING QUESTIONS`.

Use these package files for fixture parity:

- `halden-week4-data-package/expected/worked_example.json`
- `halden-week4-data-package/expected/week4_reference.json`
- `tests/Fixtures/Week4/package_sources.json`
- `scripts/validate_week4_package.py`

The old duplicated flat fixture inputs under `tests/Fixtures/Week4/inputs/` were removed. Tests should read the canonical package files directly.

## Spec-derived invariant tests

These tests can be written once the Week 4 calculator exists, using package expected outputs and exact decimal arithmetic.

| Test                               | Inputs                                                     | Expected result                         |
| ---------------------------------- | ---------------------------------------------------------- | --------------------------------------- |
| Worked example integrated margin   | prior worked-example CSV                                   | `$68.25/bbl`                            |
| Delivered marginal cost            | lifting `8.80`, gathering `2.10`, transport `3.20`         | `$14.10/bbl`                            |
| Marginal-cost transfer price       | delivered marginal cost `14.10`, capital recovery `4.60`   | `$18.70/bbl`                            |
| Market wellhead realization        | WTI `74.00`, discount `3.50`                               | `$70.50/bbl`                            |
| Market transfer anchor             | realized wellhead `70.50`, transport `3.20`                | `$73.70/bbl`                            |
| Midpoint anchor                    | market `73.70`, marginal `18.70`                           | `$46.20/bbl`                            |
| Upstream contribution at market    | transfer `73.70`, delivered marginal cost `14.10`          | `$59.60/bbl`, or `+14.60` vs target     |
| Upstream contribution at marginal  | transfer `18.70`, delivered marginal cost `14.10`          | `$4.60/bbl`, or `-40.40` vs target      |
| Upstream contribution at midpoint  | transfer `46.20`, delivered marginal cost `14.10`          | `$32.10/bbl`, or `-12.90` vs target     |
| Refining contribution at market    | product slate value `96.75`, transfer `73.70`, opex `5.90` | `$17.15/bbl`                            |
| Refining contribution at marginal  | product slate value `96.75`, transfer `18.70`, opex `5.90` | `$72.15/bbl`                            |
| Refining contribution at midpoint  | product slate value `96.75`, transfer `46.20`, opex `5.90` | `$44.65/bbl`                            |
| Integrated-margin invariance       | any of the three anchor prices                             | `$76.75/bbl`                            |
| Geneva midpoint capture per barrel | `(73.70 - 46.20) * 35%`                                    | `9.625/bbl` before any display rounding |
| Cost-of-capital schedule values    | disciplined/base/lax                                       | `6.5%`, `8.5%`, `11.0%`                 |
| Capital-envelope schedule values   | disciplined/base/lax                                       | `$1,520M`, `$1,150M`, `$950M`           |

Additional property-style tests can assert `Integrated(T) = (T - 14.10) + (90.85 - T) = 76.75` for custom/intermediate prices only after package-defined validation bounds and precision are known.

## Package validation tests

The package validator currently covers:

- required package-file presence;
- hash parity for package files and external source provenance;
- CSV arithmetic;
- worked example output;
- student notebook execution and answer-leak guard;
- faculty notebook execution and expected-output parity;
- student/faculty workbook sheet layout, formula presence, and external-link absence.

Artifact Tool workbook recalculation should remain part of the acceptance flow for workbook changes. It verifies representative values and scans for formula errors in both workbooks.

## Implemented Batch 4A tests

`tests/Feature/Economics/Week4EconomicEngineTest.php` now covers the deterministic Week 4 Laravel engine.

Implemented checks:

- delivered marginal cost equals `14.10`;
- anchor transfer prices equal `73.70`, `18.70`, and `46.20`;
- segment splits and compensation deltas match `expected/week4_reference.json`;
- integrated margin remains `76.75` for reference anchors and arbitrary custom prices `20.00` and `50.00`;
- Geneva midpoint gap equals `27.50` and capture equals `9.625`;
- current-week engine output matches `halden-week4-data-package/expected/week4_reference.json`;
- worked-example engine output matches `halden-week4-data-package/expected/worked_example.json`.

These tests exercise deterministic economic outputs only. They do not assert scores, ranks, standing consequences, faculty trace payloads, or Week 4 to Week 6 cohort classification.

## Fixture tolerance policy

- canonical CSV ingestion: exact string/decimal equality;
- worked-example and current-week two-decimal per-barrel values: exact decimal equality at two decimals;
- Geneva `capture_per_bbl`: exact decimal equality at `9.625`;
- daily Geneva cap calculation: exact decimal equality at `385000.000` if used;
- no broad binary-float tolerance is allowed for these fixtures.

## Remaining non-blocking questions

The package oracle does not define:

- exact custom transfer-price min/max/increment/precision;
- display rounding method for half-cent or three-decimal Geneva values;
- monthly or annual volume convention for Geneva arbitrage;
- exact expected faculty trace payloads, if later supplied;
- Week 4 to Week 6 cohort aggregation/classification thresholds.

Do not compare scores/ranks or implement Week 6 consequences until authoritative rules exist for those layers.

## Expected output categories

Keep these categories separate in tests and persistence:

- deterministic economic outputs;
- faculty-only causal trace;
- KPI values;
- score inputs;
- scores;
- ranks;
- published visibility state.

## Drift failures

The test suite should fail when:

- a source fixture changes without source-version metadata;
- ledger values used by Week 4 differ from the ledger snapshot;
- package/spec values conflict with the ledger;
- calculated outputs differ from expected outputs beyond authorized precision;
- rounding behavior changes;
- score/rank results are recomputed from the wrong layer.
