# Week 4 Reference Map

Inventory date: 2026-09-22.

## Reference package status

Week 4 files are present as flat files in `C:\Users\sakas\Documents\Flexee-economics`, but the package does not yet pass the final reference-package gate.

Blocking package issues:

- no Week 4-specific manifest was found;
- the only `MANIFEST.md` describes Week 10;
- no `halden-week4-data-package/` directory was found;
- the student notebook references `data/*.csv`, but the supplied Week 4 CSV files are flat in the source folder;
- no faculty solution workbook/notebook was found;
- no expected-output fixture file was found;
- no package-specific rounding, validation, or time-basis instructions were found.

## Spec-defined datasets

The Week 4 spec says six datasets ship in both workbook tabs and CSV-backed Python notebook form. The supplied package provides the values, but combines multiple spec datasets into `cost_constants.csv` rather than one CSV per dataset.

| Dataset                                   | Source file/tab                                             | Purpose                                                | Package status                        |
| ----------------------------------------- | ----------------------------------------------------------- | ------------------------------------------------------ | ------------------------------------- |
| Permian lifting costs by vintage          | `permian_lifting.csv`; workbook `Data — Permian` rows 3-7   | marginal-barrel and delivered-cost reasoning           | Present                               |
| Baton Rouge yield economics by crude type | `cost_constants.csv`; workbook `Data — Permian` rows 15-17  | crack, complexity, opex, refining economics            | Present inside combined constants CSV |
| Gulf Coast market differentials           | `cost_constants.csv`; workbook `Data — Permian` rows 10-13  | WTI, wellhead discount, external anchor                | Present inside combined constants CSV |
| Geneva trading desk arbitrage capability  | `cost_constants.csv`; workbook `Data — Permian` rows 18-19  | capture rate and volume cap                            | Present inside combined constants CSV |
| Segment compensation plan                 | `segment_comp.csv`; workbook `Data — Compensation` rows 4-7 | upstream/refining targets and political incentives     | Present                               |
| Integrated margin reconciliation          | `worked_example_prior.csv`; workbook `Worked Example`       | worked example on adjacent data and current-week blank | Present                               |

## Week 4 specification mapping

| Requirement                | Package finding                                                                                                 | Implementation destination                    | Test strategy                                 | Ambiguity/blocker                             |
| -------------------------- | --------------------------------------------------------------------------------------------------------------- | --------------------------------------------- | --------------------------------------------- | --------------------------------------------- |
| Decision field             | README says market-based, marginal-cost, midpoint, and intermediate custom value; no bounds/precision supplied  | Batch 3 decision definitions                  | Feature tests plus package parity             | Bounds/precision still unknown                |
| Analytical evidence        | Workbook and notebook leave current-week analysis blank/TODO for students                                       | Package distribution, not runtime analysis UI | Student-package integrity tests               | Exact submitted analysis fields still unknown |
| Memo prompt                | Workbook/notebook do not add package-specific memo limits or rubric beyond spec                                 | Batch 3 memo definitions                      | Definition/rendering tests                    | Limits/rubric unknown                         |
| Transfer-price calculation | Workbook worked example has formulas and cached values; notebook has same method but cannot execute as supplied | Future Week 4 PHP domain service              | Fixture parity against `tests/Fixtures/Week4` | Notebook path defect blocks full parity       |
| Faculty cohort view        | No faculty solution artifact supplied                                                                           | Stage 3 Livewire faculty tools                | Authorization and query tests later           | Missing faculty solution                      |
| Faculty causal trace       | No package trace fixtures supplied                                                                              | Stage 3 trace model/query layer               | Fixture tests later                           | Consequence thresholds unknown                |
| What-if rerun              | No faculty expected outputs supplied                                                                            | Stage 3 faculty console                       | Golden/counterfactual tests later             | Standing effects unknown                      |
| Faculty LLM context        | No runtime LLM source package; requirements remain from spec/design docs                                        | Queued LLM service/job pattern                | Payload assembly tests later                  | Not Batch 4 implementation                    |

## Workbook validation

`halden_week4.xlsx` has five visible sheets: `README`, `Data — Permian`, `Data — Compensation`, `Worked Example`, and `Your Analysis`.

Workbook findings:

- no hidden sheets;
- no named ranges;
- no external workbook links;
- yellow student-input cells are on `Your Analysis` at `B15:B20`, `B24:E26`, and `B30`;
- workbook cached values reproduce the worked example integrated margin of `$68.25`;
- current-week student analysis cells are blank by design;
- per-barrel values display as `$#,##0.00`;
- no native recalc engine was available in PATH (`soffice`/`libreoffice` not found), so validation used cached workbook values plus independent formula evaluation.

## Notebook validation

`halden_week4_analysis.ipynb` is student-facing and does not expose current-week answers. It loads `data/permian_lifting.csv`, `data/cost_constants.csv`, and `data/segment_comp.csv`.

Execution status: blocked as supplied. The source folder does not contain a `data/` directory, so execution fails on `FileNotFoundError: data/permian_lifting.csv`.

If the CSVs are placed under a `data/` folder without changing their contents, the notebook method matches the workbook method for the prior-period example. Do not treat that workaround as an authoritative package fix unless the source package is corrected or the package owner approves the flat-file layout.

## Week 10 reusable implications

The Week 10 spec is present and should influence architecture without implementing Week 10. It confirms future weeks need inherited decisions, binding constraints, product-mix calculations, standing-dependent execution, causal trace, multiple simultaneous decision fields, and prior-week state dependencies.

Architecture implications:

- Do not hard-code Week 4 as the only economic engine shape.
- Keep decision definitions generic enough for multi-field weeks.
- Persist alternatives not taken.
- Keep prediction and outcome data separate.
- Keep deterministic outputs, KPI values, score inputs, scores, ranks, and visibility publication separate.
- Store consequence links explicitly so Week 10 and Week 14 can reconstruct prior causes.
