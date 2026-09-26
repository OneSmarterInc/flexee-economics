# Batch 19A - Week 9 Economic Engine

Batch 19A implements the package-backed Week 9 Cordell rebrand economic engine. It does not integrate Week 9 into runtime execution, persistence, KPI/ranking, standing, consequences, what-if, or LLM workflows.

## Source Material Read

Implementation was based on:

- `HANDOFF-README.md`
- `SAKSHI-BATCH-NOTE.md`
- `halden-week9-data-package/MANIFEST.md`
- `halden-week9-data-package/VALIDATION_16A.md`
- `halden-week9-data-package-spec.md`
- `halden-constants-ledger.md`
- `halden-week9-data-package/fixtures/week9_golden.json`

The package is the source of truth for Week 9 formulas and parameters.

## Package Source

The engine reads:

```text
halden-week9-data-package/
    data/markets.csv
    data/nonfuel_states.csv
    data/rebrand_params.csv
    data/worked_example_markets.csv
    fixtures/week9_golden.json
    fixtures/provenance.json
```

## Engine Boundary

```text
Week9ReferencePackage
        |
        v
Week9EconomicInputs
        |
        v
Week9EconomicEngine
        |
        v
Week9EconomicResult
```

Engine identifier:

```text
week9_cordell_rebrand
```

Engine version:

```text
week9_cordell_rebrand_v1
```

## Economic Calculations

The engine implements the package formulas:

```text
net per fill = Halden benefit per fill - Cordell keep uplift per fill
cost per site = total rebrand cost / total sites
market cost = cost per site * market sites
market gain = net per fill * market sites * fills per site per year
payback years = market cost / annual gain
state-adjusted gain = base gain * selected non-fuel state / base non-fuel state
```

The sensible partial rebrand defaults to markets with positive net-per-fill economics:

```text
gulf_secondary
southeast_edge
```

The core market remains negative:

```text
LA_MS_core net per fill = -0.045
```

## Calibration Lever

`fills_per_site_year = 180,000` is consumed from the package. It is not hard-coded in the engine and remains a documented calibration lever for market-data refresh.

## Golden Targets

The tests validate the package fixture values using the package tolerance:

```text
relative = 1e-3
absolute = 1e-5
```

Key targets include:

- cost per site: `79069.7674`
- core net per fill: `-0.045`
- Gulf secondary payback: `14.6425`
- Southeast edge payback: `5.491`
- partial gain: `22.68`
- partial cost: `193.7209`
- partial payback: `8.5415`
- full net gain: `7.695`
- price-war partial payback: `9.4406`

## Deferred

Still deferred:

- Week 9 runtime integration;
- Week 9 evaluation persistence;
- Week 9 decision/memo submission mapping;
- Week 9 KPI/ranking integration;
- Week 7 to Week 9 cohort feedback production application;
- standing changes;
- consequence links;
- Week 9 what-if;
- LLM interpretation.

## Verification

Added focused tests:

- `tests/Feature/Economics/Week9EconomicEngineTest.php`

Coverage includes package loading, golden fixture parity, worked-example parity, ordering assertions, fills-per-site calibration behavior, invalid state rejection, and absence of cohort-feedback mutation.
