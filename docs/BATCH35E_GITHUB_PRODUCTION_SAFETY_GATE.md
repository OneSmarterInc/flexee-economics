# Batch 35E GitHub Production Safety Gate

Starting checkpoint: `b454d65`.

Objective: inspect the GitHub release configuration before pushing the local release candidate, without pushing, deploying, deleting branches, or changing application logic.

## Summary

```text
PRODUCTION RELEASE STATUS:
BLOCKED
```

Reason:

- GitHub's actual default branch is still `master`.
- `main` is not protected.
- the listed production environment has no protection rules.
- `origin/master` still contains a push-to-master production deployment workflow.
- the remote MySQL CI has not run for the local release candidate.

The repository is not yet safe to use as a production trigger.

## Findings

| Item                   | Status       | Evidence                                                                                                                                                                                                                                                                                |
| ---------------------- | ------------ | --------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------- |
| Default branch         | BLOCKED      | GitHub repository metadata reports `default_branch = master`; required state is `main`.                                                                                                                                                                                                 |
| Main protection        | BLOCKED      | Public branch metadata reports `main.protected = false`. Branch protection/ruleset details require authenticated admin/API access; no protection is visible.                                                                                                                            |
| MySQL required check   | BLOCKED      | Workflow defines job name `MySQL 8.4 CI`, but `main` is unprotected, so the check is not currently enforced as required.                                                                                                                                                                |
| Production approval    | BLOCKED      | GitHub environments API lists one environment named `Production` with `protection_rules = []`; no required reviewer approval is configured/visible.                                                                                                                                     |
| Master deploy disabled | BLOCKED      | `origin/master:.github/workflows/deploy.yml` still deploys on push to `master`.                                                                                                                                                                                                         |
| Main deploy path       | VERIFIED     | Local `main` workflow uses `workflow_run` from `tests` on `main`, requires successful conclusion, uses `environment: production`, checks out the tested SHA, installs Composer deps, runs npm build, maintenance mode, backup gate, migration, cache rebuild, and health check.         |
| Required secrets       | NOT VERIFIED | Secrets API returned `401 Unauthorized`. Deployment references secret names only: `DEPLOY_HOST`, `DEPLOY_USER`, `DEPLOY_SSH_KEY`, `DEPLOY_PORT`, `PRODUCTION_BACKUP_COMMAND`, `PRODUCTION_BACKUP_VERIFY_COMMAND`, `PRODUCTION_HEALTH_URL`. Secret values were not requested or printed. |
| Backup gate            | VERIFIED     | Main deploy workflow refuses migrations unless `PRODUCTION_BACKUP_COMMAND` and `PRODUCTION_BACKUP_VERIFY_COMMAND` are set, then runs both before `php artisan migrate --force`.                                                                                                         |
| Migration gate         | VERIFIED     | Main deploy workflow runs `php artisan migrate --force` only after backup verification. MySQL test workflow separately runs clean `migrate:fresh --seed` before tests.                                                                                                                  |
| Health gate            | VERIFIED     | Main deploy workflow runs `PRODUCTION_HEALTH_URL` via curl when configured, otherwise `php artisan migrate:status` and `php artisan about`.                                                                                                                                             |

## GitHub Authentication

GitHub CLI result:

```text
gh: command not found
```

Read-only GitHub API calls were used where public endpoints allowed inspection.

## Repository Metadata

GitHub repository metadata:

```text
repository: OneSmarterInc/flexee-economics
visibility: public
default_branch: master
```

This conflicts with the release policy requiring `main` as the sole release branch.

## Branch Protection

Public branch metadata:

```text
main.protected = false
master.protected = false
```

Branch protection API for `main` returned:

```text
401 Unauthorized
```

So detailed rulesets and required status checks are not inspectable from this environment. Because the public branch metadata reports `protected = false`, the safe release status is blocked until GitHub settings are updated.

## Required MySQL Check

The workflow job name is:

```text
MySQL 8.4 CI
```

The intended required status check should require the MySQL job from the `tests` workflow. In the GitHub UI this may appear as either the job name or the workflow/job combination, depending on GitHub's checks display. Do not guess in branch protection; select the exact emitted check after the first remote run.

## Production Environment

GitHub environments API lists:

```text
name: Production
protection_rules: []
deployment_branch_policy: null
can_admins_bypass: true
```

The main deploy workflow uses:

```text
environment: production
```

GitHub environment names are effectively matched by name in the workflow UI, but this inspection found no required reviewers/protection rules on the listed production environment. Production approval is therefore blocked.

## Master Deployment Workflow

Remote `origin/master` still contains:

```text
on:
  push:
    branches:
      - master
```

and deploys production through SSH on every push to `master`.

This is the undesired path:

```text
master
 -> push
 -> production
```

Production release remains:

```text
BLOCKED UNTIL MASTER DEPLOY PATH IS DISABLED
```

## Main Deployment Workflow

Local `main` contains the desired deployment shape:

```text
tests on main
 -> successful tests
 -> production environment
 -> deploy tested SHA
```

Confirmed in `.github/workflows/deploy.yml`:

- `workflow_run`;
- workflow name `tests`;
- branch `main`;
- completed workflow type;
- successful conclusion guard;
- `environment: production`;
- tested SHA checkout using `github.event.workflow_run.head_sha`;
- Composer production install before build;
- `npm ci`;
- `npm run build`;
- maintenance mode;
- backup command gate;
- backup verification gate;
- migration;
- cache rebuild;
- health check.

This path is structurally good, but it is not safe until GitHub default branch/protection/environment settings are fixed and `master` is disabled as a production path.

## Main/Master Divergence

Remote divergence after read-only fetch:

```text
origin/master..origin/main
```

`origin/main` contains the full simulation/platform work through:

```text
09a01cb test: validate post-audit seven-week browser rehearsal
```

Local `main` additionally contains:

```text
7b7c44a fix: make migrations mysql-safe and enforce main release path
b454d65 fix: align Week 8 interim EBITDA bridge
```

Remote `origin/main..origin/master` contains:

```text
0ba33af Fix simulation lifecycle and cohort feedback migrations
7054de9 Install Composer before Vite build
4155a87 Fix production Vite deployment
df556e7 Fix automatic deployment workflow
c4aed11 Fix production deployment workflow
7fe6bd5 Add automatic production deployment
1a26ccc first commit
```

Reconciliation:

- Vite build handling is represented in the current `main` deploy workflow through `npm ci` and `npm run build`.
- Composer-before-Vite ordering is represented in the current `main` deploy workflow.
- Migration-name fix is superseded by Batch 35B's full migration identifier normalization.
- The unsafe push-to-master deployment workflow remains active on `origin/master`.

## Secret Names

Deployment references these secret names:

```text
DEPLOY_HOST
DEPLOY_USER
DEPLOY_SSH_KEY
DEPLOY_PORT
PRODUCTION_BACKUP_COMMAND
PRODUCTION_BACKUP_VERIFY_COMMAND
PRODUCTION_HEALTH_URL
```

Secret values were not requested, printed, or exposed.

Secret existence:

```text
NOT VERIFIED
```

Reason: GitHub secrets API requires authentication and returned `401 Unauthorized`.

## Required Manual GitHub Actions

Before pushing or deploying:

1. Set repository default branch to `main`.
2. Protect `main`.
3. Require pull requests before merge to `main`.
4. Require the `tests` workflow status checks, including `MySQL 8.4 CI`, once the check name is emitted by a remote run.
5. Configure the production environment with required reviewer approval.
6. Disable or remove the production deployment workflow on `master`.
7. Confirm no other automation deploys from `master`.
8. Confirm deployment secret names exist without exposing values.

Do not delete `master` until the default branch, protection, required checks, environment approval, and disabled master deploy path are verified.

## Remote MySQL Gate

The remote MySQL gate remains:

```text
MYSQL CI = AWAITING SAFE REMOTE TRIGGER
```

Do not push just to trigger MySQL while the production path can still deploy unsafely from `master` and production environment approval is not configured.

## Push / Deploy Status

```text
NOT PUSHED
NOT DEPLOYED
```
