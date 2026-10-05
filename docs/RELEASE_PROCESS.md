# Release Process

Flexee Economics uses a single-release-line process.

```text
feature branch or local work
 -> pull request / review
 -> tests workflow on main
 -> release approval
 -> optional release tag
 -> production deployment
 -> health check
```

## Branches

- `main` is the release source.
- `master` is not an independent production line.
- If infrastructure still expects `master`, update the infrastructure to deploy from `main` or merge `main` into `master` only through a controlled pull request after tests pass.

Batch 34D does not merge or delete branches.

## Required Gates

Before a production release:

1. All tests must pass for the exact commit.
2. Composer validation, lint, and PHPStan must pass.
3. Frontend check, typecheck, and build must pass.
4. A release approver must approve the GitHub `production` environment deployment.
5. A production database backup must be taken and verified before migrations run.

## Versioning

Use annotated tags for production releases when the release is approved:

```bash
git tag -a vYYYY.MM.DD-N -m "Release vYYYY.MM.DD-N"
git push origin vYYYY.MM.DD-N
```

Record in the release notes:

- release tag;
- commit SHA;
- test workflow run;
- deployment workflow run;
- known limitations;
- rollback SHA or previous tag.

## Rollback Reference

The rollback reference is the previously deployed SHA or tag. Keep it in the release notes and deployment ticket before starting the release.

Application rollback means redeploying the previous known-good SHA. Database rollback means restoring the verified backup. These are separate actions.

## Staging

No staging deployment workflow is present in the current repository. A future staging target should follow the same rule:

```text
main
 -> tests
 -> staging deploy
 -> production approval
 -> production deploy
```

Do not use staging as a bypass around the production test gate.
