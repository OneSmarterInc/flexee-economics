# Halden Source Inventory

Inventory date: 2026-09-22.

Searched locations:

- `C:\Users\sakas\Documents\ChatGPT\Flexee-economics`
- `C:\Users\sakas\Documents\Flexee-economics`

Search exclusions were limited to dependency/build/cache folders under the application repository (`node_modules`, `vendor`, `.git`, `public/build`, and development-cache storage). No unrelated personal directories were searched.

Current repository check:

- `git status --short`: clean before this documentation work.
- `HEAD`: `3c72e1f feat: add simulation submission framework`.

## Required for Batch 4 economics

| Source                               | Path                                                                   | Present | File type          | Purpose                                                                                             | Authoritative status                                               | Required before Week 4 implementation                     | Notes                                                                                                                                           |
| ------------------------------------ | ---------------------------------------------------------------------- | ------- | ------------------ | --------------------------------------------------------------------------------------------------- | ------------------------------------------------------------------ | --------------------------------------------------------- | ----------------------------------------------------------------------------------------------------------------------------------------------- |
| `HANDOFF-README.md`                  | `C:\Users\sakas\Documents\Flexee-economics\HANDOFF-README.md`          | Yes     | Markdown           | Package front door and source-order guidance                                                        | Authoritative for process/source precedence, not economic formulas | Yes, for source-order rules                               | Names the missing development instructions, constants ledger, Week 4 package, and Week 10 package as first-read sources.                        |
| `halden-development-instructions.md` | Not found                                                              | No      | Markdown expected  | Laravel architecture, Python/Laravel boundary, queued LLM pattern, tenancy, build sequence, gotchas | Missing                                                            | Yes                                                       | Handoff says this is the entry point. Its absence blocks complete architecture validation.                                                      |
| `constants-ledger.md`                | Not found under that exact name                                        | No      | Markdown expected  | Single source of economic truth                                                                     | Missing as named                                                   | Yes                                                       | Handoff says the file may live in project memory, not the outputs folder. A corresponding exported file exists as `halden-constants-ledger.md`. |
| `halden-constants-ledger.md`         | `C:\Users\sakas\Documents\Flexee-economics\halden-constants-ledger.md` | Yes     | Markdown           | Economic constants and load-bearing relationships                                                   | Authoritative constants ledger for this workspace                  | Yes                                                       | Only constants ledger found. The title states it is the single source of economic truth.                                                        |
| `halden-week4-data-package/`         | Not found                                                              | No      | Directory expected | Worked Week 4 reference build: manifest, CSV/Excel/notebooks/Python/expected outputs                | Missing                                                            | Yes                                                       | Mandatory test oracle and calculation-flow source; absence blocks Week 4 implementation.                                                        |
| `halden-week4-data-package-spec.md`  | Not found                                                              | No      | Markdown expected  | Week 4 decision fields, datasets, memo, faculty layer, parameters                                   | Missing                                                            | Yes                                                       | Cannot map decision definitions, memo, outputs, scoring, or faculty visibility without it.                                                      |
| `halden-week10-data-package/`        | Not found                                                              | No      | Directory expected | Worked convergence-week reference build                                                             | Missing                                                            | Yes for architecture-readiness, no for Week 4 formulas    | Handoff says it prevents designing Week 4 architecture too narrowly.                                                                            |
| `halden-week10-data-package-spec.md` | Not found                                                              | No      | Markdown expected  | Week 10 convergence spec                                                                            | Missing                                                            | Useful before Batch 4 architecture finalization           | Needed to compare single-decision and convergence-week patterns.                                                                                |
| `halden-calibration-review.md`       | Not found                                                              | No      | Markdown expected  | Calibration status and load-bearing structural relationships                                        | Missing                                                            | Yes before launch and before resolving provisional values | Handoff calls it important and says values are provisional design-draft calibration.                                                            |

## Useful reference

| Source                             | Path                                                                                                                                                          | Present | File type         | Purpose                                        | Authoritative status           | Required before Week 4 implementation                        | Notes                                                                                                   |
| ---------------------------------- | ------------------------------------------------------------------------------------------------------------------------------------------------------------- | ------- | ----------------- | ---------------------------------------------- | ------------------------------ | ------------------------------------------------------------ | ------------------------------------------------------------------------------------------------------- |
| `halden-energy-role-charters.md`   | Not found                                                                                                                                                     | No      | Markdown expected | Seat briefs for student-facing roles           | Missing                        | Yes for complete student experience; not enough for formulas | Needed to decide seat-specific visibility/editing rules.                                                |
| `halden-faculty-teaching-guide.md` | Not found                                                                                                                                                     | No      | Markdown expected | Instructor guide and faculty tool requirements | Missing                        | Yes for faculty-layer completeness                           | Handoff says Part One describes cohort view, causal trace, what-if console, and interpretive assistant. |
| `halden-student-guide.md`          | Not found                                                                                                                                                     | No      | Markdown expected | Student onboarding/help                        | Missing                        | Not required for deterministic formulas                      | Needed for polished in-product help.                                                                    |
| Existing app architecture docs     | `docs\STAGE1_ARCHITECTURE.md`, `docs\STAGE1_DATA_MODEL.md`, `docs\BATCH1_IMPLEMENTATION.md`, `docs\BATCH2_IMPLEMENTATION.md`, `docs\BATCH3_IMPLEMENTATION.md` | Yes     | Markdown          | Current platform state                         | Internal implementation record | Yes for mapping                                              | These document the Batch 1-3 architecture available for Batch 4 mapping.                                |
| Existing economic dependency doc   | `docs\ECONOMIC_DEPENDENCIES.md`                                                                                                                               | Yes     | Markdown          | Prior constants-ledger inventory               | Internal implementation record | Yes                                                          | Updated by this readiness gate.                                                                         |

## Optional/background

| Source                                | Path      | Present | File type         | Purpose                              | Authoritative status | Required before Week 4 implementation                   | Notes                                   |
| ------------------------------------- | --------- | ------- | ----------------- | ------------------------------------ | -------------------- | ------------------------------------------------------- | --------------------------------------- |
| `halden-energy-design-v2.md`          | Not found | No      | Markdown expected | Full fourteen-week design background | Missing              | Not strictly formula-authoritative without specs/ledger | Useful for intent if specs are unclear. |
| `halden-energy-seven-week-variant.md` | Not found | No      | Markdown expected | Seven-week variant design rationale  | Missing              | No                                                      | Mentioned by handoff as background.     |
| `halden-energy-14-week-arc.md`        | Not found | No      | Markdown expected | Earlier superseded arc draft         | Missing              | No                                                      | Handoff says superseded by design v2.   |

## Package artifact scan

No first-party files matching the following package-artifact categories were found in the searched project locations:

- CSV files
- XLSX/XLS files
- Jupyter notebooks
- MANIFEST files
- Python scripts
- expected-output files

No Week 4 or Week 10 package directories were found, so no package contents can be inspected yet.
