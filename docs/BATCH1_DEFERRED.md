# Batch 1 Deferred Work

The following items are intentionally deferred to later batches and are not Batch 1 bugs.

## Batch 2 And Later

- Simulation lifecycle tables and services.
- Simulation variants and week definitions.
- Versioned content packages.
- Generated artifact import/versioning.
- Audit event implementation beyond Laravel's default timestamps.
- Section simulation activation/close lifecycle.

## Week 4 And Economics

- Week 4 formulas.
- Week 4 datasets.
- Week 4 decision schema.
- Week 4 memo prompt and rubric.
- KPI calculations.
- Scoring engine.
- Ranking snapshots.
- Faculty publishing flow.
- Economic regression tests against the Week 4 reference package.

The Week 4 markdown specification is now available. Formula implementation, dataset import, and economic regression tests remain blocked until the worked Week 4 reference package is available.

## Python And LLM Boundaries

- Python artifact generation.
- Synthetic paths and response functions.
- Queued LLM integration.
- Faculty interpretive assistant.
- What-if console.
- Causal trace.
- Cohort analytics.

## Content

- Role-charter content loading.
- Student guide content.
- Faculty teaching guide content.
- Weeks 1-14 content.
- Seven-week compressed variant.

## Future Hardening

- Tenant-aware login strategy if duplicate email addresses across tenants must be supported.
- Super-admin/platform-admin role if the product requires cross-tenant operations.
- Production deployment configuration and CI workflow.
