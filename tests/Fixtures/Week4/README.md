# Week 4 Reference Fixtures

Updated: 2026-09-22.

The canonical Week 4 golden reference now lives in:

`halden-week4-data-package/`

This fixture directory intentionally avoids duplicating package inputs and expected outputs. Tests should read from the canonical package and use:

- `halden-week4-data-package/expected/worked_example.json`
- `halden-week4-data-package/expected/week4_reference.json`
- `scripts/validate_week4_package.py`

`package_sources.json` records the normalized package hashes for drift detection.
