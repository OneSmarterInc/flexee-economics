# Week 4 Reference Map

Inventory date: 2026-09-22.

## Reference package status

The normalized Week 4 reference package now lives in `halden-week4-data-package/`.

Status: `READY WITH NON-BLOCKING QUESTIONS`.

Resolved package issues:

- Week 4-specific manifest and README are present.
- The package has a `data/` directory matching notebook execution.
- Student workbook and student notebook are present.
- Faculty solution workbook and faculty solution notebook are present.
- Worked-example and current-week expected-output files are present.
- The package records hashes for external authority files and normalized package files.
- `scripts/validate_week4_package.py` validates required files, hashes, CSV arithmetic, notebook execution, workbook formulas, and expected-output parity.

Still unresolved for later implementation:

- custom transfer-price bounds, increment, and precision;
- Geneva `9.625/bbl` display rounding and period conversion;
- Week 4 to Week 6 disciplined/base/lax cohort thresholds.

These do not block standalone Week 4 oracle use, but they do constrain UI validation, periodized Geneva reporting, and later consequence logic.

## Spec-defined datasets

The Week 4 spec says six conceptual datasets ship in both workbook tabs and CSV-backed Python notebook form. The normalized package preserves the supplied four-CSV layout rather than inventing extra source files.

| Dataset                                   | Normalized source                                                | Purpose                                            | Package status                        |
| ----------------------------------------- | ---------------------------------------------------------------- | -------------------------------------------------- | ------------------------------------- |
| Permian lifting costs by vintage          | `data/permian_lifting.csv`; workbook `Data — Permian` rows 3-7   | marginal-barrel and delivered-cost reasoning       | Present                               |
| Baton Rouge yield economics by crude type | `data/cost_constants.csv`; workbook `Data — Permian` rows 15-17  | crack, complexity, opex, refining economics        | Present inside combined constants CSV |
| Gulf Coast market differentials           | `data/cost_constants.csv`; workbook `Data — Permian` rows 10-13  | WTI, wellhead discount, external anchor            | Present inside combined constants CSV |
| Geneva trading desk arbitrage capability  | `data/cost_constants.csv`; workbook `Data — Permian` rows 18-19  | capture rate and volume cap                        | Present inside combined constants CSV |
| Segment compensation plan                 | `data/segment_comp.csv`; workbook `Data — Compensation` rows 4-7 | upstream/refining targets and political incentives | Present                               |
| Integrated margin reconciliation          | `data/worked_example_prior.csv`; workbook `Worked Example`       | prior worked example and current-week method       | Present                               |

## Week 4 specification mapping

| Requirement                | Package finding                                                                                         | Implementation destination                    | Test strategy                                                      | Remaining question                                   |
| -------------------------- | ------------------------------------------------------------------------------------------------------- | --------------------------------------------- | ------------------------------------------------------------------ | ---------------------------------------------------- |
| Decision field             | Market-based `$73.70`, marginal-cost `$18.70`, midpoint `$46.20`, and custom/intermediate value allowed | Batch 3 decision definitions                  | Feature tests plus package parity                                  | Custom bounds/precision still unknown                |
| Analytical evidence        | Workbook and notebook leave current-week analysis blank/TODO for students                               | Package distribution, not runtime analysis UI | Student-package integrity tests                                    | Exact submitted analysis fields still product choice |
| Memo prompt                | Spec supplies key assumptions, analytical method/result, and decision/logic structure                   | Batch 3 memo definitions                      | Definition/rendering tests                                         | Limits/rubric not package-defined                    |
| Transfer-price calculation | Expected outputs define worked example and current-week reference                                       | Future Week 4 PHP domain service              | Fixture parity against `halden-week4-data-package/expected/*.json` | Custom validation rules still unknown                |
| Faculty cohort view        | Faculty solution workbook/notebook now supplied in normalized package                                   | Stage 3 Livewire faculty tools                | Faculty artifact parity plus authorization/query tests later       | Consequence thresholds unknown                       |
| Faculty causal trace       | Numeric deterministic trace values are present; later standing effects are not                          | Stage 3 trace model/query layer               | Fixture tests later                                                | Standing effects and thresholds unknown              |
| What-if rerun              | Current deterministic anchors can be rerun; package does not define consequence thresholds              | Stage 3 faculty console                       | Golden/counterfactual tests later                                  | Standing effects unknown                             |
| Faculty LLM context        | No runtime LLM source package; requirements remain from spec/design docs                                | Queued LLM service/job pattern                | Payload assembly tests later                                       | Not Batch 4 implementation                           |

## Workbook validation

`student/halden_week4.xlsx` and `faculty/halden_week4_solution.xlsx` each have five visible sheets: `README`, `Data — Permian`, `Data — Compensation`, `Worked Example`, and `Your Analysis`.

Workbook findings:

- no hidden sheets;
- no external workbook links;
- student current-week analysis cells remain blank by design;
- faculty workbook fills the same cells with formulas;
- worked example reproduces integrated margin of `$68.25`;
- current-week faculty workbook reproduces delivered marginal cost `$14.10`, transfer prices `$73.70`, `$18.70`, `$46.20`, integrated margin `$76.75`, and Geneva midpoint capture `9.625/bbl`;
- Artifact Tool recalculation produced no formula errors in the inspected ranges.

## Notebook validation

`student/halden_week4_analysis.ipynb` is student-facing and does not expose current-week answer constants.

`faculty/halden_week4_solution.ipynb` executes against the same canonical CSVs and produces `reference_outputs` matching `expected/week4_reference.json`.

Both notebooks resolve `data/` relative to either the package root or their own subdirectory.

## Week 10 reusable implications

The Week 10 spec is present and should influence architecture without implementing Week 10. It confirms future weeks need inherited decisions, binding constraints, product-mix calculations, standing-dependent execution, causal trace, multiple simultaneous decision fields, and prior-week state dependencies.

Architecture implications:

- Do not hard-code Week 4 as the only economic engine shape.
- Keep decision definitions generic enough for multi-field weeks.
- Persist alternatives not taken.
- Keep prediction and outcome data separate.
- Keep deterministic outputs, KPI values, score inputs, scores, ranks, and visibility publication separate.
- Store consequence links explicitly so Week 10 and Week 14 can reconstruct prior causes.
