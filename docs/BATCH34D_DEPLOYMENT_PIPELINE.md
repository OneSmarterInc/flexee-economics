# Batch 34D - Production Deployment Safety and Release Pipeline

## Scope

Batch 34D hardens deployment and release operations only. It does not alter simulation economics, KPI calculations, ranking calculations, consequence rules, cohort windows, the seven-week sequence, or Week 14 assessment behavior.

## Current Workflow Audit

Repository `main` contained `.github/workflows/tests.yml` only.

The historical deploy workflow exists on `origin/master`, not on `main`, and deploys on every push to `master`. It resets the server to `origin/master`, installs Composer dependencies, runs `php artisan migrate --force`, clears/caches Laravel, and does not build frontend assets or require a verified database backup.

Branch state at audit time:

```text
origin/master...main = 4 commits only on master, 87 commits only on main
```

`main` is therefore the release source. `master` must not continue as an independently advancing production branch.

## Implemented Release Strategy

The new production workflow is:

```text
push/PR to main
 -> tests workflow
 -> successful tests workflow_run
 -> GitHub production environment approval
 -> deploy tested SHA
```

The deploy workflow is `.github/workflows/deploy.yml`.

## Test Gate

Deployment is triggered by:

```yaml
workflow_run:
    workflows:
        - tests
    branches:
        - main
    types:
        - completed
```

The deploy job runs only when:

```yaml
github.event.workflow_run.conclusion == 'success'
```

There is no `push` trigger to `master`.

## Frontend Build

The deployment script runs:

```bash
npm ci
npm run build
```

`public/build` remains gitignored and is not committed.

## PHP Dependencies

The deployment script runs:

```bash
composer install --no-dev --prefer-dist --no-interaction --optimize-autoloader
```

This respects `composer.lock` and avoids shipping development dependencies.

## Migration Safety

Before migrations, the workflow:

```text
php artisan down
 -> PRODUCTION_BACKUP_COMMAND
 -> PRODUCTION_BACKUP_VERIFY_COMMAND
 -> php artisan migrate --force
 -> php artisan up
```

No backup provider is invented. Operators must configure the two backup secrets for the actual production database provider.

## Health Check

If `PRODUCTION_HEALTH_URL` exists, deployment validates it with `curl --fail`. Otherwise it falls back to Laravel CLI checks:

```bash
php artisan migrate:status --no-interaction
php artisan about --only=environment --no-interaction
```

## Staging

No staging workflow exists in this repository. Staging remains recommended future work, not a fake completed capability.

## Secrets

The workflow references secret names only. No secret values are stored in repository files.

Required secrets:

- `DEPLOY_HOST`
- `DEPLOY_USER`
- `DEPLOY_SSH_KEY`
- `DEPLOY_PORT`
- `PRODUCTION_BACKUP_COMMAND`
- `PRODUCTION_BACKUP_VERIFY_COMMAND`

Optional:

- `PRODUCTION_HEALTH_URL`

## Validation

Static release tests assert:

- tests workflow runs on `main`;
- deploy workflow is gated by successful `tests` workflow completion;
- deploy workflow has no `master` push trigger;
- frontend build commands are present;
- production migration is preceded by maintenance mode and backup verification;
- deployment docs do not contain plaintext secret values;
- release docs preserve the single-branch strategy.

## Deferred

- actual production deployment;
- branch merge from `main` to `master`;
- production backup provider selection;
- staging environment creation;
- provider-native monitoring/alerting.
