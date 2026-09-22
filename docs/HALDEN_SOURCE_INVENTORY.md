# Halden Source Inventory

Inventory date: 2026-09-22.

Searched locations:

- `C:\Users\sakas\Documents\ChatGPT\Flexee-economics`
- `C:\Users\sakas\Documents\Flexee-economics`

Search exclusions were limited to dependency/build/cache folders under the application repository (`node_modules`, `vendor`, `.git`, `public/build`, and development-cache storage). No unrelated personal directories were searched.

Current repository check:

- `HEAD` before this validation pass: `866f76c docs: reconcile authoritative Halden sources`.

## Authoritative markdown sources

The previously missing authoritative markdown files are present under `C:\Users\sakas\Documents\Flexee-economics`. They were not copied into the application repository because the supplied source directory is clearly identified, stable, and outside generated application docs.

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

## Week 4 package inventory

The Week 4 reference files are present as flat files under `C:\Users\sakas\Documents\Flexee-economics`, not inside a `halden-week4-data-package/` directory. They were not renamed or moved.

| File                          | Path                                                                    | SHA-256                                                            | Classification                       | Status                                            |
| ----------------------------- | ----------------------------------------------------------------------- | ------------------------------------------------------------------ | ------------------------------------ | ------------------------------------------------- |
| `permian_lifting.csv`         | `C:\Users\sakas\Documents\Flexee-economics\permian_lifting.csv`         | `BE099BB7845D95AA989A2CE0C43E0A18DB660AB6E01B3C04B91E9FB5B54389E2` | canonical CSV                        | Present                                           |
| `cost_constants.csv`          | `C:\Users\sakas\Documents\Flexee-economics\cost_constants.csv`          | `FEB8EE3F11BA6164658F6B4D5D7AB3CA30078CBF21D41A6BDF8C7215591150FE` | canonical CSV                        | Present                                           |
| `segment_comp.csv`            | `C:\Users\sakas\Documents\Flexee-economics\segment_comp.csv`            | `BC305C5E4E90311B0452FA201A23663DD773A0006E655284F2F8066D3A2DD5A9` | canonical CSV                        | Present                                           |
| `worked_example_prior.csv`    | `C:\Users\sakas\Documents\Flexee-economics\worked_example_prior.csv`    | `094146C787CBF95EBAFB3F6AEA0DCE949F1866929E5A9FEF714F31B77DE53AFB` | canonical CSV / worked-example input | Present                                           |
| `halden_week4.xlsx`           | `C:\Users\sakas\Documents\Flexee-economics\halden_week4.xlsx`           | `A3BF2A23B4AAA6DCAB609B2BCFC293C063A5028921A03E6BE251C62A67FE90AA` | student workbook                     | Present                                           |
| `halden_week4_analysis.ipynb` | `C:\Users\sakas\Documents\Flexee-economics\halden_week4_analysis.ipynb` | `5B75EAF76475C132E814A08A592A2F1CF9637C5CEB0CBD06F2D2B42360DD81CA` | student notebook                     | Present but path-broken as supplied               |
| `MANIFEST.md`                 | `C:\Users\sakas\Documents\Flexee-economics\MANIFEST.md`                 | `A9D89991AD60937153EC951DF6E12B82E1954EE8AAA58451CA6C18CEB12113C6` | MANIFEST                             | Present, but describes Week 10 rather than Week 4 |

Missing Week 4 package artifacts:

- Week 4-specific MANIFEST
- `data/` directory expected by `halden_week4_analysis.ipynb`
- separate six-CSV layout promised by the Week 4 spec
- faculty solution workbook
- faculty solution notebook
- expected-output fixture files
- package README or validation instructions

## Week 10 package note

`MANIFEST.md`, `halden_week10.xlsx`, and `halden_week10_analysis.ipynb` are present in the same source folder. The manifest describes the Week 10 package pattern, not Week 4. Week 10 was not validated in this pass beyond identifying that the root manifest is not a Week 4 manifest.

## Readiness implication

The authoritative markdown layer and flat Week 4 files are present and reconciled enough to create partial golden fixtures. Full Week 4 implementation remains blocked until the package defects above are resolved or explicitly accepted as the authoritative package shape.
