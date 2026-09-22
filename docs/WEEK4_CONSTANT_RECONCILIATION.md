# Week 4 Constant Reconciliation

Inventory date: 2026-09-22.

Sources reconciled:

- `C:\Users\sakas\Documents\Flexee-economics\halden-week4-data-package-spec.md`
- `C:\Users\sakas\Documents\Flexee-economics\halden-constants-ledger.md`
- `C:\Users\sakas\Documents\Flexee-economics\halden-calibration-review.md`
- `C:\Users\sakas\Documents\Flexee-economics\permian_lifting.csv`
- `C:\Users\sakas\Documents\Flexee-economics\cost_constants.csv`
- `C:\Users\sakas\Documents\Flexee-economics\segment_comp.csv`
- `C:\Users\sakas\Documents\Flexee-economics\worked_example_prior.csv`
- `C:\Users\sakas\Documents\Flexee-economics\halden_week4.xlsx`
- `C:\Users\sakas\Documents\Flexee-economics\halden_week4_analysis.ipynb`

The constants ledger remains the single source of economic truth. If the Week 4 spec and ledger disagree, the ledger wins and the disagreement must be flagged before implementation.

## Status

The Week 4 markdown spec, ledger, supplied CSVs, and workbook reconcile on the numeric transfer-pricing values needed for mathematical invariants. Full reference-package parity remains blocked because the supplied Week 4 package lacks a Week 4 manifest, expected outputs, faculty solution artifacts, and an executable notebook path layout.

## Reconciliation table

| Item                                     | Ledger value                                                                          | Week 4 spec value                                                                                 | Reference package value                                                                                        | Match/conflict           | Notes                                                                                              |
| ---------------------------------------- | ------------------------------------------------------------------------------------- | ------------------------------------------------------------------------------------------------- | -------------------------------------------------------------------------------------------------------------- | ------------------------ | -------------------------------------------------------------------------------------------------- |
| WTI reference                            | `$74.00/bbl`, live-path reference                                                     | `$74.00/bbl`, Week 4-specific live-path value                                                     | `cost_constants.csv` `wti=74.00`; workbook `Data — Permian!B10=74`                                             | Match                    | WTI moves weekly elsewhere.                                                                        |
| Permian lifting costs                    | 2021 `$11.50`, 2022 `$10.20`, 2023 `$9.40`, 2024 `$8.80/bbl`; 2024 is marginal barrel | Same table and marginal-barrel designation                                                        | `permian_lifting.csv` rows for 2021-2024 match                                                                 | Match                    | 2024 vintage drives marginal-cost analysis.                                                        |
| Gathering cost                           | `$2.10/bbl`                                                                           | `$2.10/bbl`                                                                                       | `cost_constants.csv` `gathering_cost=2.10`; workbook `B12=2.10`                                                | Match                    | Company-wide Permian constant.                                                                     |
| Transport cost                           | `$3.20/bbl` Permian to Baton Rouge                                                    | `$3.20/bbl`                                                                                       | `cost_constants.csv` `transport_permian_to_br=3.20`; workbook `B13=3.20`                                       | Match                    | Company-wide constant.                                                                             |
| Delivered marginal cost                  | `$14.10/bbl`                                                                          | `8.80 + 2.10 + 3.20 = $14.10/bbl`                                                                 | Workbook `Your Analysis!B8` formula `=8.8+'Data — Permian'!B12+'Data — Permian'!B13`; expected fixture `14.10` | Match                    | True short-run floor before capital recovery charge.                                               |
| Capital recovery charge                  | Not listed in ledger cost section; Week 4 spec says `$4.60/bbl`                       | `$4.60/bbl`                                                                                       | `cost_constants.csv` `sr_capital_charge=4.60`; workbook `B14=4.60`                                             | No conflict              | Week 4-specific value; methodology carries.                                                        |
| Marginal-cost transfer price             | `$18.70/bbl`                                                                          | `$14.10 + $4.60 = $18.70/bbl`                                                                     | Workbook worked example formula `=B8+B9`; expected fixture `18.70`                                             | Match                    | Integrated-optimal transfer-price anchor.                                                          |
| Market transfer price                    | `$73.70/bbl`                                                                          | `$73.70/bbl`                                                                                      | Derived from `70.50 + 3.20`; expected fixture `73.70`                                                          | Match                    | Current-week student workbook leaves this blank for students.                                      |
| Midpoint/lazy option                     | `$46.20/bbl`                                                                          | `$46.20/bbl`                                                                                      | Expected fixture `(73.70 + 18.70)/2 = 46.20`; notebook TODO describes average                                  | Match                    | Gap to external market price is `$27.50/bbl`.                                                      |
| WTI discount/wellhead                    | `$3.50/bbl` discount; wellhead price implied `$70.50/bbl`                             | `$3.50/bbl`; realized wellhead `$70.50/bbl`                                                       | `cost_constants.csv` `3.50`; expected fixture `70.50`                                                          | Match                    | `74.00 - 3.50 = 70.50`.                                                                            |
| Gulf Coast crack                         | `$21.50/bbl` live-path reference                                                      | `$21.50/bbl`                                                                                      | `cost_constants.csv` `gc_crack_321=21.50`; workbook `B15=21.50`                                                | Match                    | Company-wide Gulf Coast reference relationship.                                                    |
| Complexity premium                       | `$4.75/bbl`                                                                           | `$4.75/bbl`                                                                                       | `cost_constants.csv` `br_complexity_premium=4.75`; workbook `B16=4.75`                                         | Match                    | Baton Rouge asset property.                                                                        |
| BR realized gross margin over crude cost | Ledger implies crack plus complexity; net margin noted approximately `$20.35/bbl`     | `$26.25/bbl` gross over crude cost                                                                | Expected fixture product-slate build uses `21.50 + 4.75`, less opex `5.90`                                     | Match with rounding note | `21.50 + 4.75 = 26.25`; `26.25 - 5.90 = 20.35`.                                                    |
| Refinery opex                            | `$5.90/bbl`                                                                           | `$5.90/bbl`                                                                                       | `cost_constants.csv` `br_opex=5.90`; workbook `B17=5.90`                                                       | Match                    | Baton Rouge asset property.                                                                        |
| Geneva capture rate                      | `35%` of internal-external gap                                                        | `35%`                                                                                             | `cost_constants.csv` `geneva_capture_rate=0.35`; workbook `B18=0.35`; notebook TODO uses `0.35`                | Match                    | Applies separately from integrated operating segment split.                                        |
| Geneva capacity                          | `40,000 bbl/day`                                                                      | `40,000 bbl/day`                                                                                  | `cost_constants.csv` `geneva_max_volume=40000`; workbook `B19=40000`                                           | Match                    | No time-basis beyond bbl/day supplied.                                                             |
| Upstream target                          | `$45.00/bbl`                                                                          | `$45.00/bbl`                                                                                      | `segment_comp.csv` upstream target `45.00`; workbook `Data — Compensation!C5=45`                               | Match                    | Segment compensation target.                                                                       |
| Refining target                          | `$30.00/bbl`                                                                          | `$30.00/bbl`                                                                                      | `segment_comp.csv` refining target `30.00`; workbook `Data — Compensation!C6=30`                               | Match                    | Segment compensation target.                                                                       |
| Market segment outcomes                  | Upstream `+14.60`, refining `-12.85` vs target                                        | Same faculty answer-key table                                                                     | Expected fixture: upstream `59.60`, refining `17.15`, deltas `+14.60`/`-12.85`                                 | Match                    | No separate faculty solution file supplied.                                                        |
| Marginal segment outcomes                | Upstream `-40.40`, refining `+42.15` vs target                                        | Same faculty answer-key table                                                                     | Expected fixture: upstream `4.60`, refining `72.15`, deltas `-40.40`/`+42.15`                                  | Match                    | No separate faculty solution file supplied.                                                        |
| Midpoint segment outcomes                | Upstream `-12.90`, refining `+14.65` vs target                                        | Same faculty answer-key table                                                                     | Expected fixture: upstream `32.10`, refining `44.65`, deltas `-12.90`/`+14.65`                                 | Match                    | No separate faculty solution file supplied.                                                        |
| Integrated margin                        | `$76.75/bbl` at WTI `$74`; invariant to transfer price                                | Invariance required; worked current-week blank asks students to compute it                        | Expected fixture `76.75`; workbook worked example reproduces `$68.25` prior case                               | Match                    | Current-week workbook cells are blank by design.                                                   |
| Week 4 -> Week 6 rates                   | Disciplined `6.5%`, base `8.5%`, lax `11.0%`; envelopes `$1,520M`, `$1,150M`, `$950M` | Spec says cohort transfer-pricing behavior shapes Week 6 constraints but does not list thresholds | Not present in supplied Week 4 package files                                                                   | Partial                  | Ledger supplies schedules; current sources do not give classification thresholds/aggregation rule. |

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

The package confirms per-barrel capture and a `bbl/day` volume cap but does not specify a monthly convention. It also does not specify whether the three-decimal `9.625/bbl` should display as `$9.62`, `$9.63`, or remain unrounded in downstream calculations.

## Conflicts and unresolved items

No numeric Week 4 spec-vs-ledger-package conflict was found in the supplied CSV/workbook values.

Unresolved before implementation:

- Week 4-specific manifest is absent.
- The notebook cannot execute as supplied because it expects `data/*.csv` while CSVs were supplied flat.
- Faculty solution workbook/notebook and expected-output files are absent.
- Exact aggregation/classification thresholds for disciplined/base/lax Week 4 cohort behavior are not supplied by the markdown sources read in this pass.
- Exact rounding method and monthly-volume convention for Geneva arbitrage remain unspecified.
