# Demo Runbook

This runbook describes the repeatable Week 4 demo baseline.

## Reset

Rebuild the demo tenant and Week 4 vertical slice:

```bash
php artisan halden:demo-reset
```

For a full local database rebuild:

```bash
php artisan halden:demo-reset --fresh
```

## Health Check

Verify the baseline:

```bash
php artisan halden:demo-health
```

The check confirms:

- demo tenant, faculty, and student exist;
- Section A has the Halden Energy simulation assigned;
- Week 4 runtime is open;
- the Week 4 content package is active and valid;
- Week 4 decision and memo definitions exist;
- execution, KPI, and interpretation services resolve.

## Demo Accounts

All seeded demo accounts use password:

```text
password
```

Faculty:

```text
faculty@example.test
```

Student:

```text
student11@example.test
```

## Student Flow

1. Sign in as `student11@example.test`.
2. Open the dashboard.
3. Open Week 4: Transfer pricing.
4. Confirm student/shared Week 4 materials are visible.
5. Submit a transfer price, for example `46.20`.
6. Submit the Week 4 memo.
7. Confirm the workspace shows submitted status.

Students must not see faculty solution artifacts, causal trace, what-if, peer decisions, or cohort state.

## Faculty Flow

1. Sign in as `faculty@example.test`.
2. Open `/faculty/week-control`.
3. Select Section A Demo Halden Energy.
4. Select Week 4: Transfer pricing.
5. Execute the week.
6. Confirm the execution record shows content validation, submission counting, decision resolution, KPI calculation, ranking calculation, and deferred future visibility/cohort steps.

## Debrief Tools

Causal trace:

```text
/faculty/causal-trace
```

Use it to show Week 4 decision, economic resolution, KPI/ranking history, and Week 4 consequence links.

What-if:

```text
/faculty/what-if
```

Use it to compare a counterfactual transfer price without mutating historical simulation state.

Interpretive assistant:

The assistant foundation is service-backed and queue-based. It stores interpretation requests and context snapshots; it does not make grading decisions.

## Reference Test

The platform baseline is covered by:

```text
tests/Feature/Week4/Week4RuntimeActivationSmokeTest.php
```

That test is the golden smoke test for future week integrations.
