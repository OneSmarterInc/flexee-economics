# Economic Dependencies

## Scope and source status

This inventory covers the Week 4 Stage 1 proof point using only the authoritative files currently available:

- `C:\Users\sakas\Documents\Flexee-economics\HANDOFF-README.md`
- `C:\Users\sakas\Documents\Flexee-economics\halden-constants-ledger.md`

The Week 4 specification, Week 4 reference package, Week 10 reference package, development instructions, calibration review, role charters, faculty guide, student guide, and design-v2 document are not present in the current workspace. Therefore this document lists constants and formulas known from the constants ledger, plus required Week 4 items that are blocked until the missing package is supplied.

## Constants authority

Authoritative constants ledger for this workspace:

`C:\Users\sakas\Documents\Flexee-economics\halden-constants-ledger.md`

SHA-256:

`62D26482535FCD6CA8D9EDC5644D8784CBDCF9F3879B6CC87A30392251328BBC`

Reason:

- It is the only constants ledger found in the searched project locations.
- Its title states it is the Halden Energy constants ledger and single source of economic truth.
- `HANDOFF-README.md` says the constants ledger wins if a spec and ledger disagree.

No second ledger was found, so no ledger-vs-ledger comparison was possible or needed. The exact filename named by the handoff, `constants-ledger.md`, was not found; `halden-constants-ledger.md` is treated as the exported ledger corresponding to that source until project owners supply another ledger.

No missing value below should be inferred or replaced.

## Known Week 4 dependencies from the constants ledger

| Name                                   | Value or formula                                                                      | Source                                  | Ledger? | Duplicate source seen?  | Conflict      |
| -------------------------------------- | ------------------------------------------------------------------------------------- | --------------------------------------- | ------- | ----------------------- | ------------- |
| WTI reference                          | `$74.00/bbl`; live-path reference                                                     | `halden-constants-ledger.md:14`         | Yes     | No                      | None observed |
| Brent spread                           | `Brent = WTI + $4.50`                                                                 | `halden-constants-ledger.md:15`, `:104` | Yes     | Duplicate within ledger | None observed |
| Permian gathering cost                 | `$2.10/bbl`                                                                           | `halden-constants-ledger.md:19`         | Yes     | No                      | None observed |
| Permian to Baton Rouge transport       | `$3.20/bbl`                                                                           | `halden-constants-ledger.md:20`         | Yes     | No                      | None observed |
| Permian wellhead discount              | `$3.50/bbl` to WTI                                                                    | `halden-constants-ledger.md:21`         | Yes     | No                      | None observed |
| Permian lifting costs by vintage       | 2021 `$11.50`, 2022 `$10.20`, 2023 `$9.40`, 2024 `$8.80/bbl`                          | `halden-constants-ledger.md:22`         | Yes     | No                      | None observed |
| Delivered marginal cost to Baton Rouge | `$14.10/bbl` for 2024 vintage                                                         | `halden-constants-ledger.md:23`         | Yes     | No                      | None observed |
| Baton Rouge complexity premium         | `$4.75/bbl` over benchmark crack                                                      | `halden-constants-ledger.md:27`         | Yes     | No                      | None observed |
| Baton Rouge refining opex              | `$5.90/bbl`                                                                           | `halden-constants-ledger.md:28`         | Yes     | No                      | None observed |
| Gulf Coast 3-2-1 crack reference       | `$21.50/bbl`; live-path reference                                                     | `halden-constants-ledger.md:29`         | Yes     | No                      | None observed |
| Baton Rouge reference net margin       | Approximately `$20.35/bbl`                                                            | `halden-constants-ledger.md:30`         | Yes     | No                      | None observed |
| Baton Rouge product yield              | Gasoline `48%`, diesel `32%`, jet `14%`, other `6%`                                   | `halden-constants-ledger.md:31`         | Yes     | No                      | None observed |
| Trading arbitrage capture rate         | `35%` of internal-external price gap                                                  | `halden-constants-ledger.md:80`         | Yes     | No                      | None observed |
| Trading max arbitrage volume           | `40,000 bbl/day`                                                                      | `halden-constants-ledger.md:81`         | Yes     | No                      | None observed |
| Segment compensation targets           | Upstream `$45.00/bbl`; Refining `$30.00/bbl`; Trading desk P&L vs budget              | `halden-constants-ledger.md:87`         | Yes     | No                      | None observed |
| Integrated margin invariance           | Transfer price changes segment split, not integrated margin                           | `halden-constants-ledger.md:117`        | Yes     | No                      | None observed |
| Integrated margin at reference prices  | `$76.75/bbl` on the Permian-to-Baton-Rouge chain at WTI `$74`                         | `halden-constants-ledger.md:118`        | Yes     | No                      | None observed |
| Market-based transfer price option     | Transfer price `$73.70`; upstream `+14.60`, refining `-12.85` vs target               | `halden-constants-ledger.md:119`        | Yes     | No                      | None observed |
| Marginal-cost transfer price option    | Transfer price `$18.70`; upstream `-40.40`, refining `+42.15` vs target               | `halden-constants-ledger.md:119`        | Yes     | No                      | None observed |
| Lazy midpoint transfer price option    | Transfer price `$46.20`; upstream `-12.90`, refining `+14.65` vs target               | `halden-constants-ledger.md:119`        | Yes     | No                      | None observed |
| Week 4 to Week 6 mechanism             | Transfer-pricing discipline affects cost of capital; not a cross-cohort market window | `halden-constants-ledger.md:129`        | Yes     | No                      | None observed |
| Cohort discount-rate schedule          | Disciplined `6.5%`, base `8.5%`, lax `11.0%`                                          | `halden-constants-ledger.md:131`        | Yes     | No                      | None observed |
| Capital-envelope schedule              | Disciplined `$1,520M`, base `$1,150M`, lax `$950M`                                    | `halden-constants-ledger.md:131`        | Yes     | No                      | None observed |

## Known derived formulas and relationships

| Formula or relationship                                             | Source                                   | Implementation note                                                                                                                                        |
| ------------------------------------------------------------------- | ---------------------------------------- | ---------------------------------------------------------------------------------------------------------------------------------------------------------- |
| `Brent = WTI + 4.50`                                                | `halden-constants-ledger.md:104`         | Store as a versioned formula/configuration value, not inline in controllers.                                                                               |
| `Permian realized price = WTI - 3.50`                               | `halden-constants-ledger.md:21`          | Used for upstream segment economics if Week 4 asks for Permian/Baton Rouge transfer pricing.                                                               |
| `Delivered marginal cost to Baton Rouge = 14.10/bbl`                | `halden-constants-ledger.md:23`          | Ledger gives authoritative value; do not recompute differently unless the Week 4 package specifies a reconciled calculation.                               |
| Transfer price does not change integrated margin                    | `halden-constants-ledger.md:117`         | Week 4 scoring should reward recognition of allocation/incentive effects rather than false enterprise-value changes, subject to the missing Week 4 rubric. |
| Integrated margin at reference prices equals `76.75/bbl`            | `halden-constants-ledger.md:118`         | Use as a regression invariant once the Week 4 reference package is available.                                                                              |
| Week 4 discipline maps to Week 6 discount rate and capital envelope | `halden-constants-ledger.md:129`, `:131` | Persist the Week 4 classification or output needed by Week 6; do not encode it only in display text.                                                       |

## Blocked Week 4 dependencies

These are required for implementation but absent from the current workspace:

| Required item                        | Why needed                                                     | Status  |
| ------------------------------------ | -------------------------------------------------------------- | ------- |
| Week 4 data package manifest         | Defines actual files, generated outputs, and workflow          | Missing |
| Week 4 CSV inputs                    | Needed for student-facing datasets and golden regression tests | Missing |
| Week 4 Excel workbook formulas       | Needed to replicate expected calculations and rounding         | Missing |
| Week 4 notebooks                     | Needed to understand generation and validation workflow        | Missing |
| Week 4 expected outputs              | Needed for economic regression tests                           | Missing |
| `halden-week4-data-package-spec.md`  | Needed for decisions, memo prompt, faculty layer, and scoring  | Missing |
| `halden-week10-data-package/`        | Needed to understand convergence-week architecture pattern     | Missing |
| `halden-week10-data-package-spec.md` | Needed to compare single-decision and convergence-week specs   | Missing |
| `halden-development-instructions.md` | Needed for exact Laravel architecture requirements             | Missing |
| `halden-calibration-review.md`       | Needed to classify provisional calibration risks               | Missing |
| `halden-energy-role-charters.md`     | Needed to load seat-specific content                           | Missing |
| `halden-faculty-teaching-guide.md`   | Needed for faculty tool requirements                           | Missing |
| `halden-student-guide.md`            | Needed for student onboarding and help content                 | Missing |
| `halden-energy-design-v2.md`         | Useful for design intent when specs are unclear                | Missing |

## Conflicts and ambiguities

No conflicts can be confirmed because the only economic source currently available is the constants ledger. The missing Week 4 specification and reference package must be checked against the ledger before implementation.

## Implementation guardrails

- Do not implement missing Week 4 formulas from memory or general managerial economics assumptions.
- Treat all available values as provisional design-draft calibration, not market data.
- Store every Week 4 economic constant with source metadata and version.
- Add tests that assert the integrated margin invariance and transfer-price split values once the package is available.
- Record any ledger/spec mismatch before writing the calculation code that depends on it.
