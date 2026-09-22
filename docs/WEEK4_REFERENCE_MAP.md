# Week 4 Reference Map

Inventory date: 2026-09-22.

## Reference package status

`halden-week4-data-package/` was not found in the searched locations. The markdown spec now defines the intended Week 4 structure, but the worked package remains the missing calculation oracle.

Blocked missing artifacts:

- Week 4 MANIFEST
- Week 4 canonical CSV inputs
- Week 4 Excel workbook(s)
- Week 4 Jupyter notebooks
- Week 4 Python support files
- Week 4 faculty solution workbook/notebook
- Week 4 expected outputs
- Week 4 README or package notes

## Spec-defined datasets

The Week 4 spec says six datasets ship in both workbook tabs and CSV-backed Python notebook form:

| Dataset                                   | Purpose                                                | Package status            |
| ----------------------------------------- | ------------------------------------------------------ | ------------------------- |
| Permian lifting costs by vintage          | marginal-barrel and delivered-cost reasoning           | Specified, package absent |
| Baton Rouge yield economics by crude type | crack, complexity, opex, refining economics            | Specified, package absent |
| Gulf Coast market differentials           | WTI, wellhead discount, external anchor                | Specified, package absent |
| Geneva trading desk arbitrage capability  | capture rate and volume cap                            | Specified, package absent |
| Segment compensation plan                 | upstream/refining targets and political incentives     | Specified, package absent |
| Integrated margin reconciliation          | worked example on adjacent data and current-week blank | Specified, package absent |

## Week 4 specification mapping

| Requirement                | Spec status                                                                                                   | Reference artifact               | Implementation destination                    | Test strategy                                          | Ambiguity/blocker               |
| -------------------------- | ------------------------------------------------------------------------------------------------------------- | -------------------------------- | --------------------------------------------- | ------------------------------------------------------ | ------------------------------- |
| Decision field             | Transfer price with market, marginal-cost, midpoint anchors and custom/intermediate value                     | Missing package                  | Batch 3 decision definitions                  | Feature tests plus package parity                      | Bounds/precision unknown        |
| Analytical evidence        | Students calculate integrated margin, segment splits, compensation deltas, Geneva arbitrage externally        | Missing workbook/notebook        | Package distribution, not runtime analysis UI | Package parity once supplied                           | Exact submitted outputs unknown |
| Memo prompt                | Three sections: assumptions; method/result; decision/logic                                                    | Missing package/rubric           | Batch 3 memo definitions                      | Definition/rendering tests                             | Limits/rubric unknown           |
| Transfer-price calculation | Spec and ledger give constants and invariants                                                                 | Missing expected outputs         | Future Week 4 PHP domain service              | Spec-derived invariant tests plus package golden tests | Rounding/display unknown        |
| Faculty cohort view        | Transfer-price distribution against marginal and market benchmarks                                            | Missing faculty solution outputs | Stage 3 Livewire faculty tools                | Authorization and query tests later                    | Not Batch 4 implementation      |
| Faculty causal trace       | Week 4 to arbitrage, standing, and later consequences                                                         | Missing package trace fixtures   | Stage 3 trace model/query layer               | Fixture tests later                                    | Consequence thresholds unknown  |
| What-if rerun              | Rerun segment splits/standing consequences under alternate transfer price                                     | Missing expected outputs         | Stage 3 faculty console                       | Golden/counterfactual tests later                      | Standing effects unknown        |
| Faculty LLM context        | chosen transfer price, alternatives, computed integrated margin, memo, cohort distribution, incoming standing | No runtime LLM source package    | Queued LLM service/job pattern                | Payload assembly tests later                           | Not Batch 4 implementation      |

## Week 10 reusable implications

The Week 10 spec is present and should influence architecture without implementing Week 10. It confirms future weeks need inherited decisions, binding constraints, product-mix calculations, standing-dependent execution, causal trace, multiple simultaneous decision fields, and prior-week state dependencies.

Architecture implications:

- Do not hard-code Week 4 as the only economic engine shape.
- Keep decision definitions generic enough for multi-field weeks.
- Persist alternatives not taken.
- Keep prediction and outcome data separate.
- Keep deterministic outputs, KPI values, score inputs, scores, ranks, and visibility publication separate.
- Store consequence links explicitly so Week 10 and Week 14 can reconstruct prior causes.
