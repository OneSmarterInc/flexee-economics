# Stage 1 Data Model

## Source constraints

This was the initial proposed schema before the Laravel implementation and before the authoritative development instructions were supplied. Future economic implementation still must wait for the worked Week 4 package to be reviewed.

## Tenancy model

Every tenant-owned table must have a direct `tenant_id` or an enforced foreign-key path to `tenants.id`.

Recommended root hierarchy:

`tenants -> institutions -> courses -> sections -> teams -> team_members -> users`

The development instructions confirm section-level isolation and installation-level comparability; keep this hierarchy aligned with the implemented migrations.

## Tables

### `tenants`

Important columns:

- `id`
- `name`
- `slug`
- `status`
- timestamps

Constraints and indexes:

- unique `slug`

### `institutions`

Important columns:

- `id`
- `tenant_id`
- `name`
- `slug`
- timestamps

Constraints and indexes:

- foreign key `tenant_id -> tenants.id`
- unique `(tenant_id, slug)`
- index `tenant_id`

### `users`

Important columns:

- `id`
- `tenant_id`
- `name`
- `email`
- `password`
- `global_role`
- timestamps

Constraints and indexes:

- foreign key `tenant_id -> tenants.id`
- unique `(tenant_id, email)`
- index `(tenant_id, global_role)`

### `courses`

Important columns:

- `id`
- `tenant_id`
- `institution_id`
- `code`
- `name`
- `term`
- timestamps

Constraints and indexes:

- foreign keys to `tenants` and `institutions`
- unique `(tenant_id, institution_id, code, term)`

### `sections`

Important columns:

- `id`
- `tenant_id`
- `course_id`
- `name`
- `starts_at`
- `ends_at`
- `status`
- timestamps

Constraints and indexes:

- foreign key `course_id -> courses.id`
- unique `(tenant_id, course_id, name)`
- index `(tenant_id, course_id, status)`

### `section_faculty`

Important columns:

- `id`
- `tenant_id`
- `section_id`
- `user_id`
- `role`
- timestamps

Constraints and indexes:

- unique `(tenant_id, section_id, user_id)`
- foreign keys to `sections` and `users`

### `enrollments`

Important columns:

- `id`
- `tenant_id`
- `section_id`
- `user_id`
- `status`
- timestamps

Constraints and indexes:

- unique `(tenant_id, section_id, user_id)`
- index `(tenant_id, user_id, status)`

### `teams`

Important columns:

- `id`
- `tenant_id`
- `section_id`
- `name`
- `slug`
- timestamps

Constraints and indexes:

- unique `(tenant_id, section_id, slug)`
- index `(tenant_id, section_id)`

### `seats`

Important columns:

- `id`
- `simulation_id`
- `code`
- `name`
- `sort_order`
- `content_ref`
- timestamps

Constraints and indexes:

- unique `(simulation_id, code)`

For Halden, expected seats include EVP and four segment heads, but exact seat names/content must come from `halden-energy-role-charters.md`.

### `team_members`

Important columns:

- `id`
- `tenant_id`
- `team_id`
- `user_id`
- `seat_id`
- timestamps

Constraints and indexes:

- unique `(tenant_id, team_id, user_id)`
- unique `(tenant_id, team_id, seat_id)`
- index `(tenant_id, user_id)`

### `simulations`

Important columns:

- `id`
- `slug`
- `name`
- `status`
- timestamps

Constraints and indexes:

- unique `slug`

### `simulation_variants`

Important columns:

- `id`
- `simulation_id`
- `slug`
- `name`
- `duration_weeks`
- timestamps

Constraints and indexes:

- unique `(simulation_id, slug)`

### `simulation_weeks`

Important columns:

- `id`
- `simulation_variant_id`
- `week_number`
- `slug`
- `title`
- `pattern`
- timestamps

Constraints and indexes:

- unique `(simulation_variant_id, week_number)`
- unique `(simulation_variant_id, slug)`

### `week_content_versions`

Important columns:

- `id`
- `simulation_week_id`
- `version`
- `constants_hash`
- `manifest_path`
- `decision_schema`
- `memo_schema`
- `scoring_engine`
- `status`
- timestamps

Constraints and indexes:

- unique `(simulation_week_id, version)`
- index `(simulation_week_id, status)`

### `generated_artifacts`

Important columns:

- `id`
- `week_content_version_id`
- `kind`
- `path`
- `hash`
- `metadata`
- timestamps

Constraints and indexes:

- unique `(week_content_version_id, kind, hash)`

### `section_simulations`

Important columns:

- `id`
- `tenant_id`
- `section_id`
- `simulation_variant_id`
- `status`
- timestamps

Constraints and indexes:

- unique `(tenant_id, section_id, simulation_variant_id)`

### `section_simulation_weeks`

Important columns:

- `id`
- `tenant_id`
- `section_simulation_id`
- `simulation_week_id`
- `week_content_version_id`
- `status`
- `opens_at`
- `closes_at`
- `published_at`
- timestamps

Constraints and indexes:

- unique `(tenant_id, section_simulation_id, simulation_week_id)`
- index `(tenant_id, status, closes_at)`

### `team_simulations`

Important columns:

- `id`
- `tenant_id`
- `section_simulation_id`
- `team_id`
- `status`
- timestamps

Constraints and indexes:

- unique `(tenant_id, section_simulation_id, team_id)`

### `decision_submissions`

Important columns:

- `id`
- `tenant_id`
- `section_simulation_week_id`
- `team_id`
- `submitted_by_user_id`
- `payload`
- `version`
- `status`
- `submitted_at`
- timestamps

Constraints and indexes:

- unique active/final submission per `(tenant_id, section_simulation_week_id, team_id)` according to submission rules
- index `(tenant_id, team_id, submitted_at)`

### `memo_submissions`

Important columns:

- `id`
- `tenant_id`
- `section_simulation_week_id`
- `team_id`
- `submitted_by_user_id`
- `body`
- `attachments_metadata`
- `version`
- `status`
- `submitted_at`
- timestamps

Constraints and indexes:

- unique active/final memo per `(tenant_id, section_simulation_week_id, team_id)` according to submission rules

### `scoring_runs`

Important columns:

- `id`
- `tenant_id`
- `section_simulation_week_id`
- `team_id`
- `decision_submission_id`
- `memo_submission_id`
- `week_content_version_id`
- `engine_version`
- `constants_hash`
- `artifact_hashes`
- `status`
- `started_at`
- `completed_at`
- timestamps

Constraints and indexes:

- index `(tenant_id, section_simulation_week_id, status)`
- unique final run policy to prevent duplicate visible scores unless recalculation/versioning is explicit

### `kpi_results`

Important columns:

- `id`
- `tenant_id`
- `scoring_run_id`
- `team_id`
- `key`
- `value`
- `unit`
- `precision`
- `metadata`
- timestamps

Constraints and indexes:

- unique `(tenant_id, scoring_run_id, key)`

### `scores`

Important columns:

- `id`
- `tenant_id`
- `scoring_run_id`
- `team_id`
- `score_key`
- `raw_value`
- `normalized_value`
- `weight`
- `weighted_value`
- `metadata`
- timestamps

Constraints and indexes:

- unique `(tenant_id, scoring_run_id, score_key)`

### `ranking_snapshots`

Important columns:

- `id`
- `tenant_id`
- `section_simulation_week_id`
- `scope`
- `status`
- `snapshot_payload`
- `generated_at`
- timestamps

Constraints and indexes:

- index `(tenant_id, section_simulation_week_id, scope, generated_at)`

### `publications`

Important columns:

- `id`
- `tenant_id`
- `section_simulation_week_id`
- `ranking_snapshot_id`
- `published_by_user_id`
- `visibility`
- `published_at`
- `revoked_at`
- timestamps

Constraints and indexes:

- index `(tenant_id, section_simulation_week_id, published_at)`

### `audit_events`

Important columns:

- `id`
- `tenant_id`
- `actor_user_id`
- `event_type`
- `auditable_type`
- `auditable_id`
- `metadata`
- `occurred_at`

Constraints and indexes:

- index `(tenant_id, event_type, occurred_at)`
- index `(auditable_type, auditable_id)`

## Data leakage and impossible-state protections

- Use composite uniqueness including `tenant_id` for tenant-owned business keys.
- Validate that every submitted `team_id` belongs to the same section simulation week.
- Validate that the submitting user is a member of the team or faculty/admin with explicit permission.
- Prevent submissions after close unless faculty/admin override is recorded in `audit_events`.
- Prevent publication unless scoring and ranking snapshots are complete.
- Prevent students from reading unpublished `ranking_snapshots`.
- Store content, constants, engine, and artifact versions with every scoring run.
- Do not mutate finalized submissions; create new versions or explicit overrides.
