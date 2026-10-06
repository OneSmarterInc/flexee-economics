# Batch 34H Seven-Week Browser Rehearsal

Date: 2026-10-06

Starting checkpoint:

- `6b91f627800c897b696485cf062024963c93ca33`

Status:

- `LOCAL REHEARSAL COMPLETE WITH TARGETED UX FIXES`
- Not committed.
- Not pushed.

## Browser URL

The rehearsal used the local Laravel app:

```text
http://127.0.0.1:8000
```

## Accounts

Seeded demo accounts used:

- Faculty: `faculty@example.test`
- Team Alpha student: `pilot-alpha1@example.test`
- Team Beta student: `pilot-beta1@example.test`

## Sequence

The rehearsal followed the authoritative seven-week pilot sequence:

```text
Week 1 -> Week 4 -> Week 6 -> Week 8 -> Week 10 -> Week 12 -> Week 14
```

## Rehearsed Flow

### Week 1

- Team Alpha submitted the asset-register decision and memo through the student UI.
- Team Beta submitted the asset-register decision and memo through the student UI.
- Faculty selected the seven-week pilot section and executed Week 1.
- Execution completed and produced Week 1 evaluations/KPI/ranking records.

### Week 4

- Team Alpha submitted a marginal-cost transfer price of `18.70`.
- Team Beta submitted an intermediate/lazy-midpoint transfer price of `46.20`.
- Faculty executed Week 4.
- The Geneva capture behavior matched the corrected model:
    - marginal-cost transfer price: `0` Geneva capture;
    - intermediate `46.20`: positive Geneva capture;
    - market-based capture is not reused as an intermediate-price shortcut.

### Week 4 -> Week 6 Cohort State

The section-level discount-rate consequence resolved as `base` for both teams because the two-team distribution did not reach the 70% threshold for either marginal-cost or market-based classification.

Observed persisted consequence values:

- discount rate: `8.500%`;
- capital envelope: `$1,150M`;
- same section-level result for both participating teams.

The cohort percentages and peer transfer prices were not exposed to students during Week 4 decision-making.

### Week 6

- Team Alpha submitted a capital allocation through the student allocation workspace.
- Team Beta submitted a capital allocation through the student allocation workspace.
- Week 6 displayed the resulting classification/rate/envelope context.
- Faculty executed Week 6 successfully.

### Week 8

- Team Alpha submitted OPEC probabilities and realized scenario.
- Team Beta submitted OPEC probabilities and realized scenario.
- Faculty executed Week 8 successfully.

### Role Rotation

After Week 10 opened, the student journey dashboard correctly selected Week 10 as the current week even though earlier weeks remained visible/open for review.

The dashboard displayed the second-seat context:

- phase: second seat;
- current assigned seat: EVP;
- phase weeks: Weeks 10, 12, and 14.

### Week 10

- Team Alpha submitted the Week 10 operating posture and memo.
- Team Beta submitted the Week 10 operating posture and memo.
- Faculty executed Week 10 successfully.

Week 10 inherited state was derived from persisted prior history, not from student-entered inherited-state fields.

Observed persisted dependencies included:

- `week4_tp_delacroix_cover`;
- `week5_hedge_coverage`;
- `week6_cancellable_capex_musd`;
- `week8_cash_cushion_musd`;
- standing-state derived Straits Pacific flexibility.

No unresolved dependencies were present.

### Week 12

- Team Alpha submitted `helix_rotterdam,offshore_wind,euro_retail_divest`.
- Team Beta submitted `permian_expansion,biofuel_conversion`.
- Faculty executed Week 12 successfully.

The Week 12 runtime created portfolio evaluations from the package-backed transition engine.

### Week 14

- Faculty opened Week 14.
- Team Alpha submitted board-defense materials through the student Week 14 workspace.
- Faculty opened the assessment page for Team Alpha.
- Faculty completed the rubric assessment and published feedback.
- Student view showed published feedback.
- Student view did not show private faculty notes.
- Student access to the faculty assessment route returned `403 Forbidden`.

## Browser-Discovered Fixes

### Deprecated Inherited-State Fields After Upgrade

Issue:

- Upgraded local demo databases could retain deprecated student-entered inherited-state fields even after the current seed logic stopped creating them.
- Browser evidence: Week 8 exposed a stale `cash_cushion_musd` input before reseeding cleanup.

Fix:

- `DatabaseSeeder` now prunes deprecated inherited-state fields for Weeks 4, 8, and 10 during seed/reset.

Fields pruned:

- Week 4: `br_reported_margin_strong`;
- Week 8: `cash_cushion_musd`;
- Week 10: `br_reported_margin_strong`, `cancellable_capex_musd`, `crude_hedge_coverage`, `cash_cushion_musd`.

### Student Dashboard Current Week Selection

Issue:

- If a previous week remained `open` after execution, the student dashboard could continue selecting that resolved week as the current week.

Fix:

- Student dashboard current-week selection now prefers visible unresolved open/released weeks before falling back to resolved weeks.

### Week 14 Dashboard Status

Issue:

- The Week 14 workspace correctly used board-defense status, but the student dashboard summarized Week 14 as `Decision not started / Memo not started`.

Fix:

- The student dashboard now exposes and renders `board_defense_status` for Week 14 current-week and history summaries.

## Security Checks

Confirmed during browser rehearsal:

- Students can see their own team submissions and published feedback.
- Students cannot access faculty assessment routes.
- Students do not see faculty private notes.
- Week 14 does not expose a computational package or solution material.

## Mobile Check

The student dashboard and Week 14 workspace were inspected at a `390 x 844` viewport.

Observed:

- timeline and history render as stacked cards;
- Week 14 board-defense feedback remains visible;
- no wide-table dependency appeared in the inspected student journey.

## Automated Tests

Focused tests added/updated:

- demo seed prunes deprecated inherited-state fields from upgraded databases;
- dashboard current week prefers unresolved active week over resolved open week;
- Week 14 dashboard carries board-defense status rather than decision/memo status.

Focused verification:

```text
14 tests / 185 assertions
```

Broader focused verification:

```text
23 tests / 333 assertions
```

Full Laravel verification:

```text
492 tests / 4,591 assertions
```

Quality checks:

- Composer validation passed.
- Pint/lint passed.
- PHPStan passed with `0` errors.
- Frontend check, TypeScript check, and production build passed.

## Deferred

- This batch is verified locally but remains uncommitted and unpushed pending explicit commit approval.
- Beta Week 14 board-defense assessment was not performed during this rehearsal; Alpha covered the assessment and publication path.
- Week 6 allocation status still displays `Decisions: not_started` in some history cards because capital allocation uses a separate submission model. This remains a low-priority UX polish item.
