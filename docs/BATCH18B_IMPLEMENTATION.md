# Batch 18B - Week 8 OPEC Scenario Economic Engine

Batch 18B implements the Week 8 OPEC/scenario economic engine against the authoritative Week 8 package. It does not integrate Week 8 into week execution, persistence, KPI/ranking, standing, consequences, what-if, or LLM workflows.

## Package Source

The engine reads:

```text
halden-week8-data-package/
    data/baseline_state.csv
    data/opec_scenarios.csv
    data/propagation_coefficients.csv
    data/worked_example_prior.csv
    fixtures/week8_golden.json
    fixtures/provenance.json
```

The package reader is `Week8ReferencePackage`, analogous to the Week 6 package reader. Package values remain outside engine code.

## Engine Boundary

```text
Week8ReferencePackage
        |
        v
Week8EconomicInputs
        |
        v
Week8EconomicEngine
        |
        v
Week8EconomicResult
```

Engine identifier:

```text
week8_opec_scenario
```

Engine version:

```text
week8_opec_scenario_v1
```

## Scenario Model

The package defines three OPEC scenarios:

| Scenario        | Probability | Resolved WTI | Delta WTI |
| --------------- | ----------: | -----------: | --------: |
| `holds_full`    |      `0.35` |      `88.00` |   `14.00` |
| `holds_partial` |      `0.40` |      `81.00` |    `7.00` |
| `fails`         |      `0.25` |      `70.00` |   `-4.00` |

Probabilities are validated to sum to `1.000000`.

## Economic Calculations

The engine applies the package propagation coefficients:

| Channel                    | Coefficient |
| -------------------------- | ----------: |
| `upstream_realization`     |      `1.00` |
| `refining_crack`           |     `-0.35` |
| `retail_passthrough`       |      `0.60` |
| `retail_demand_elasticity` |     `-0.05` |

Implemented calculations:

```text
upstream impact = delta WTI * upstream realization
refining crack = baseline crack + delta WTI * refining crack coefficient
retail volume percent =
    retail elasticity
    * ((retail pass-through * delta WTI / 42) / pump base)
    * 100
```

The `42` denominator follows the workbook/notebook package formula converting crude dollars per barrel to dollars per gallon.

## Numeric Strategy

The engine uses `Brick\Math\BigDecimal` for deterministic decimal arithmetic.

Display precision follows the package plan:

- probabilities: six decimals;
- WTI/upstream/crack: two decimals;
- retail volume percent: three decimals;
- coefficients: four to six decimals depending on context.

Retail volume percentage uses half-even rounding for the three-decimal package display, matching the fixture behavior where `-0.3125` displays as `-0.312`.

## Prediction vs Realization

Prediction and realization are separate:

- prediction distribution: optional student/team probability estimate;
- package distribution: authoritative simulation probabilities;
- realization: optional selected actual scenario.

The engine never overwrites the prediction distribution with the realized scenario or package distribution.

## OPEC vs Cohort Response Separation

Batch 18B implements only:

```text
OPEC shock -> WTI -> upstream/refining/retail propagation
```

It does not implement:

```text
Week 6 aggregate Gulf Coast capacity additions -> Week 8 refining margin
```

The `refining_crack = -0.35` coefficient remains an OPEC shock propagation coefficient and must not be reused as the missing Week 6 to Week 8 cohort-response function.

## Golden Tests

Added focused tests in `tests/Feature/Economics/Week8EconomicEngineTest.php`.

They verify:

- reference package loading;
- scenario probabilities;
- expected WTI `80.70`;
- expected upstream impact `6.70`;
- expected crack `19.16`;
- per-scenario golden parity;
- invalid probability rejection;
- worked-example parity (`3.40` upstream, `20.31` crack);
- prediction/realization separation;
- decimal precision;
- no cohort-feedback mutation.

Focused result:

```text
8 tests / 38 assertions
```

## Deferred

- Week 8 runtime integration;
- persistence model for Week 8 prediction and realization;
- Week 8 decision/memo submission fields;
- KPI/ranking integration;
- Week 9 consequences;
- Week 6 to Week 8 cohort-response production parameters;
- Week 8 what-if;
- LLM interpretation.
