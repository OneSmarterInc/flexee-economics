# Batch 11A Implementation

Batch 11A adds the reusable simulation content package framework. It does not migrate the already validated Week 4 package, generate Python artifacts, implement Week 6 economics, or change any runtime week engine.

## Architecture

```text
SimulationVersion / SimulationWeek
        |
        v
SimulationContentPackage
        |
        v
ContentArtifact
        |
        v
ContentPackageValidator
```

The package layer is platform-scoped. Packages belong to reusable simulation versions and weeks, not to tenant-owned section runs. Tenant and section simulations can later consume a selected simulation version without duplicating source content records.

## Simulation Content Packages

`SimulationContentPackage` stores:

- simulation version
- simulation week
- package type
- package version
- manifest
- deterministic manifest hash
- validation status
- validation summary
- validation timestamp

Package records are immutable once created.

## Content Artifacts

`ContentArtifact` stores package files and references:

- artifact key
- artifact type
- visibility
- path/reference
- checksum algorithm
- expected checksum
- actual checksum
- artifact version
- missing-artifact flag
- metadata

Artifact records are immutable once created.

## Validation Boundary

`ContentPackageValidator` currently validates:

- deterministic manifest hashing
- referenced artifact presence
- SHA-256 artifact hashing
- expected checksum comparison
- missing artifact reporting

Missing artifacts do not cause the framework to invent content or silently pass. They create an invalid package record with explicit validation errors.

## Idempotency and Versioning

The database enforces one package per:

```text
simulation_week_id + package_type + version
```

This allows future Week 6, Week 10, or Week 12 packages to add corrected versions without mutating historical versions.

## Week 4

Week 4 remains on the already validated package path:

```text
halden-week4-data-package/
scripts/validate_week4_package.py
tests/Fixtures/Week4/package_sources.json
```

Batch 11A intentionally does not migrate or wrap Week 4. A later batch can register Week 4 in this generalized package registry after proving parity with the existing validated path.

## Deferred

The following remain out of scope:

- migrating Week 4 into `SimulationContentPackage`
- Week 6 canonical package ingestion
- package activation/locking on section simulations
- student/faculty artifact delivery UI
- Python generation or calibration
- notebook execution validation
- workbook formula validation
- runtime engine consumption of package records
