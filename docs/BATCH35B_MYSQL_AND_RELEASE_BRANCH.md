# Batch 35B MySQL and Release Branch

Starting checkpoint: `09a01cb`.

Authoritative audit: `SAKSHI-AUDIT-FOLLOWUP-2026-10-06.md`.

## Scope

This batch addresses the two production blockers identified by the 6 October audit:

- MySQL compatibility for first production launch.
- A single `main` release path instead of competing `main` and `master` production paths.

No economic, KPI, ranking, consequence, seven-week, or Week 14 assessment behavior was changed.

## Production Database

The audit confirms production uses MySQL and requires a MySQL 8 release gate.

The repository now uses a GitHub Actions MySQL job with:

- image: `mysql:8.4`;
- strict Laravel MySQL connection;
- `utf8mb4`;
- `utf8mb4_0900_ai_ci`;
- a clean `php artisan migrate:fresh --seed --no-interaction`;
- a full `php artisan test --no-interaction`.

The exact production server minor/patch version is not locally verifiable from this repository. GitHub or infrastructure settings must confirm the production MySQL minor before launch if it differs from the CI gate.

## Migration Identifier Audit

A static migration identifier audit was added to `ProductionDeploymentPipelineTest`.

The stricter audit found `139` implicit database identifiers over MySQL's 64-character limit.

Identifier types included:

- foreign keys;
- unique constraints;
- indexes.

All over-limit implicit names were replaced with deterministic, concise, explicit names in the existing migrations. This is a pre-launch exception because the audit confirms production has no real data and may be rebuilt from scratch before launch.

After production launch, existing migrations must not be edited again. Future schema changes must use new migrations.

## Master Branch Review

Local refs show:

- `origin/HEAD -> origin/main`;
- `origin/main` at `09a01cb`;
- `origin/master` still present.

`origin/master` has unique operational commits including:

- Vite build fix;
- Composer-before-Vite ordering fix;
- migration-name fix.

The current `main` deployment workflow already installs Composer dependencies before `npm ci` / `npm run build`, verifies backup before migration, and deploys only after the `tests` workflow succeeds on `main`.

The useful migration-name idea from `master` was superseded by the full migration identifier normalization in this batch. The full `master` history was not merged.

## Deployment Workflow

Current intended path:

```text
main
 -> tests workflow
 -> production environment approval
 -> deploy-production
 -> production
```

The `main` deployment workflow:

- is triggered by successful `tests` workflow completion on `main`;
- uses the protected `production` environment;
- checks out the tested SHA;
- installs Composer dependencies before frontend build;
- runs `npm ci` and `npm run build`;
- enters maintenance mode;
- refuses migrations unless backup and backup verification commands are configured;
- runs backup before migration;
- runs migration before cache rebuild and health check.

`origin/master` still contains a push-to-master deployment workflow. This batch does not delete `master` or mutate GitHub branch settings. Before production launch, the repository owner must ensure `master` cannot independently deploy, either by deleting the branch after reconciliation or disabling/removing its workflow through an approved GitHub-side action.

## GitHub Settings

Locally verified:

- `origin/HEAD` points to `origin/main`.

Not locally verifiable:

- GitHub default branch setting in repository settings;
- branch protection rules;
- required checks configuration;
- production environment approval policy.

Required GitHub-side release settings:

- default branch: `main`;
- required PR for `main`;
- required MySQL test job for `main`;
- protected `production` environment approval;
- no active production deployment path from `master`.

## Verification Notes

Local MySQL execution could not be run because this workstation has `pdo_mysql` but no local `mysql`, `mysqld`, or `docker` command available to host a MySQL service.

The MySQL clean migration/test execution is therefore configured as the GitHub Actions release gate and remains to be proven by the remote `MySQL 8.4 CI` job.

Local verification performed:

- focused release workflow and migration identifier tests;
- full SQLite-backed Laravel suite;
- Composer validation;
- Pint/lint;
- PHPStan;
- frontend check;
- frontend type check;
- frontend build;
- `git diff --check`.

## Remaining Actions

- Run and require the GitHub Actions `MySQL 8.4 CI` job.
- Confirm production MySQL minor/patch version.
- Confirm GitHub default branch is `main`.
- Configure branch protection to require PRs and MySQL tests.
- Disable or remove any production deployment path from `master`.
- Do not deploy until those GitHub-side controls are verified.
