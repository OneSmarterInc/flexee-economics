# Cross-Section Ranking Readiness

Batch 34G reviewed cross-section ranking after the effective-dated seat assignment checkpoint. The result is:

```text
NOT REQUIRED FOR CURRENT PILOT
```

Cross-section ranking remains a real design goal, but implementation is deferred because the authoritative handoff does not yet define enough operational rules to calculate and publish it safely.

## Sources Reviewed

- `HANDOFF-README.md`
- `SAKSHI-BATCH-NOTE.md`
- `SAKSHI-AUDIT-NOTE-2026-10-04.md`
- `SAKSHI-AUDIT-FOLLOWUP-2026-10-05.md`
- `halden-development-instructions.md`
- `halden-energy-design-v2.md`
- `halden-energy-seven-week-variant.md`
- `halden-student-guide.md`
- `halden-faculty-adoption-guide.md`
- current `RankingCalculationService`
- `RankingSnapshot`
- `KpiSnapshot`
- `KpiDefinitionCatalog`
- faculty dashboard ranking summary
- student dashboard ranking exposure

`SAKSHI-KPI-CONSEQUENCE-NOTE.md` and `SAKSHI-DECISIONS-NOTE.md` were requested but are not present in the current supplied source directories. The implemented KPI/consequence package and the audit/follow-up notes cover the relevant package-backed KPI/ranking behavior currently available in the repository.

## Current Within-Section Ranking

The current production ranking path is unchanged:

```text
KPI snapshots
        ↓
section-level min/max normalization
        ↓
published KPI weights
        ↓
weighted composite score
        ↓
within-section rank
        ↓
immutable RankingSnapshot
```

The service explicitly rejects `RankingScope::CrossSection` with:

```text
Cross-section ranking scope is reserved for a future batch.
```

This preserves the single-section pilot behavior and avoids replacing section-normalized rankings with an undefined cross-section population.

## What The Design Defines

The handoff materials establish design intent:

- the leaderboard should eventually run within a section and across installations;
- cross-section comparison is intended only when environments are identical;
- the fourteen-week and seven-week arcs must not be ranked together without explicit permission;
- the seven-week variant compares only with other seven-week sections;
- leaderboard rank is context for Week 14, not an automatic grade.

These points justify keeping schema and service boundaries ready for a future cross-section scope.

## What Is Not Yet Authoritatively Defined

The supplied sources do not yet define the operational calculation contract for cross-section ranking:

- exact cross-section population query;
- whether population is same course, same simulation version, same tenant, same institution, or truly cross-installation;
- whether cross-tenant ranking is allowed and under what privacy rules;
- whether normalization uses section-normalized KPI scores, global min/max values, percentiles, or another method;
- publication timing and whether delayed sections are included;
- snapshot recalculation policy when later sections finish the same week;
- student visibility rules for other-section rank context;
- faculty/admin visibility rules for cross-section distributions;
- tie behavior for cross-section ranks;
- treatment of incomplete KPI bases in a cross-section population;
- whether cross-section snapshots are immutable point-in-time records or recalculated leaderboard views.

## Security Boundary

Until those rules exist, the safe behavior is:

- keep ranking snapshots tenant-scoped;
- keep students limited to their own team ranking visibility;
- keep faculty limited to assigned section simulations unless administrator privileges apply;
- do not expose other-section submissions, private outcomes, assessment data, or unpublished feedback;
- do not create cross-tenant leaderboard views.

## Variant Boundary

The seven-week variant source explicitly states that seven-week teams compare only to other seven-week teams. Cross-variant comparison remains unresolved and should remain disabled unless a future authoritative product decision permits it.

## Deferral Decision

Batch 34G does not implement cross-section ranking.

Reasons:

1. The audit follow-up describes cross-section ranking as future work and not urgent for a single-section pilot.
2. The current product can run the local pilot with within-section ranking.
3. The ranking formula for cross-section scope is not fully specified.
4. Security and publication rules are not fully specified.
5. Implementing a plausible formula would risk changing student-visible competition semantics without authority.

## Future Implementation Gate

Implement cross-section ranking only after an authoritative source defines:

- population scope;
- variant compatibility;
- tenant and institution boundary;
- normalization rule;
- weighting rule;
- tie behavior;
- publication timing;
- snapshot immutability/recalculation policy;
- student/faculty/admin visibility rules.

When those rules arrive, extend `RankingCalculationService` rather than creating a separate ranking engine.
