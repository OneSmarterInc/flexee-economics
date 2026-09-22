# Week 4 Constant Reconciliation

Inventory date: 2026-09-22.

Sources reconciled:

- `C:\Users\sakas\Documents\Flexee-economics\halden-week4-data-package-spec.md`
- `C:\Users\sakas\Documents\Flexee-economics\halden-constants-ledger.md`
- `C:\Users\sakas\Documents\Flexee-economics\halden-calibration-review.md`

The constants ledger remains the single source of economic truth. If the Week 4 spec and ledger disagree, the ledger wins and the disagreement must be flagged before implementation.

## Status

The Week 4 markdown spec and ledger reconcile on the numeric transfer-pricing values needed for mathematical invariants. The worked reference package is still missing, so package parity, exact file schemas, workbook/notebook formula parity, rounding, display conventions, and expected-output tests remain blocked.

## Reconciliation table

| Item                                     | Ledger value                                                                          | Week 4 spec value                                                                                 | Match/conflict           | Notes                                                                                              |
| ---------------------------------------- | ------------------------------------------------------------------------------------- | ------------------------------------------------------------------------------------------------- | ------------------------ | -------------------------------------------------------------------------------------------------- |
| WTI reference                            | `$74.00/bbl`, live-path reference                                                     | `$74.00/bbl`, Week 4-specific live-path value                                                     | Match                    | WTI moves weekly elsewhere.                                                                        |
| Permian lifting costs                    | 2021 `$11.50`, 2022 `$10.20`, 2023 `$9.40`, 2024 `$8.80/bbl`; 2024 is marginal barrel | Same table and marginal-barrel designation                                                        | Match                    | 2024 vintage drives marginal-cost analysis.                                                        |
| Gathering cost                           | `$2.10/bbl`                                                                           | `$2.10/bbl`                                                                                       | Match                    | Company-wide Permian constant.                                                                     |
| Transport cost                           | `$3.20/bbl` Permian to Baton Rouge                                                    | `$3.20/bbl`                                                                                       | Match                    | Company-wide constant.                                                                             |
| Delivered marginal cost                  | `$14.10/bbl`                                                                          | `8.80 + 2.10 + 3.20 = $14.10/bbl`                                                                 | Match                    | True short-run floor before capital recovery charge.                                               |
| Capital recovery charge                  | Not listed in ledger cost section; Week 4 spec says `$4.60/bbl`                       | `$4.60/bbl`                                                                                       | No conflict              | Week 4-specific value; methodology carries.                                                        |
| Marginal-cost transfer price             | `$18.70/bbl`                                                                          | `$14.10 + $4.60 = $18.70/bbl`                                                                     | Match                    | Integrated-optimal transfer-price anchor.                                                          |
| Market transfer price                    | `$73.70/bbl`                                                                          | `$73.70/bbl`                                                                                      | Match                    | Spec defines as external market price plus freight.                                                |
| Midpoint/lazy option                     | `$46.20/bbl`                                                                          | `$46.20/bbl`                                                                                      | Match                    | Gap to external market price is `$27.50/bbl`.                                                      |
| WTI discount/wellhead                    | `$3.50/bbl` discount; wellhead price implied `$70.50/bbl`                             | `$3.50/bbl`; realized wellhead `$70.50/bbl`                                                       | Match                    | `74.00 - 3.50 = 70.50`.                                                                            |
| Gulf Coast crack                         | `$21.50/bbl` live-path reference                                                      | `$21.50/bbl`                                                                                      | Match                    | Company-wide Gulf Coast reference relationship.                                                    |
| Complexity premium                       | `$4.75/bbl`                                                                           | `$4.75/bbl`                                                                                       | Match                    | Baton Rouge asset property.                                                                        |
| BR realized gross margin over crude cost | Ledger implies crack plus complexity; net margin noted approximately `$20.35/bbl`     | `$26.25/bbl` gross over crude cost                                                                | Match with rounding note | `21.50 + 4.75 = 26.25`; `26.25 - 5.90 = 20.35`.                                                    |
| Refinery opex                            | `$5.90/bbl`                                                                           | `$5.90/bbl`                                                                                       | Match                    | Baton Rouge asset property.                                                                        |
| Geneva capture rate                      | `35%` of internal-external gap                                                        | `35%`                                                                                             | Match                    | Applies separately from integrated operating segment split.                                        |
| Geneva capacity                          | `40,000 bbl/day`                                                                      | `40,000 bbl/day`                                                                                  | Match                    | Maximum monthly volume desk can arbitrage.                                                         |
| Upstream target                          | `$45.00/bbl`                                                                          | `$45.00/bbl`                                                                                      | Match                    | Segment compensation target.                                                                       |
| Refining target                          | `$30.00/bbl`                                                                          | `$30.00/bbl`                                                                                      | Match                    | Segment compensation target.                                                                       |
| Market segment outcomes                  | Upstream `+14.60`, refining `-12.85` vs target                                        | Same faculty answer-key table                                                                     | Match                    | Derivable from student datasets.                                                                   |
| Marginal segment outcomes                | Upstream `-40.40`, refining `+42.15` vs target                                        | Same faculty answer-key table                                                                     | Match                    | Derivable from student datasets.                                                                   |
| Midpoint segment outcomes                | Upstream `-12.90`, refining `+14.65` vs target                                        | Same faculty answer-key table                                                                     | Match                    | Derivable from student datasets.                                                                   |
| Integrated margin                        | `$76.75/bbl` at WTI `$74`; invariant to transfer price                                | Invariance required; worked current-week blank asks students to compute it                        | Match                    | Package still needed to confirm expected outputs and rounding.                                     |
| Week 4 -> Week 6 rates                   | Disciplined `6.5%`, base `8.5%`, lax `11.0%`; envelopes `$1,520M`, `$1,150M`, `$950M` | Spec says cohort transfer-pricing behavior shapes Week 6 constraints but does not list thresholds | Partial                  | Ledger supplies schedules; current sources do not give classification thresholds/aggregation rule. |

## Integrated-margin derivation

Let:

- `T` = submitted transfer price
- `C_m` = delivered marginal cost before capital recovery = `8.80 + 2.10 + 3.20 = 14.10`
- `U(T)` = upstream contribution per barrel = `T - C_m`
- `R_target` = refining compensation target = `30.00`
- `RefDelta(T)` = refining vs-target value from the spec/ledger table
- `R(T)` = refining contribution per barrel = `R_target + RefDelta(T)`

Using any of the three authoritative anchors:

| Transfer price    | Upstream contribution `T - 14.10` | Refining contribution `30 + delta` | Integrated contribution |
| ----------------- | --------------------------------: | ---------------------------------: | ----------------------: |
| Market `$73.70`   |                          `$59.60` |                           `$17.15` |                `$76.75` |
| Marginal `$18.70` |                           `$4.60` |                           `$72.15` |                `$76.75` |
| Midpoint `$46.20` |                          `$32.10` |                           `$44.65` |                `$76.75` |

Symbolically, the transfer price cancels:

`Integrated(T) = (T - 14.10) + (90.85 - T) = 76.75`

The inferred ex-transfer Baton Rouge value for this Week 4 chain is `$90.85/bbl`. This does not conflict with the ledger's approximate Baton Rouge net margin of `$20.35/bbl`, because that ledger note is the asset's refining margin over crude cost (`21.50 + 4.75 - 5.90`), while the Week 4 integrated chain includes the crude value transfer economics and segment split.

## Geneva arbitrage

Geneva arbitrage is separate from the transfer-price cancellation. It does not make the transfer price create operating value inside upstream/refining; it moves value out of the operating segments into Trading when the internal price leaves an exploitable internal-external gap.

For the lazy midpoint:

- Gap to external market price: `73.70 - 46.20 = 27.50/bbl`
- Desk capture per barrel on arbitrageable volume: `27.50 * 35% = 9.625/bbl`
- Volume cap: `40,000 bbl/day`

Exact monthly convention and expected display rounding are blocked by the missing reference package.

## Conflicts and unresolved items

No numeric Week 4 spec-vs-ledger conflict was found in the markdown sources.

Unresolved before implementation:

- `halden-week4-data-package/` is absent.
- Exact aggregation/classification thresholds for disciplined/base/lax Week 4 cohort behavior are not supplied by the markdown sources read in this pass.
- Exact precision, rounding, display formatting, and monthly-volume convention for Geneva arbitrage are blocked by the missing package.
