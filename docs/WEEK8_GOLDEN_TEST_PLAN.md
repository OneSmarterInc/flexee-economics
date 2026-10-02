# Week 8 Golden Test Plan

Batch 18A validated the Week 8 package as a Laravel regression oracle. Batch 18B now implements the package-backed Week 8 economic engine and uses this fixture as the oracle.

## Source Oracle

Use:

- `halden-week8-data-package/data/*.csv`
- `halden-week8-data-package/fixtures/week8_golden.json`
- `halden-week8-data-package/fixtures/provenance.json`

Do not duplicate package inputs in tests unless a test needs a small explicit fixture for isolation.

## Required Golden Assertions

Week 8 engine tests verify:

- package files exist and hashes match provenance;
- scenario probabilities sum to `1.000000`;
- expected WTI equals `80.70`;
- propagation coefficients equal:
    - upstream realization `1.00`;
    - refining crack `-0.35`;
    - retail pass-through `0.60`;
    - retail demand elasticity `-0.05`;
- per-scenario outputs equal the golden fixture:
    - `holds_full`: upstream `14.00`, crack `16.60`, retail volume `-0.312%`;
    - `holds_partial`: upstream `7.00`, crack `19.05`, retail volume `-0.156%`;
    - `fails`: upstream `-4.00`, crack `22.90`, retail volume `0.089%`;
- integration conflict holds: when the cut holds full, upstream gains while refining crack falls below base;
- student artifacts do not expose current-week solved probabilities;
- faculty solution matches golden values.

## Numeric Precision

Observed package precision:

- probabilities: two decimals in CSV, compare to six decimal places after parsing;
- WTI and delta WTI: two decimals;
- cracks: two decimals;
- upstream impact: two decimals;
- retail volume percentage: three decimals in golden;
- coefficients: two decimals in CSV, compare to four decimal places after parsing;
- expected WTI: two decimals.

Use exact decimal-safe comparison in PHP. Avoid binary floating point in production engine code.

## Worked Example

The worked example uses:

| Scenario | Probability | Delta WTI |
| -------- | ----------: | --------: |
| `up`     |      `0.50` |   `10.00` |
| `flat`   |      `0.30` |    `0.00` |
| `down`   |      `0.20` |   `-8.00` |

Validated outputs:

- expected upstream impact: `3.40/bbl`;
- expected crack: `20.31/bbl`;
- workbook worked-example cached values and notebook execution agree on the method.

## Implemented In Batch 18B

`tests/Feature/Economics/Week8EconomicEngineTest.php` verifies:

- reference package loading and provenance metadata;
- scenario probabilities sum to `1.000000`;
- invalid prediction distributions are rejected;
- expected WTI equals `80.70`;
- expected upstream impact equals `6.70`;
- expected crack equals `19.16`;
- per-scenario outputs match `week8_golden.json`;
- worked example matches `3.40/bbl` upstream impact and `20.31/bbl` crack;
- prediction distribution and realized outcome remain separate;
- retail precision matches package display behavior;
- OPEC calculation does not create or mutate cohort feedback effects.

Focused result:

```text
8 tests / 38 assertions
```

## Deliberate Non-Assertions

Do not assert in Batch 18A:

- student team probability estimates;
- realized scenario selection;
- KPI/ranking effects;
- Week 10 consequences;
- Week 6 -> Week 8 cohort response effects.
