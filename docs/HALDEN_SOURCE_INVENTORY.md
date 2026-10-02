# Halden Source Inventory

Inventory date: 2026-09-22.

Searched locations:

- `C:\Users\sakas\Documents\ChatGPT\Flexee-economics`
- `C:\Users\sakas\Documents\Flexee-economics`

Search exclusions were limited to dependency/build/cache folders under the application repository (`node_modules`, `vendor`, `.git`, `public/build`, and development-cache storage). No unrelated personal directories were searched.

## Authoritative markdown sources

The authoritative markdown files are present under `C:\Users\sakas\Documents\Flexee-economics`. They were not copied into the application repository because the supplied source directory is clearly identified, stable, and outside generated application docs.

| Source                               | Path                                                                           | SHA-256                                                            | Status  | Purpose                                                                                       |
| ------------------------------------ | ------------------------------------------------------------------------------ | ------------------------------------------------------------------ | ------- | --------------------------------------------------------------------------------------------- |
| `HANDOFF-README.md`                  | `C:\Users\sakas\Documents\Flexee-economics\HANDOFF-README.md`                  | `51F34AF2A8A4A0F013E1935B14AB623E2E9ED7ECCD41033802CF13235DF94C91` | Present | Process/source-order guidance.                                                                |
| `halden-development-instructions.md` | `C:\Users\sakas\Documents\Flexee-economics\halden-development-instructions.md` | `F585C132AD484741A73635083C2EE833A32262E98DA587AF2CEBF6F221AF9EE5` | Present | Build architecture, Python/Laravel boundary, LLM queue pattern, tenancy, sequencing, gotchas. |
| `halden-week4-data-package-spec.md`  | `C:\Users\sakas\Documents\Flexee-economics\halden-week4-data-package-spec.md`  | `8BA320474AAB95A13B72CCF854A335AC005DB42A799121B0B1957B51CD01CE55` | Present | Week 4 decision, datasets, memo, faculty layer, constants, template pattern.                  |
| `halden-week10-data-package-spec.md` | `C:\Users\sakas\Documents\Flexee-economics\halden-week10-data-package-spec.md` | `732B78986C435FDD8F49AFF1003ED7B937290CC21B79DBDF84D5B76D6853FD18` | Present | Convergence-week architecture check.                                                          |
| `halden-calibration-review.md`       | `C:\Users\sakas\Documents\Flexee-economics\halden-calibration-review.md`       | `2314E6E204CFA487ABCEA952CFB2C258F9D59A3EDB2B9770F9F0092175B06CD1` | Present | Provisional calibration status and final market-refresh checklist.                            |
| `halden-energy-role-charters.md`     | `C:\Users\sakas\Documents\Flexee-economics\halden-energy-role-charters.md`     | `323B595749D4A835547DC8FA2CD17BC615BBD48B3C10B54C837ABEA1DCF27184` | Present | Five student seat charters.                                                                   |
| `halden-faculty-teaching-guide.md`   | `C:\Users\sakas\Documents\Flexee-economics\halden-faculty-teaching-guide.md`   | `209ACDB33AAFF5D83D65FD4DE60CDA4E3541A91BFD177559BD317D1BE3E11087` | Present | Faculty cycle, tools, debrief guidance, assessment.                                           |
| `halden-student-guide.md`            | `C:\Users\sakas\Documents\Flexee-economics\halden-student-guide.md`            | `608E6A071B033F0FCEF0698723BADD48E8572E1A0417A0A26339F1093B9DF481` | Present | Student onboarding and weekly rhythm.                                                         |
| `halden-energy-design-v2.md`         | `C:\Users\sakas\Documents\Flexee-economics\halden-energy-design-v2.md`         | `0AADF339801C4485FE64D8DA462A882F4D2E139ED1B47EA40A1FF6121C9BF2A3` | Present | Full fourteen-week design background.                                                         |
| `halden-constants-ledger.md`         | `C:\Users\sakas\Documents\Flexee-economics\halden-constants-ledger.md`         | `62D26482535FCD6CA8D9EDC5644D8784CBDCF9F3879B6CC87A30392251328BBC` | Present | Current constants ledger and single source of economic truth.                                 |

The current source-of-truth constants ledger is `halden-constants-ledger.md` unless an explicitly newer authoritative ledger is supplied.

## Week 4 source files

The original Week 4 files were supplied as flat files under `C:\Users\sakas\Documents\Flexee-economics`. They remain the external provenance inputs.

| File                          | SHA-256                                                            | Classification                       | Normalized destination                                           |
| ----------------------------- | ------------------------------------------------------------------ | ------------------------------------ | ---------------------------------------------------------------- |
| `permian_lifting.csv`         | `BE099BB7845D95AA989A2CE0C43E0A18DB660AB6E01B3C04B91E9FB5B54389E2` | canonical CSV                        | `halden-week4-data-package/data/permian_lifting.csv`             |
| `cost_constants.csv`          | `FEB8EE3F11BA6164658F6B4D5D7AB3CA30078CBF21D41A6BDF8C7215591150FE` | canonical CSV                        | `halden-week4-data-package/data/cost_constants.csv`              |
| `segment_comp.csv`            | `BC305C5E4E90311B0452FA201A23663DD773A0006E655284F2F8066D3A2DD5A9` | canonical CSV                        | `halden-week4-data-package/data/segment_comp.csv`                |
| `worked_example_prior.csv`    | `094146C787CBF95EBAFB3F6AEA0DCE949F1866929E5A9FEF714F31B77DE53AFB` | canonical CSV / worked-example input | `halden-week4-data-package/data/worked_example_prior.csv`        |
| `halden_week4.xlsx`           | `A3BF2A23B4AAA6DCAB609B2BCFC293C063A5028921A03E6BE251C62A67FE90AA` | student workbook                     | `halden-week4-data-package/student/halden_week4.xlsx`            |
| `halden_week4_analysis.ipynb` | `5B75EAF76475C132E814A08A592A2F1CF9637C5CEB0CBD06F2D2B42360DD81CA` | original student notebook            | normalized into executable `student/halden_week4_analysis.ipynb` |

## Normalized Week 4 package

The repository now contains `halden-week4-data-package/`, a normalized reference package created from the supplied flat Week 4 materials without changing authoritative economic values.

The package adds:

- Week 4-specific `MANIFEST.md` and `README.md`;
- `data/` layout matching notebook execution;
- student workbook and student notebook;
- faculty solution workbook and faculty solution notebook;
- expected-output JSON for the worked example and current Week 4 reference;
- provenance hashes and a validation script.

Standalone Week 4 reference-package status: `READY WITH NON-BLOCKING QUESTIONS`.

The package validates required files, hashes, CSV arithmetic, worked-example output, student/faculty notebook execution, expected-output consistency, workbook formulas, and workbook formula recalculation through Artifact Tool checks.

## Remaining questions

The normalized package does not settle every later implementation rule:

- custom transfer-price minimum, maximum, increment, and precision;
- display rounding for `9.625/bbl` Geneva capture;
- monthly or annual Geneva period conversion beyond the supplied `40,000 bbl/day` cap;
- Week 4 to Week 6 disciplined/base/lax cohort classification thresholds.

These are not blockers for the standalone Week 4 package oracle. They remain blockers for the relevant UI validation, periodized Geneva P&L, and later consequence-engine implementation.

## Complete handoff package note

The complete handoff bundle supersedes the earlier Week 10 source note. The repository now contains registered authoritative package roots for Weeks 1, 2, 3, 5, 6, 7, 8, 9, 10, 11, 12, and 13. Week 4 remains the stable validated baseline and Week 14 has no computational package by design.

Week 10 is upgraded to the 16A package standard. Week 12 is no longer quarantined; the package records the revised `$1,200M` adjacent-transition ceiling and `$550M` divestment proceeds.
