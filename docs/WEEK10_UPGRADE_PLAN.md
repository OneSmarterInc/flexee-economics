# Week 10 Upgrade Plan

Week 10 remains explicitly unready for ingestion.

The existing Week 10 files predate the current 16A package standard. They are useful source material, but they are not an authoritative runtime package yet.

## Current Status

- Week 10 package root is not registered by Batch 18D.
- `AuthoritativeContentPackageRegistrationService` rejects Week 10 registration.
- No Week 10 economics, runtime integration, KPI effects, ranking effects, or consequence mechanics are implemented in this batch.

## Required Upgrade Artifacts

Week 10 needs a normalized package with:

- `MANIFEST.md`
- canonical CSVs under `data/`
- student workbook
- student notebook
- faculty solution workbook
- faculty solution notebook
- `fixtures/week10_golden.json`
- `fixtures/provenance.json`
- `VALIDATION_16A.md`

## Required Validation

The upgraded package should pass the same checks as the registered batch packages:

- canonical CSVs complete and parseable;
- student workbook and notebook align to package data;
- faculty workbook and notebook recompute the golden outputs;
- student/faculty separation holds;
- SHA-256 provenance matches all artifacts;
- golden tolerance uses relative `1e-3` and absolute `1e-5`;
- ordering assertions are explicit and pass.

After that, Week 10 can receive its own ingestion batch and then a separate economics implementation batch.
