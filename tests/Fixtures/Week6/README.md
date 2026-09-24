# Week 6 Fixture Inventory

Batch 16A introduced the authoritative Week 6 reference package at:

`halden-week6-data-package/`

The package remains the source of truth for Week 6 content ingestion. These fixture files record the
package hash inventory and point tests at the expected outputs and provenance files without
duplicating economic formulas in the test suite.

Validation entry point:

`scripts/validate_week6_package.py`

Expected outputs and provenance:

- `halden-week6-data-package/fixtures/week6_golden.json`
- `halden-week6-data-package/fixtures/provenance.json`
