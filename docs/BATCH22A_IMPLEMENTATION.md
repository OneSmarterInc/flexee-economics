# Batch 22A - Week 5 Economic Engine

## Purpose

Batch 22A implements the deterministic Week 5 currency economics engine from the authoritative Week 5 package.

It does not add runtime integration, persistence, KPI/ranking effects, consequences, or UI.

The implemented boundary is:

```text
halden-week5-data-package
        |
        v
Week5ReferencePackage
        |
        v
Week5EconomicInputs
        |
        v
Week5EconomicEngine
        |
        v
Week5EconomicResult
```

## Authoritative Sources Read

The package-backed implementation was based on:

- `docs/AUTHORITATIVE_PACKAGE_BATCH_SOURCE_NOTE.md`
- `docs/AUTHORITATIVE_PACKAGE_BATCH_INVENTORY.md`
- `halden-week5-data-package/MANIFEST.md`
- `halden-week5-data-package/VALIDATION_16A.md`
- `halden-week5-data-package/fixtures/week5_golden.json`
- `halden-week5-data-package/fixtures/provenance.json`
- `halden-week5-data-package/data/*.csv`
- `halden-week5-data-package/faculty/halden_week5_FACULTY_SOLUTION.ipynb`

The literal `HANDOFF-README.md` and `SAKSHI-BATCH-NOTE.md` files were not present in this repository tree, but the authoritative batch source note and inventory preserve the supplied handoff rules used for package ingestion.

## Package Inputs

The engine reads package data from:

| Artifact                    | Runtime use                                                  |
| --------------------------- | ------------------------------------------------------------ |
| `fx_shock.csv`              | Pre/post FX rates for EURUSD, USDNOK, and USDSGD             |
| `entity_flows.csv`          | Entity-level annual pre-shock flows                          |
| `norway_unit_cost.csv`      | Norwegian lifting-cost baseline                              |
| `existing_hedges.csv`       | Existing EUR forward sale                                    |
| `forwards_options.csv`      | Forward rates and collar premiums retained in input snapshot |
| `worked_example_entity.csv` | Worked-example exposure inputs                               |
| `week5_golden.json`         | Golden regression oracle and tolerance                       |
| `provenance.json`           | Package version and artifact hashes                          |

No Week 5 economic values are hard-coded in application logic.

## Implemented Calculations

The engine mirrors the faculty solution notebook formulas:

- EUR/USD value change: `post / pre - 1`
- NOK and SGD USD-value change: `pre / post - 1`
- Norwegian cost benefit from krone weakness
- Norwegian post-shock lifting cost
- European retail translation impact
- Gain on the existing EUR forward sale
- Rotterdam net EUR exposure
- Rotterdam natural hedge ratio
- Rotterdam net impact
- Rotterdam gross-cost overhedge loss
- Singapore exposure impact
- Worked-example FX change, net exposure, net impact, and wrong gross hedge impact

All calculations use `Brick\Math\BigDecimal`.

## Golden Outputs

The focused tests verify the package fixture outputs with the 16A tolerance policy:

```text
relative = 1e-3
absolute = 1e-5
```

Pinned outputs include:

- `eur_change = -0.064516`
- `nok_usd_value_change = -0.076923`
- `sgd_usd_value_change = -0.007407`
- `norway_benefit_musd = 84.615385`
- `norway_lifting_post = 25.846154`
- `euro_retail_translation_musd = -77.419355`
- `existing_hedge_gain_musd = 19.354839`
- `rot_net_eur_musd = 250`
- `rot_natural_hedge_ratio = 0.9`
- `rot_net_impact_musd = -16.129032`
- `rot_overhedge_loss_musd = -145.16129`
- `sing_impact_musd = -2.222222`

## Tests Added

Added:

```text
tests/Feature/Economics/Week5EconomicEngineTest.php
```

Coverage:

- package reader loads Week 5 rates, entity flows, hedges, forwards, collar premiums, provenance, and version;
- engine matches every golden fixture result;
- worked example matches fixture outputs;
- package ordering assertions hold from calculation outputs;
- hedge notional remains package data/calibration, not an application constant;
- unknown reference keys are rejected;
- engine calculation does not create or mutate runtime evaluation records.

Focused result:

```text
7 tests / 44 assertions
```

## Deferred

Still deferred:

- Week 5 runtime integration;
- `Week5EconomicEvaluation` persistence;
- submission-to-input mapping;
- Week 5 KPI/ranking integration;
- hedge-coverage handoff into Week 10 runtime;
- consequences;
- standing changes;
- what-if;
- LLM interpretation;
- UI changes.
