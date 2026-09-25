# Week 8 Fixture Inventory

Batch 18A introduced the authoritative Week 8 reference package at:

`halden-week8-data-package/`

The package remains the source of truth for Week 8 content ingestion. These fixture files record the canonical package references, provenance paths, and hashes used by package validation tests without duplicating source artifacts.

Validation entry point:

`scripts/validate_week8_package.py`

Canonical oracle:

- `halden-week8-data-package/fixtures/week8_golden.json`
- `halden-week8-data-package/fixtures/provenance.json`
