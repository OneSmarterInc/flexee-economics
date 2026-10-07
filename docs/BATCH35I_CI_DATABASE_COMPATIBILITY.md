# Batch 35I - CI Database Compatibility

## SQLite CI

The SQLite CI job failed because `composer setup` copies `.env.example`, whose local default database is PostgreSQL. The job now sets `DB_CONNECTION=sqlite` and `DB_DATABASE` to the workflow workspace SQLite file, then creates `database/database.sqlite` before running `composer setup`.

This keeps `.env.example` unchanged while ensuring the job named `SQLite CI` actually migrates and tests against SQLite.

## MySQL Foreign Keys

MySQL 8.4 rejected composite `SET NULL` foreign keys that included non-null `tenant_id`. The affected optional references now use single-column nullable foreign keys while required tenant-scoped cascade/restrict constraints remain composite.

This applies to optional user references such as creator, actor, submitter, updater, evaluator, and publisher fields, plus optional runtime/consequence references whose target id is nullable.

The creator relationship remains optional. Deleting the creator user nulls the creator reference while preserving the section simulation's non-null tenant ownership.

## Tenant Isolation

Tenant integrity for section simulations remains enforced by the `SectionSimulation` model:

- the section simulation tenant must match the section tenant;
- a creator, when present, must belong to the same tenant;
- identity fields cannot be retargeted after assignment.

Focused regression coverage verifies same-tenant creator creation, cross-tenant creator rejection, null creator support, and creator deletion nulling the reference without changing tenant ownership.

## MySQL Validation Status

Local validation cannot execute the MySQL `information_schema` test because no local MySQL server is available. The PR must remain unmerged until GitHub Actions runs the MySQL 8.4 job and confirms:

- `php artisan migrate:fresh --seed --no-interaction`;
- `php artisan test --no-interaction`;
- the MySQL-only schema identifier test executes rather than skips.
