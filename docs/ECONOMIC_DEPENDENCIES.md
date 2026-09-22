# Economic Dependencies

## Scope and source status

This inventory covers the Batch 4 Week 4 readiness pass using the authoritative markdown sources now available in `C:\Users\sakas\Documents\Flexee-economics`.

The constants authority is:

- `C:\Users\sakas\Documents\Flexee-economics\halden-constants-ledger.md`
- SHA-256: `62D26482535FCD6CA8D9EDC5644D8784CBDCF9F3879B6CC87A30392251328BBC`

The Week 4 worked reference package is still absent. No missing value below should be inferred from industry knowledge or implemented before the package gate clears.

## Known Week 4 dependencies

| Name                                   | Value or formula                                             | Source status                       | Conflict              |
| -------------------------------------- | ------------------------------------------------------------ | ----------------------------------- | --------------------- |
| WTI reference                          | `$74.00/bbl`, live-path reference                            | Ledger and Week 4 spec              | None                  |
| Permian gathering cost                 | `$2.10/bbl`                                                  | Ledger and Week 4 spec              | None                  |
| Permian to Baton Rouge transport       | `$3.20/bbl`                                                  | Ledger and Week 4 spec              | None                  |
| Permian wellhead discount              | `$3.50/bbl` to WTI                                           | Ledger and Week 4 spec              | None                  |
| Permian lifting costs by vintage       | 2021 `$11.50`, 2022 `$10.20`, 2023 `$9.40`, 2024 `$8.80/bbl` | Ledger and Week 4 spec              | None                  |
| Marginal barrel                        | 2024 vintage at `$8.80/bbl`                                  | Ledger and Week 4 spec              | None                  |
| Delivered marginal cost                | `8.80 + 2.10 + 3.20 = $14.10/bbl`                            | Ledger and Week 4 spec              | None                  |
| Capital recovery charge                | `$4.60/bbl`                                                  | Week 4 spec                         | None; Week 4-specific |
| Marginal-cost transfer price           | `$18.70/bbl`                                                 | Ledger and Week 4 spec              | None                  |
| Market transfer price                  | `$73.70/bbl`                                                 | Ledger and Week 4 spec              | None                  |
| Lazy midpoint                          | `$46.20/bbl`                                                 | Ledger and Week 4 spec              | None                  |
| Gulf Coast 3-2-1 crack                 | `$21.50/bbl`                                                 | Ledger and Week 4 spec              | None                  |
| Baton Rouge complexity premium         | `$4.75/bbl`                                                  | Ledger and Week 4 spec              | None                  |
| Baton Rouge refining opex              | `$5.90/bbl`                                                  | Ledger and Week 4 spec              | None                  |
| Baton Rouge net margin over crude cost | `21.50 + 4.75 - 5.90 = $20.35/bbl`                           | Ledger approximate; spec derivation | None                  |
| Geneva arbitrage capture rate          | `35%` of internal-external gap                               | Ledger and Week 4 spec              | None                  |
| Geneva max arbitrage volume            | `40,000 bbl/day`                                             | Ledger and Week 4 spec              | None                  |
| Upstream compensation target           | `$45.00/bbl`                                                 | Ledger and Week 4 spec              | None                  |
| Refining compensation target           | `$30.00/bbl`                                                 | Ledger and Week 4 spec              | None                  |
| Integrated margin                      | `$76.75/bbl` at WTI `$74`, invariant to transfer price       | Ledger and Week 4 spec relationship | None                  |
| Week 4 -> Week 6 rates                 | `6.5%`, `8.5%`, `11.0%`                                      | Ledger                              | Thresholds unresolved |
| Week 4 -> Week 6 capital envelopes     | `$1,520M`, `$1,150M`, `$950M`                                | Ledger                              | Thresholds unresolved |

## Known formulas and relationships

| Relationship                           | Formula                                             | Implementation note                              |
| -------------------------------------- | --------------------------------------------------- | ------------------------------------------------ |
| Brent spread                           | `Brent = WTI + 4.50`                                | Live-path fixture relationship.                  |
| Permian realized wellhead price        | `WTI - 3.50`                                        | At Week 4 WTI, `70.50`.                          |
| Delivered marginal cost                | `lifting + gathering + transport`                   | At marginal barrel, `14.10`.                     |
| Marginal-cost transfer price           | `delivered marginal cost + capital recovery charge` | `18.70`.                                         |
| Upstream contribution                  | `transfer price - 14.10`                            | Used for segment split.                          |
| Integrated transfer-price cancellation | `(T - 14.10) + (90.85 - T) = 76.75`                 | Transfer price changes split, not the fixed pie. |
| Geneva midpoint capture per barrel     | `(73.70 - 46.20) * 35% = 9.625`                     | Monthly convention/rounding blocked by package.  |

## Calibration status

The current implementation must use the supplied design calibration. The calibration review says these values are provisional design-draft values, not audited live market values. A final pre-launch refresh should update inputs from current sources and cite them, while preserving load-bearing relationships.

Do not refresh market values during Batch 4. Preserve:

- transfer-price fixed-pie invariance
- visible opposite bonus outcomes under market vs marginal transfer prices
- Week 6 NPV/IRR crossover relative to disciplined/base/lax rates
- Rotterdam net-negative-but-covers-variable structure
- product income elasticity ordering and Week 8 integration conflict

## Blocked dependencies

Still required before implementation:

- `halden-week4-data-package/`
- package MANIFEST
- canonical CSV inputs
- Excel workbook and visible formulas
- Jupyter notebook and support files
- faculty solution workbook/notebook
- expected outputs
- exact precision, rounding, and display rules
- exact Week 4 -> Week 6 cohort aggregation/classification thresholds

## Implementation guardrails

- Do not implement missing Week 4 formulas from memory or general managerial economics assumptions.
- Store economic constants with source metadata and version.
- Keep Python offline for synthetic path generation and response-function calibration only.
- Keep runtime decision processing, scoring, standing, consequence propagation, response-function application, and ranking in Laravel/PHP.
- Record any ledger/spec/package mismatch before writing calculation code that depends on it.
