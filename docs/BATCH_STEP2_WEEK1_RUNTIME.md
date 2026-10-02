# Batch Step 2 - Week 1 Runtime

Current checkpoint scope: Week 1 asset-register runtime only.

## Authoritative Sources

The implementation reads Week 1 from:

- `halden-week1-data-package/MANIFEST.md`
- `halden-week1-data-package/VALIDATION_16A.md`
- `halden-week1-data-package/data/*.csv`
- `halden-week1-data-package/fixtures/week1_golden.json`
- `halden-week1-data-package/fixtures/provenance.json`
- `storage/app/tmp/complete-handoff/halden-week1-data-package-spec.md`
- `storage/app/tmp/complete-handoff/halden-7wk-week1-spec.md`

The package is treated as the authority. The application does not infer asset economics from the scenario narrative.

## Runtime Architecture

```text
Week 1 package
        |
        v
Week1ReferencePackage
        |
        v
Week1EconomicInputs
        |
        v
Week1EconomicEngine
        |
        v
Week1EconomicEvaluation
        |
        v
WeekExecutionService
```

Week 1 now follows the same historical-record pattern used by later runtime weeks. A raw student submission does not count as historical economic state until faculty execution creates a persisted evaluation.

## Student Decision Model

The demo seed now creates a versioned Week 1 decision form:

- `permian_rig_count`
- `rotterdam_review_posture`
- `first_meeting_choice`

It also creates a required Week 1 memo. The submitted answers are captured in the evaluation input snapshot for later reconstruction, but the economic calculations remain package-backed.

## Economic Outputs

The engine calculates the package-defined outputs:

- Permian realized price and margin
- Norwegian pre-tax and post-tax margin
- Norwegian after-tax effect of a `$2/bbl` loss
- Kessana company margin
- Baton Rouge net margin
- Rotterdam contribution, net margin, shutdown crack, and current crack
- Singapore Halden-share economics
- economic ranking
- reported-profit ranking
- worked-example parity

## Golden Parity

The golden fixture remains the regression oracle:

- Permian margin: `56.4`
- Norway post-tax: `10.34`
- Kessana company: `25.46`
- Baton Rouge net: `20.35`
- Rotterdam net: `-0.3`
- economic rank differs from reported rank

Numeric calculations use `Brick\Math\BigDecimal` and package-defined values from CSVs.

## Runtime Behavior

`WeekExecutionService` now handles Week 1 in `resolve_decisions` by creating immutable `Week1EconomicEvaluation` records. KPI, ranking, consequence, standing, and cohort effects remain deferred unless a future authoritative mapping defines them.

Student and faculty status views now use persisted Week 1 evaluations when deciding whether Week 1 is resolved.

## Seven-Week Compatibility

The Week 1 runtime works with a seven-week variant. The seven-week Week 1 spec says Week 1 economics are identical to the fourteen-week Week 1 package, so the runtime uses the same package-backed engine. Window 2 cohort behavior is not activated by Week 1 execution.

## Deferred Functionality

Not implemented in this batch:

- Week 1 KPI or ranking mapping
- Week 1 standing changes
- Week 1 consequence links
- Week 1 what-if
- Week 1 LLM interpretation
- Week 3, Week 7, Window 1, Window 3, or Week 2 implementation
