# Batch 35C Week 8 Interim EBITDA Bridge

Starting checkpoint: `7b7c44a`.

Authoritative audit: `SAKSHI-AUDIT-FOLLOWUP-2026-10-06.md`.

## Scope

This batch implements the audit-authorized interim Week 8 EBITDA bridge:

```text
identifier: interim_week8_ebitda_bridge_v0
formula:    delta_wti * 0.65 * 91.25
```

The bridge is deliberately narrow. It aligns the two current consumers of Week 8 EBITDA effect:

- Week 10 cash-cushion consequence derivation.
- KPI input `i8_ebitda_effect_musd`.

It does not change Week 8 OPEC/scenario economics, Window 2 cohort effects, ranking normalization, seven-week sequencing, Week 14, or any future position-aware production mapping.

## Architecture

The shared calculation now lives in:

```text
App\Domain\Economics\Week8\Week8InterimEbitdaBridge
        ↓
Week8InterimEbitdaBridgeResult
        ↓
Week 10 cash cushion
        +
KPI financial state input i8_ebitda_effect_musd
```

The bridge reads the realized WTI movement from persisted `Week8EconomicEvaluation` records. It prefers `realization_snapshot.delta_wti`; when legacy evaluation records have no delta snapshot, it falls back to `realized_wti - 81.00`.

If neither value is available, the bridge returns no value. Week 10 cash-cushion derivation then remains unresolved rather than manufacturing a zero or fixture value.

## Provenance

Every bridge result carries:

- `bridge_identifier = interim_week8_ebitda_bridge_v0`;
- `bridge_status = interim`;
- formula terms;
- source Week 8 evaluation ID;
- Week 8 engine/package identity;
- realized scenario and WTI fields;
- a flag that the future authoritative position-aware mapping is still pending.

The Week 10 `week8_cash_cushion_musd` consequence stores this provenance under `metadata.week8_ebitda_bridge`.

## Runtime Behavior

For the Week 8 package's common partial-hold case:

```text
delta_wti = 7
effect    = 7 * 0.65 * 91.25
          = 415.1875 MUSD
```

For the fail case:

```text
delta_wti = -4
effect    = -4 * 0.65 * 91.25
          = -237.2500 MUSD
```

Both Week 10 cash cushion and KPI state now consume that same bridge output.

## Separation From Fixtures

`halden-kpi-consequence-package/data/reference_team_inputs.csv` remains a scoring-engine fixture. It is not used as a runtime oracle for Week 8 EBITDA.

The interim bridge is runtime-derived from `Week8EconomicEvaluation`; it does not read or backfill from reference-team rows.

## Seven-Week Behavior

The seven-week variant continues to exclude Windows 1, 2, and 3. Week 8 EBITDA bridge behavior is still available because Week 8 itself is part of the compressed sequence and Week 10 consumes its persisted evaluation.

Weeks 7 and 9 remain absent from the seven-week cash-cushion formula and contribute zero through the existing seven-week behavior.

## Remaining Limitation

The bridge is not the final production exposure model. The future authoritative addendum must still define the position-aware mapping that incorporates hedge coverage, production posture, and any other approved operating exposure.
