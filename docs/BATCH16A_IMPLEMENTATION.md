# Batch 16A Implementation

Batch 16A normalized and validated the authoritative Week 6 reference package without implementing
the Week 6 NPV/IRR runtime engine.

## Package Location

The package is stored at:

`halden-week6-data-package/`

It contains the supplied manifest, canonical CSVs, student workbook, student notebook, faculty
solution workbook, provenance hashes, and golden outputs.

## Validation Boundary

`scripts/validate_week6_package.py` validates:

- required artifact inventory;
- SHA-256 hashes from `fixtures/provenance.json`;
- workbook sheet structure, lack of external links, faculty formulas, and cached faculty outputs;
- student notebook execution;
- golden fixture math from the canonical CSVs.

This validation proves the package and fixtures are internally consistent. It does not move Week 6
economics into Laravel runtime code.

## Content Registration

`Week6ContentPackageManifest` now points at the real package artifacts:

- student workbook and notebook;
- shared manifest and canonical CSVs;
- faculty solution workbook;
- solution-only golden outputs and provenance.

Valid Week 6 packages can activate through the existing `SimulationContentResolver` path. Invalid
or missing packages remain rejected by the generic content package validator.

## Deferred

Still deferred to Batch 16B:

- Laravel NPV calculation;
- Laravel IRR calculation;
- capital-envelope feasibility;
- Week 6 resolution integration;
- KPI/ranking effects;
- Week 6 what-if support.
