# Batch 26B Implementation

Batch 26B adds the deterministic Week 12 transition-portfolio economic engine. It does not add runtime integration, evaluation persistence, KPI/ranking, standing changes, consequence links, UI, what-if behavior, or LLM behavior.

## Package Source

The engine is backed by:

```text
halden-week12-data-package
```

The package source remains unchanged. The implementation consumes:

- `data/envelope.csv`
- `data/buckets.csv`
- `data/projects.csv`
- `data/carbon_scenarios.csv`
- `data/demand_scenarios.csv`
- `data/worked_example_projects.csv`
- `fixtures/week12_golden.json`
- `fixtures/provenance.json`

## Engine Architecture

```text
Week12ReferencePackage
        ↓
Week12EconomicInputs
        ↓
Week12EconomicEngine
        ↓
Week12EconomicResult
```

Classes added:

- `App\Domain\Economics\Week12\Week12ReferencePackage`
- `App\Domain\Economics\Week12\Week12EconomicInputs`
- `App\Domain\Economics\Week12\Week12EconomicEngine`
- `App\Domain\Economics\Week12\Week12EconomicResult`
- `App\Domain\Economics\Week12\Week12PortfolioResult`

The engine identifier is:

```text
week12_transition_portfolio
```

The engine version is:

```text
week12_transition_portfolio_v1
```

## Input Model

The reference package reader loads only canonical package data:

- capital envelope inputs;
- bucket floor and ceiling constraints;
- project costs, buckets, base NPVs, carbon sensitivities, and demand sensitivities;
- carbon scenarios;
- demand scenarios;
- worked-example inputs;
- golden fixture;
- provenance hashes;
- package version.

No project values, bucket ceilings, divestment proceeds, portfolio counts, or golden outputs are hard-coded into the engine.

## Portfolio Rules

The engine enumerates non-empty project portfolios from the package project list.

For each portfolio:

- positive project costs count as capital required;
- negative-cost projects are treated as divestment proceeds;
- available capital equals discretionary envelope plus selected divestment proceeds;
- bucket ceilings are checked against positive spend in each bucket;
- a portfolio is feasible only when both capital-envelope and bucket constraints pass.

The package-defined discretionary envelope is:

```text
total_envelope - sustaining_floor
```

The engine preserves feasibility analysis separately from project attractiveness. It does not collapse transition choices into a single score.

## Helix / Divestment Handling

The resolved Week 12 interlock is package-backed:

- Helix Rotterdam cost comes from `projects.csv`;
- adjacent-transition ceiling comes from `buckets.csv`;
- Euro retail divestment proceeds come from the negative cost in `projects.csv`;
- Helix Rotterdam plus offshore wind feasibility is evaluated through the same portfolio constraint logic as every other portfolio.

The engine reproduces the package behavior:

- Helix Rotterdam alone is feasible and fills the discretionary envelope;
- Helix Rotterdam plus offshore wind is infeasible without divestment;
- Helix Rotterdam plus offshore wind plus divestment is feasible;
- two portfolios are unlocked by divestment.

## Output Model

`Week12EconomicResult` includes:

- engine identifier/version;
- package version;
- status;
- input snapshot;
- output snapshot;
- discretionary envelope;
- envelope with divestment;
- Helix Rotterdam cost;
- adjacent-transition ceiling;
- Helix Rotterdam plus offshore wind cost;
- Helix/offshore/divestment interlock flag;
- feasible portfolio count;
- feasible portfolio count including Helix Rotterdam;
- portfolios unlocked by divestment;
- project scenario NPVs;
- project NPV ranges;
- worked-example outputs;
- portfolio feasibility results.

## Golden Tests

`tests/Feature/Economics/Week12EconomicEngineTest.php` validates:

- package loading;
- provenance hashes;
- headline golden fixture outputs;
- scenario NPV ranges;
- low-carbon/slow and high-carbon/collapse scenario outputs;
- portfolio feasibility;
- bucket ceiling failures;
- divestment unlock behavior;
- worked example parity;
- determinism;
- decimal-safe display outputs;
- no downstream KPI/ranking/standing/consequence/cohort state is created.

Focused Week 12 result:

```text
7 tests / 90 assertions
```

## Precision Strategy

The engine uses `Brick\Math\BigDecimal` for package calculations. Golden comparisons use the package tolerance:

- relative: `1e-3`
- absolute: `1e-5`

The implementation avoids binary-float comparisons in parity-sensitive tests.

## Deferred Functionality

Deferred to later batches:

- Week 12 runtime integration;
- Week 12 evaluation persistence;
- Week 12 KPI/ranking integration;
- standing transitions;
- consequence links;
- Week 12 UI;
- Week 12 what-if support;
- LLM interpretation.
