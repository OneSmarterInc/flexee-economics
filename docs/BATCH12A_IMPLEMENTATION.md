# Batch 12A Implementation

Batch 12A adds the Week 6 content package ingestion framework. It does not provide the authoritative Week 6 package, implement NPV/IRR formulas, add project cash flows, create artifact files, or change Week 6 economics.

## Architecture

```text
Week6ContentPackageManifest
        |
        v
Week6ContentPackageRegistrationService
        |
        v
SimulationContentPackage
        |
        v
SimulationContentActivation
        |
        v
SimulationContentResolver
```

The Week 6 package registration layer is a thin specialization over the generic content framework from Batches 11A and 11B.

## Expected Week 6 Structure

The framework declares the expected Week 6 package shape:

Student artifacts:

- workbook
- notebook
- instructions

Faculty artifacts:

- solution workbook
- solution notebook
- teaching notes

Expected-output artifacts:

- outputs
- validation fixtures

Default artifact references point to the future canonical package paths under:

```text
simulation-content/halden/week6/
```

Those files are not created by this batch.

## Missing Artifacts

When the authoritative Week 6 files are absent, registration creates an invalid package with explicit missing-artifact validation errors.

Invalid packages cannot be activated.

## Supplied Package Path

When all expected artifact references are supplied and valid, the package can be activated through the existing content activation service and resolved through the runtime resolver.

The runtime resolver remains responsible for:

- tenant and section authorization
- student/faculty visibility filtering
- hiding missing artifacts

## Deferred

The following remain out of scope:

- authoritative Week 6 package files
- Week 6 project cash flows
- NPV/IRR calculations
- capital-envelope feasibility calculations
- Week 6 what-if console
- Week 8 consequences
- Python generation or calibration
