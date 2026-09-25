# Authoritative Package Batch Inventory

Batch 18D ingests the current authoritative package batch as package content only. It does not implement week-specific economics, runtime resolution, KPI effects, ranking changes, consequences, or UI.

## Registered Packages

| Week | Package root                 | Status     | Notes                                                                                         |
| ---- | ---------------------------- | ---------- | --------------------------------------------------------------------------------------------- |
| 1    | `halden-week1-data-package`  | Registered | Asset register package; introduces `book_values.csv`.                                         |
| 2    | `halden-week2-data-package`  | Registered | Retail elasticity package with stored 156-week synthetic price/volume data.                   |
| 3    | `halden-week3-data-package`  | Registered | Rotterdam restart package; window-1 response recorded as symmetric.                           |
| 5    | `halden-week5-data-package`  | Registered | Currency exposure package with entity currency flows.                                         |
| 6    | `halden-week6-data-package`  | Registered | Existing Week 6 capital package retained; fixture hashes match the batch copy.                |
| 7    | `halden-week7-data-package`  | Registered | Retail competitive response package; no cluster justifies matching under ledger elasticities. |
| 8    | `halden-week8-data-package`  | Registered | Existing Week 8 OPEC package retained; fixture hashes match the batch copy.                   |
| 9    | `halden-week9-data-package`  | Registered | Rebranding package; fills per site remains a calibration lever.                               |
| 11   | `halden-week11-data-package` | Registered | Kessana hold-up/fiscal take package.                                                          |
| 13   | `halden-week13-data-package` | Registered | Factor markets and turnaround labor package.                                                  |

All registered packages are represented through the generic `AuthoritativeContentPackageRegistrationService`, which delegates validation to the existing `SimulationContentPackageService` and `ContentPackageValidator`.

## Excluded Packages

| Week | Status          | Reason                                                                                                                                        |
| ---- | --------------- | --------------------------------------------------------------------------------------------------------------------------------------------- |
| 4    | Stable baseline | Week 4 is already the validated vertical-slice reference and is not migrated by this bulk batch.                                              |
| 10   | Pending upgrade | Week 10 predates the 16A package standard and still needs faculty solution notebook, golden fixtures, provenance, and a validation report.    |
| 12   | Quarantined     | The user request requires Week 12 to remain excluded from activation pending explicit design acceptance of the Helix Rotterdam bucket change. |
| 14   | No package      | Week 14 is a board defense and assessment week with no computational package by design.                                                       |

## Validation Summary

The batch validator checks package presence, provenance hashes, parseable CSVs, parseable notebook JSON, golden fixture assertions, and the 16A golden tolerance policy where present.

Latest package validation:

```text
Validated 10 packages, 124 artifacts, 58 csvs.
```

Golden fixture comparison tolerance for 16A-standard packages is:

- Relative: `1e-3`
- Absolute: `1e-5`

Week 6 and Week 8 are earlier accepted package roots and do not contain the newer explicit tolerance metadata in their golden fixtures; their existing validated package files are retained.
