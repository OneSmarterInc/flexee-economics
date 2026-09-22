# Week 4 Reference Map

Inventory date: 2026-09-22.

## Reference package status

`halden-week4-data-package/` was not found in the searched locations. The Week 4 reference calculation flow cannot be inspected yet.

Blocked missing artifacts:

- Week 4 MANIFEST
- Week 4 CSV inputs
- Week 4 Excel workbook(s)
- Week 4 notebooks
- Week 4 Python scripts
- Week 4 expected outputs
- Week 4 README or other package notes

## Input files

No Week 4 input files were found.

| Input file    | Columns       | Data types    | Status                                          |
| ------------- | ------------- | ------------- | ----------------------------------------------- |
| Not available | Not available | Not available | Blocked by missing `halden-week4-data-package/` |

## Transformations and calculation sequence

No package transformations, formulas, or calculation sequence can be documented from authoritative reference artifacts because the package is missing.

Known only from the constants ledger:

- Week 4 concerns transfer-pricing distribution and discipline.
- Integrated margin is invariant to transfer price.
- Transfer price options have ledger-provided segment splits.
- Week 4 transfer-pricing discipline links to Week 6 discount rate and capital envelope.

These ledger relationships are not enough to implement Week 4 because the Week 4 package/spec must define exact student inputs, outputs, rounding, fixtures, memo, KPIs, scoring, and faculty visibility.

## Outputs and expected results

No Week 4 expected-output files were found.

| Output               | Expected result source                    | Status  |
| -------------------- | ----------------------------------------- | ------- |
| KPIs                 | Missing Week 4 spec/package               | Unknown |
| evaluation outputs   | Missing Week 4 spec/package               | Unknown |
| score inputs         | Missing Week 4 spec/package               | Unknown |
| rank inputs          | Missing Week 4 spec/package               | Unknown |
| faculty causal trace | Missing Week 4 spec/package/faculty guide | Unknown |

## Week 4 specification mapping

`halden-week4-data-package-spec.md` was not found. Therefore every spec-to-reference mapping is blocked.

| Requirement                | Spec location | Reference artifact | Constant dependency                                          | Expected output | Implementation destination                             | Test strategy                                 | Ambiguity/conflict |
| -------------------------- | ------------- | ------------------ | ------------------------------------------------------------ | --------------- | ------------------------------------------------------ | --------------------------------------------- | ------------------ |
| Decision fields            | Missing       | Missing            | Constants ledger likely involved, exact dependencies unknown | Missing         | Batch 3 decision definitions                           | Golden fixtures from missing package          | Blocked            |
| Memo prompt/rubric         | Missing       | Missing            | Unknown                                                      | Missing         | Batch 3 memo definition and future evaluation pipeline | Feature/regression tests from missing package | Blocked            |
| Transfer-price calculation | Missing       | Missing            | Constants ledger integrated-margin section                   | Missing         | Future `app/Domain/Economics/Week4/`                   | Golden-master numeric comparison              | Blocked            |
| KPI generation             | Missing       | Missing            | Unknown                                                      | Missing         | Future economic evaluation domain layer                | Golden fixtures from package                  | Blocked            |
| Score/rank inputs          | Missing       | Missing            | Unknown                                                      | Missing         | Future scoring/ranking services                        | Golden fixtures and publication tests         | Blocked            |
| Faculty visibility         | Missing       | Missing            | Unknown                                                      | Missing         | Future faculty tools/Livewire components               | Authorization and output tests                | Blocked            |

## Reusable pattern implications from Week 10

`halden-week10-data-package/` was not found. Structural implications can only be taken from the handoff, which says:

- Week 4 is the pattern for single-decision weeks.
- Week 10 is the pattern for convergence weeks.
- Week 12 follows the Week 10 shape.

Architecture implication:

- Do not hard-code Week 4 as the only economic engine shape.
- Keep the Batch 3 decision/memo definition framework generic.
- Put Week 4 deterministic logic behind an explicit Week 4 domain boundary, while keeping shared interfaces open for later convergence-week engines.
- Do not design result persistence as a single opaque score; keep economic outputs, KPIs, scores, and ranks separable.
