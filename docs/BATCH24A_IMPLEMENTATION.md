# Batch 24A - Week 11 Kessana Package Validation and Economic Engine

## Purpose

Batch 24A validates the authoritative Week 11 Kessana package and adds a deterministic, package-backed economic engine.

This batch does not add Week 11 runtime execution, persistence, KPI/ranking, standing changes, consequence links, what-if support, LLM behavior, or UI.

## Package Source

The engine consumes:

```text
halden-week11-data-package/
```

Authoritative files read:

- `MANIFEST.md`
- `VALIDATION_16A.md`
- `data/psc_terms.csv`
- `data/reserves.csv`
- `data/exit_and_sunk.csv`
- `data/take_grid.csv`
- `data/comparable_terms.csv`
- `data/worked_example_field.csv`
- `fixtures/week11_golden.json`
- `fixtures/provenance.json`

The package is a Batch 16A `PASS` package. Source artifacts were not modified.

## Package Validation

The package includes:

- canonical CSV inputs;
- student workbook;
- student notebook;
- faculty solution workbook;
- faculty solution notebook;
- golden fixture;
- SHA-256 provenance.

`Week11EconomicEngineTest` validates that every hash listed in `fixtures/provenance.json` matches the source artifact on disk.

Package version:

```text
1.0.0-draft
```

## Engine Architecture

```text
halden-week11-data-package
        |
        v
Week11ReferencePackage
        |
        v
Week11EconomicInputs
        |
        v
Week11EconomicEngine
        |
        v
Week11EconomicResult
```

## Input Model

`Week11EconomicInputs` exposes package-backed data:

- PSC terms: Brent, Kessana differential, lifting cost, current take, demanded take;
- reserves: remaining reserves, production years, Kessana risk rate;
- exit/sunk reference: exit value and sunk capital reference;
- take grid: current, mid, demanded, harsh;
- comparable fiscal terms;
- worked-example adjacent field;
- golden fixture;
- source hashes;
- package version.

## Formulas Implemented

The engine implements only relationships demonstrated by the package:

```text
realized_price = brent + kessana_differential
profit_oil = realized_price - lifting
annual_mbbl = remaining_mbbl / production_years
annuity_factor = (1 - 1 / (1 + risk_rate) ^ production_years) / risk_rate
company_margin = profit_oil * (1 - government_take)
pv_stay = company_margin * annual_mbbl * annuity_factor
stay_minus_exit = pv_stay - exit_value
indifference_take = 1 - exit_value / (profit_oil * annual_mbbl * annuity_factor)
```

The sunk capital value is retained as package context only. It is not used in the forward stay/exit calculation.

## Output Model

`Week11EconomicResult` includes:

- engine identifier and version;
- package version;
- realized price;
- profit oil;
- annual production;
- annuity factor;
- per-take scenario results;
- exit value;
- indifference take;
- comparable fiscal-term min/max;
- demanded-take comparability flag;
- ordering assertion booleans;
- sunk-invariance flag;
- worked-example outputs;
- input snapshot;
- output snapshot.

## Golden Fixture Parity

The engine matches `fixtures/week11_golden.json` for:

- `profit_oil = 67.0`;
- `annual_mbbl = 34.0`;
- `annuity_factor = 5.216116`;
- current, mid, demanded, and harsh company margins;
- current, mid, demanded, and harsh PV stay values;
- exit value;
- demanded stay-minus-exit;
- indifference take;
- comparable range;
- demanded take.

Golden comparisons use:

```text
relative = 1e-3
absolute = 1e-5
```

## Worked Example

The package worked example is validated against the golden fixture:

- annuity factor;
- PV before take change;
- PV after take change;
- indifference take.

## Numeric Precision

The engine uses `Brick\Math\BigDecimal` for parity-sensitive calculations. No binary-float arithmetic is used for economic calculations.

## Deferred

Still deferred:

- Week 11 runtime integration;
- Week 11 persistence;
- Week 11 KPI/ranking integration;
- Week 11 standing transitions;
- Week 11 consequence links;
- Week 11 what-if;
- Week 11 LLM interpretation;
- Week 11 UI;
- Tetteh standing and Week 6 Kessana-capital runtime consumption.
