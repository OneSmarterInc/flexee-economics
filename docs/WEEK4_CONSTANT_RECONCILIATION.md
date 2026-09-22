# Week 4 Constant Reconciliation

Inventory date: 2026-09-22.

## Status

Mandatory reconciliation is blocked because these authoritative sources are missing:

- `halden-week4-data-package/`
- `halden-week4-data-package-spec.md`

The only available economic source is:

- `C:\Users\sakas\Documents\Flexee-economics\halden-constants-ledger.md`

The ledger is authoritative. If later Week 4 spec/package values conflict with the ledger, the ledger wins and the conflict must be flagged before implementation.

## Reconciliation table

| Constant name                          | Ledger value                                                                          | Week 4 spec value | Reference-package value | Match/conflict | Notes                    |
| -------------------------------------- | ------------------------------------------------------------------------------------- | ----------------- | ----------------------- | -------------- | ------------------------ |
| WTI reference                          | `$74.00/bbl` reference; live-path series                                              | Missing           | Missing                 | Cannot compare | Ledger line 14.          |
| Brent spread                           | `WTI + $4.50`                                                                         | Missing           | Missing                 | Cannot compare | Ledger lines 15 and 104. |
| Permian wellhead discount              | `$3.50/bbl` to WTI                                                                    | Missing           | Missing                 | Cannot compare | Ledger line 21.          |
| Delivered marginal cost to Baton Rouge | `$14.10/bbl`                                                                          | Missing           | Missing                 | Cannot compare | Ledger line 23.          |
| Baton Rouge complexity premium         | `$4.75/bbl` over benchmark crack                                                      | Missing           | Missing                 | Cannot compare | Ledger line 27.          |
| Baton Rouge refining opex              | `$5.90/bbl`                                                                           | Missing           | Missing                 | Cannot compare | Ledger line 28.          |
| Gulf Coast 3-2-1 crack reference       | `$21.50/bbl`; live-path reference                                                     | Missing           | Missing                 | Cannot compare | Ledger line 29.          |
| Baton Rouge reference net margin       | Approximately `$20.35/bbl`                                                            | Missing           | Missing                 | Cannot compare | Ledger line 30.          |
| Trading arbitrage capture rate         | `35%` of internal-external price gap                                                  | Missing           | Missing                 | Cannot compare | Ledger line 80.          |
| Max arbitrage volume                   | `40,000 bbl/day`                                                                      | Missing           | Missing                 | Cannot compare | Ledger line 81.          |
| Upstream target margin                 | `$45.00/bbl`                                                                          | Missing           | Missing                 | Cannot compare | Ledger line 87.          |
| Refining target margin                 | `$30.00/bbl`                                                                          | Missing           | Missing                 | Cannot compare | Ledger line 87.          |
| Integrated margin invariant            | Transfer price changes split, not total integrated margin                             | Missing           | Missing                 | Cannot compare | Ledger line 117.         |
| Integrated margin at reference prices  | `$76.75/bbl`                                                                          | Missing           | Missing                 | Cannot compare | Ledger line 118.         |
| Market-based transfer price option     | `$73.70`; upstream `+14.60`, refining `-12.85` vs target                              | Missing           | Missing                 | Cannot compare | Ledger line 119.         |
| Marginal-cost transfer price option    | `$18.70`; upstream `-40.40`, refining `+42.15` vs target                              | Missing           | Missing                 | Cannot compare | Ledger line 119.         |
| Lazy-midpoint transfer price option    | `$46.20`; upstream `-12.90`, refining `+14.65` vs target                              | Missing           | Missing                 | Cannot compare | Ledger line 119.         |
| Week 4 to Week 6 mechanism             | Transfer-pricing discipline affects cost of capital; not a cross-cohort market window | Missing           | Missing                 | Cannot compare | Ledger line 129.         |
| Cohort discount-rate schedule          | disciplined `6.5%`, base `8.5%`, lax `11.0%`                                          | Missing           | Missing                 | Cannot compare | Ledger line 131.         |
| Capital-envelope schedule              | disciplined `$1,520M`, base `$1,150M`, lax `$950M`                                    | Missing           | Missing                 | Cannot compare | Ledger line 131.         |

## Conflicts

No conflicts can be identified yet because no Week 4 spec or reference package was found.

## Calibration review

`halden-calibration-review.md` was not found. The ledger itself states all values are provisional design-draft calibration and that relationships are the design intent. Handoff specifically calls out the NPV/IRR crossover at `7.99%` and Rotterdam's net-negative-but-covers-variable structure as relationships that must survive recalibration, but the calibration classification for Week 4 values cannot be completed without the missing review.
