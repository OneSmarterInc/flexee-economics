# Production Deployment Runbook

This runbook describes the safe production deployment path for Flexee Economics after the Batch 34D deployment hardening pass.

## Current Strategy

Production releases use one authoritative code line:

```text
main
 -> tests workflow
 -> production environment approval
 -> deploy-production workflow
 -> production server
```

The previous `master` deployment path is not the release source. The remote `master` branch remains present for history only and must not receive independent production changes.

## Pre-Deployment

1. Confirm the intended release commit on `main`.
2. Confirm the `tests` workflow passed for that exact commit.
3. Confirm the GitHub `production` environment requires an authorized approval before the deploy job can continue.
4. Confirm these GitHub Actions secrets exist. Do not print their values.
    - `DEPLOY_HOST`
    - `DEPLOY_USER`
    - `DEPLOY_SSH_KEY`
    - `DEPLOY_PORT`
    - `PRODUCTION_BACKUP_COMMAND`
    - `PRODUCTION_BACKUP_VERIFY_COMMAND`
    - `PRODUCTION_HEALTH_URL` if an HTTP health endpoint is available
5. Confirm the production server checkout points at the repository and can fetch `origin/main`.
6. Confirm the operator-approved database backup command is valid for the current production database.
7. Confirm a rollback target is known: the previous deployed Git SHA or release tag.

## Deployment

The deployment workflow is `.github/workflows/deploy.yml`. It starts only after the `tests` workflow completes successfully on `main`.

The deploy job performs:

```text
fetch tested commit
 -> checkout tested SHA
 -> composer install --no-dev --prefer-dist --no-interaction --optimize-autoloader
 -> npm ci
 -> npm run build
 -> php artisan down
 -> PRODUCTION_BACKUP_COMMAND
 -> PRODUCTION_BACKUP_VERIFY_COMMAND
 -> php artisan migrate --force
 -> php artisan optimize:clear
 -> php artisan config:cache
 -> php artisan route:cache
 -> php artisan view:cache
 -> php artisan up
 -> health check
```

If either backup secret is missing, the workflow fails before migrations and leaves maintenance mode.

## Post-Deployment Checks

After deployment succeeds, verify:

- application homepage loads;
- login works;
- student dashboard loads;
- faculty dashboard loads;
- week control loads for an assigned faculty section;
- logs do not show migration, asset, route, or database errors.

## Health Check

If `PRODUCTION_HEALTH_URL` is configured, the workflow runs:

```bash
curl --fail --show-error --silent --location "$PRODUCTION_HEALTH_URL"
```

If no health URL is configured, it falls back to:

```bash
php artisan migrate:status --no-interaction
php artisan about --only=environment --no-interaction
```

This is a release health gate only. It is not a full monitoring platform.

## Do Not Do During Deployment

- Do not deploy from `master`.
- Do not deploy a SHA whose tests have not passed.
- Do not run `php artisan migrate --force` without a verified backup.
- Do not commit `public/build` assets.
- Do not print secrets in logs.
- Do not use migration rollback as a substitute for restoring production data.
