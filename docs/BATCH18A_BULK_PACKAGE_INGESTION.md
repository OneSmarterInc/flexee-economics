# Batch 18D - Bulk Authoritative Package Audit and Registration

Batch 18D registers the authoritative multi-week package batch without implementing new economic engines.

## Architecture

```text
Authoritative package root
        |
        v
AuthoritativeContentPackageManifest
        |
        v
AuthoritativeContentPackageRegistrationService
        |
        v
SimulationContentPackageService
        |
        v
SimulationContentPackage + ContentArtifact
```

The generic manifest reads package provenance and constructs artifact records. The existing package service performs checksum validation and persistence, preserving the content architecture from Batches 11A and 11B.

## Registered Weeks

The approved bulk-registration set is:

```text
1, 2, 3, 5, 6, 7, 8, 9, 11, 13
```

Week 6 and Week 8 were already present and remain the accepted package roots. Their fixture hashes match the newly supplied batch copy.

## Exclusions

- Week 4 is unchanged as the stable golden vertical slice.
- Week 10 remains pending a 16A-standard package upgrade.
- Week 12 is quarantined by explicit task instruction pending design acceptance.
- Week 14 has no computational package by design.

## Security and Visibility

Artifact visibility is role-scoped:

- Students can resolve `student`, `shared`, and unscoped artifacts.
- Faculty/admin can resolve `student`, `faculty`, `solution`, `shared`, and unscoped artifacts.

The bulk registration tests verify that student users cannot see solution fixtures or faculty solution files.

## Validation

Added:

- `scripts/validate_authoritative_package_batch.py`
- `tests/Feature/Content/AuthoritativePackageBatchIngestionTest.php`

The package validator checks:

- required package artifacts;
- provenance hashes;
- CSV parseability;
- notebook JSON parseability;
- golden fixture tolerance for 16A-standard packages;
- golden ordering assertions.

Focused test coverage verifies:

- all ten approved packages register;
- invalid packages cannot activate;
- hashes are preserved;
- package versions are immutable;
- student/faculty artifact visibility;
- Week 12 cannot register for activation;
- Week 10 remains unready;
- Week 4 remains unchanged.

## Deferred

This batch intentionally does not implement:

- economic engines for newly ingested weeks;
- Week 8 OPEC engine;
- Week 6 to Week 8 cohort-response parameters;
- Week 10 package upgrade;
- Week 12 ingestion;
- KPI/ranking/consequence integration;
- LLM, what-if, or faculty UI changes.
