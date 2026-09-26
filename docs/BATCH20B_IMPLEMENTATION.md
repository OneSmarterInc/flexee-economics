# Batch 20B - Week 10 Convergence Economic Engine

Batch 20B adds the deterministic Week 10 convergence economic engine against the authoritative Week 10 package.

This batch does not connect Week 10 to `WeekExecutionService`, persistence, KPI/ranking, standing transitions, consequence links, cohort effects, what-if, UI, or LLM interpretation.

## Architecture

```text
Authoritative Week 10 package
        |
        v
Week10ReferencePackage
        |
        v
Week10ReferenceInputs
        +
Week10InheritedState
        |
        v
Week10ConvergenceEconomicEngine
        |
        v
Week10EconomicResult
```

The engine is pure application logic. It reads typed inputs and returns a result object with snapshots. It does not write database records.

## Package Source

The engine consumes:

- `halden-week10-data-package/data/product_elasticities.csv`
- `halden-week10-data-package/data/refinery_yields.csv`
- `halden-week10-data-package/data/recession_params.csv`
- `halden-week10-data-package/data/binding_rules.csv`
- `halden-week10-data-package/data/team_prior_state.csv`
- `halden-week10-data-package/fixtures/week10_golden.json`
- `halden-week10-data-package/fixtures/provenance.json`

No Week 10 economic values are hard-coded into the engine.

## Input Contract

`Week10ReferenceInputs` provides canonical package data:

- product income elasticities and blended demand shares;
- recession parameters;
- refinery yield mix;
- binding-rule thresholds;
- two prior-state fixture teams;
- golden fixture and source hashes.

`Week10InheritedState` represents team-specific inherited state:

- `cancellable_capex_musd`
- `crude_hedge_coverage`
- `br_reported_margin_strong`
- `straits_pacific_standing`
- `cash_cushion_musd`

Each inherited value carries a `Week10HistoricalDependency` with source week, source entity, source id/version, source value, and availability status.

## Historical Dependency Mapping

| Dependency                  | Source                                            |
| --------------------------- | ------------------------------------------------- |
| `cancellable_capex_musd`    | Week 6 capital allocation/evaluation              |
| `crude_hedge_coverage`      | Week 5 hedge mandate or hedge position            |
| `br_reported_margin_strong` | Week 4 transfer-price consequence                 |
| `straits_pacific_standing`  | standing history                                  |
| `cash_cushion_musd`         | Week 8 economic evaluation or cash-position state |

The reference fixture states in `team_prior_state.csv` are regression rows. Runtime must later assemble actual team state from platform history.

## Demand Calculation

The engine applies the package recession parameter to package income elasticities:

```text
demand_hit = gdp_change * income_elasticity
```

Golden outputs:

| Product  | Demand hit |
| -------- | ---------: |
| gasoline |  `-0.0105` |
| diesel   |  `-0.0255` |
| jet      |   `-0.048` |
| blended  |  `-0.0237` |

## Refinery Calculation

The engine applies package refinery yields to package demand hits:

```text
refinery_hit = sum(product_yield * product_demand_hit)
```

Golden outputs:

| Refinery    | Demand hit |
| ----------- | ---------: |
| Baton Rouge | `-0.01992` |
| Rotterdam   | `-0.02226` |
| Singapore   | `-0.02622` |

Singapore is the hardest-hit refinery in the golden fixture.

## Binding Constraint Calculation

The package defines five binding tests:

| Constraint      | Rule                                |
| --------------- | ----------------------------------- |
| capex           | `cancellable_capex_musd < 200.0`    |
| hedge           | `crude_hedge_coverage < 0.5`        |
| Delacroix cover | `br_reported_margin_strong = true`  |
| Straits Pacific | standing is `strained` or `hostile` |
| cash            | `cash_cushion_musd < 150.0`         |

Golden reference cases:

| Reference team          | Binding count |
| ----------------------- | ------------: |
| `reference_disciplined` |           `0` |
| `reference_constrained` |           `5` |

## Missing Dependency Handling

If any inherited dependency is unavailable, the engine returns:

```text
status = unresolved_dependency
```

It still computes package-only demand and refinery outputs, but does not default or calculate binding constraints. It reports the unresolved dependency keys in the result.

## Numeric Strategy

The engine uses `Brick\Math\BigDecimal`, matching the existing decimal-safe package-backed engines. Golden comparisons use the Week 10 package tolerance:

```text
relative = 1e-3
absolute = 1e-5
```

## Golden Tests

`tests/Feature/Economics/Week10EconomicEngineTest.php` verifies:

- product demand hits;
- blended demand hit;
- refinery-specific recession impacts;
- hardest-hit refinery;
- disciplined binding count;
- constrained binding count;
- historical dependency provenance;
- unresolved-dependency handling;
- determinism;
- worked-example parity;
- decimal precision;
- no downstream mutation.

## Deferred

Still deferred:

- Week 10 runtime integration;
- Week 10 evaluation persistence;
- real runtime state assembly from Week 4/5/6/8 and standing history;
- Week 10 KPI/ranking effects;
- standing transitions;
- consequence mapping;
- cohort effects;
- Week 10 what-if;
- Week 10 UI;
- LLM interpretation changes.
