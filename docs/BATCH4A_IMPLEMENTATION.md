# Batch 4A Implementation

Implemented: 2026-09-22.

## Scope

Batch 4A adds the deterministic Week 4 economic calculation layer only. It does not implement scoring, KPI generation, leaderboard publication, cohort feedback, faculty causal trace, LLM integration, persistence writes, or UI changes.

## Domain Location

The Week 4 economic engine lives in `app/Domain/Economics/Week4/`.

Domain files:

- `Week4EconomicInputs.php`
- `Week4EconomicEngine.php`
- `Week4TransferPrices.php`
- `Week4EconomicResult.php`
- `Week4GenevaArbitrageResult.php`

## Input Contract

`Week4EconomicInputs` carries structured decimal values for:

- Permian marginal lifting cost, gathering, transport, and wellhead discount;
- Week 4 WTI, Baton Rouge crack, complexity premium, and opex;
- short-run capital recovery charge;
- Geneva capture rate and maximum `bbl/day` capacity;
- upstream and refining compensation targets.

The domain layer does not read package files or hard-code CSV paths. Tests load the normalized package fixtures and convert them into the structured input object.

## Output Contract

`Week4EconomicEngine` returns explicit result objects for:

- delivered marginal cost;
- realized wellhead price;
- product slate value;
- integrated margin;
- market, marginal-cost, and lazy-midpoint transfer prices;
- per-transfer-price upstream/refining split and target deltas;
- Geneva gap, capture per barrel, daily volume capacity, and daily capture at the supplied capacity.

## Numeric Strategy

The engine uses `Brick\Math\BigDecimal` for decimal-safe arithmetic. It avoids PHP binary floating point for parity-sensitive calculations.

Package money outputs are formatted at exact two-decimal scale when the reference expects cents. Geneva `capture_per_bbl` is preserved as exact decimal text, so the reference value remains `9.625` without inventing display rounding.

## Calculations Implemented

- Delivered marginal cost: `lifting + gathering + transport`.
- Market transfer price: `(WTI - wellhead discount) + transport`.
- Marginal transfer price: `delivered marginal cost + short-run capital charge`.
- Lazy midpoint: `(market transfer price + marginal transfer price) / 2`.
- Product slate value: `(WTI - wellhead discount) + Gulf Coast crack + Baton Rouge complexity premium`.
- Integrated margin: `product slate value - delivered marginal cost - Baton Rouge opex`.
- Upstream margin: `transfer price - delivered marginal cost`.
- Refining margin: `integrated margin - upstream margin`.
- Compensation deltas: segment margin less upstream/refining target.
- Geneva arbitrage: `(external price - internal price) * capture rate`, with supplied `bbl/day` capacity retained separately.

## Golden Tests

`tests/Feature/Economics/Week4EconomicEngineTest.php` compares the engine against:

- `halden-week4-data-package/expected/week4_reference.json`
- `halden-week4-data-package/expected/worked_example.json`
- canonical CSV inputs under `halden-week4-data-package/data/`

The tests cover delivered cost, anchor prices, segment splits, compensation deltas, integrated-margin invariance at reference and arbitrary transfer prices, Geneva capture, full current-week reference parity, and worked-example parity.

## Excluded Functionality

Batch 4A intentionally excludes:

- decision-submission persistence changes;
- UI validation bounds for custom transfer prices;
- scoring and KPI values;
- rank or leaderboard publication;
- cohort feedback and Week 4 to Week 6 classification;
- standing changes and faculty causal trace;
- faculty what-if UI;
- LLM payload assembly or calls;
- Python generation.
