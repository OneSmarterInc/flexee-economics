# Batch 6C Implementation

Batch 6C connects the verified Week 4 economic resolution to consequence-link records. It maps only consequence records supported by the current authoritative Week 4 materials.

## Architecture

The implemented flow is:

Week4EconomicResolution
-> Week4ConsequenceResolver
-> ConsequenceLink records

The Week 4 economic engine remains pure. KPI calculation, ranking, standing transitions, advisor mechanics, and faculty trace UI do not own this mapping.

## Consequence Definitions

`Week4ConsequenceDefinitionCatalog` creates two versioned definitions:

- `week4_transfer_price_segment_margin_impact`
- `week4_transfer_price_geneva_arbitrage_record`

Both definitions use `EconomicResolution` as the source and target because Batch 6C records causal facts from the resolved Week 4 result without inventing future state models.

## Stored Links

The resolver creates immutable links for:

- segment-margin impact from the selected transfer price
- Geneva arbitrage exposure from the deterministic Week 4 result

Each link stores the definition key/version, runtime week, explanation, and metadata snapshot from the economic resolution.

## Idempotency

The resolver returns existing links for the same resolution, definition key, and definition version. Re-running the Week 4 resolution does not duplicate consequence links.

## Deferred Features

The following remain intentionally out of scope:

- automatic standing changes
- Delacroix or other counterparty transition rules
- Week 4 to Week 6 cost-of-capital classification
- advisor consultations
- causal trace UI
- faculty what-if console
- LLM

The docs currently state that Week 4 to Week 6 thresholds and standing effects are not supplied, so Batch 6C records the supported causal facts without inferring those future mechanics.
