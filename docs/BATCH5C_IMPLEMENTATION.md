# Batch 5C Implementation

Batch 5C adds the ranking calculation foundation without building leaderboard UI, publication rules, visibility gates, standings, cohort feedback, causal trace, or consequence propagation.

## Architecture

The implemented foundation is:

KpiSnapshot
-> RankingCalculationService
-> RankingSnapshot

`RankingCalculationService` consumes KPI snapshots for one runtime week and writes immutable per-team ranking snapshots. Ranking snapshots are historical records and are never overwritten.

## Ranking Snapshot Model

`ranking_snapshots` stores:

- tenant
- section simulation
- runtime week
- team simulation
- team
- scope
- status
- composite score
- rank
- ranking version
- calculation input snapshot
- incomplete reason
- calculated timestamp

## Composite Score

The service uses the published KPI definition weights for `halden_kpi_v1`.

Composite score is the weighted sum of available KPI snapshot values for a team. A team is rankable only when all required KPI definitions have an available snapshot value.

Unavailable or missing KPI snapshots are not treated as zero. The ranking snapshot is instead stored with:

- `status = incomplete`
- `composite_score = null`
- `rank = null`
- an explicit incomplete reason

## Scope

Batch 5C supports within-section ranking calculation.

Cross-section ranking is represented by the `RankingScope` enum but intentionally rejected by the service until a later batch defines cross-section comparability and publication behavior.

## Determinism

Ranks are assigned by descending composite score. Ties receive the same rank, and team simulation id is used only as a deterministic ordering tiebreaker for snapshot creation.

## Deferred Features

The following remain intentionally out of scope:

- leaderboard UI
- publication rules
- student visibility gates
- cross-section ranking calculation
- ranking publication snapshots
- percentile display
- standing model
- cohort feedback
- causal trace
- advisor consultations
- consequence links
- LLM
- Week 10 consequences
