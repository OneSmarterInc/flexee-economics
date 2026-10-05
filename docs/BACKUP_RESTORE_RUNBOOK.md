# Backup and Restore Runbook

This document separates what is implemented today from what operators must do and what should be automated later.

## Currently Implemented

The production deployment workflow requires two configured commands before migrations can run:

- `PRODUCTION_BACKUP_COMMAND`
- `PRODUCTION_BACKUP_VERIFY_COMMAND`

The workflow fails before `php artisan migrate --force` if either command is missing.

The repository does not define a database provider-specific backup tool. That is intentional: no backup provider is declared in the current infrastructure files.

## Operator Procedure

Before approving production deployment:

1. Confirm the backup command targets the production database.
2. Confirm the backup destination has enough space and retention.
3. Confirm the backup verification command checks that the backup artifact exists and is restorable enough for the release risk.
4. Confirm the restore operator knows the latest backup path or identifier.

During deployment, the workflow runs:

```bash
php artisan down
bash -lc "$PRODUCTION_BACKUP_COMMAND"
bash -lc "$PRODUCTION_BACKUP_VERIFY_COMMAND"
php artisan migrate --force
php artisan up
```

## Restore Procedure

If deployment fails before migrations, redeploy the previous application SHA or rerun the fixed deployment after resolving the failure.

If deployment fails after migrations or data corruption is suspected:

1. Keep the application in maintenance mode:
    ```bash
    php artisan down
    ```
2. Stop write traffic at the application/load balancer layer if available.
3. Restore the verified database backup using the production database provider's approved restore process.
4. Checkout the previous known-good SHA:
    ```bash
    git fetch origin main
    git checkout --force <previous-good-sha>
    composer install --no-dev --prefer-dist --no-interaction --optimize-autoloader
    npm ci
    npm run build
    php artisan optimize:clear
    php artisan config:cache
    php artisan route:cache
    php artisan view:cache
    php artisan up
    ```
5. Run post-restore checks:
    ```bash
    php artisan migrate:status --no-interaction
    php artisan about --only=environment --no-interaction
    ```
6. Verify login, student dashboard, faculty dashboard, and week control through the browser.

Migration rollback is not a substitute for restoring production data.

## Recommended Future Automation

- Provider-native scheduled backups with retention policy.
- Restore rehearsal in a staging environment.
- Automated backup artifact checksum or provider restore-point validation.
- Alerting for failed backup or failed restore verification.
