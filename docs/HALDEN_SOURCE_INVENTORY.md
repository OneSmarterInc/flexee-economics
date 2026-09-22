# Halden Source Inventory

Inventory date: 2026-09-22.

Searched locations:

- `C:\Users\sakas\Documents\ChatGPT\Flexee-economics`
- `C:\Users\sakas\Documents\Flexee-economics`

Search exclusions were limited to dependency/build/cache folders under the application repository (`node_modules`, `vendor`, `.git`, `public/build`, and development-cache storage). No unrelated personal directories were searched.

Current repository check:

- `git status --short`: clean before this documentation work.
- `HEAD`: `9ccfba4 docs: prepare Week 4 implementation plan`.

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

## Package directories

The worked reference packages are still absent.

| Package                       | Expected location checked                                                                                                                              | Present | Blocking status                                                                               |
| ----------------------------- | ------------------------------------------------------------------------------------------------------------------------------------------------------ | ------- | --------------------------------------------------------------------------------------------- |
| `halden-week4-data-package/`  | `C:\Users\sakas\Documents\Flexee-economics\halden-week4-data-package`, `C:\Users\sakas\Documents\ChatGPT\Flexee-economics\halden-week4-data-package`   | No      | Blocks READY and Week 4 implementation.                                                       |
| `halden-week10-data-package/` | `C:\Users\sakas\Documents\Flexee-economics\halden-week10-data-package`, `C:\Users\sakas\Documents\ChatGPT\Flexee-economics\halden-week10-data-package` | No      | Blocks worked Week 10 package parity; does not block Week 4 formula reconciliation by itself. |

Specific Week 4 and Week 10 artifact categories checked and not found:

- MANIFEST files
- canonical CSV files
- XLSX/XLS workbooks
- Jupyter notebooks
- Python support files
- faculty solution workbook/notebook
- expected-output files

The only package-like search result was the unrelated application asset `public\fonts-manifest.dev.json`.

## Readiness implication

The authoritative markdown layer is now present and reconciled enough to update the implementation plan, gap review, constant reconciliation, and invariant test plan. Full Week 4 implementation remains blocked until `halden-week4-data-package/` is supplied with its manifest, canonical data, worked example, and expected outputs.
