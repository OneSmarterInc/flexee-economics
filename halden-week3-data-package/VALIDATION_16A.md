# Batch 16A validation — Week 3

**Result: PASS**

| Check | Result |
| --- | --- |
| canonical package directory | PASS |
| canonical CSV inputs complete (no missing values) | PASS |
| student workbook recalculates with 0 errors | PASS |
| faculty workbook recalculates with 0 errors | PASS |
| worked example: Excel formulas == Python engine | PASS |
| faculty workbook answers (live Excel formulas) == Python engine | PASS |
| student/faculty separation (student yellow cells empty, no fixtures in student files) | PASS |
| student notebook executes | PASS |
| faculty notebook reproduces golden fixtures | PASS |
| expected outputs available (golden fixtures non-empty) | PASS |
| ASSERT: Rotterdam covers variable cost (+2.00) but loses money net (-0.30) | PASS |
| ASSERT: Idling Rotterdam is worse than running it short-run (idle delta < 0) | PASS |
| ASSERT: Baton Rouge and Singapore nets reconcile with Week 1 (20.35 and 8.60) | PASS |
| ASSERT: Window 1 is bounded: every cohort state keeps the NWE crack above the 2.60 shutdown floor | PASS |
| ASSERT: Even when the room runs Europe hard, Rotterdam still covers variable cost | PASS |
| ASSERT: A disciplined room turns Rotterdam net-positive | PASS |
| ASSERT: Ledger points reproduced: 3.25 / 4.60 / 5.95 | PASS |

## Flags (non-blocking unless marked)
- Window 1 response is symmetric as recorded in the ledger (slope 9 either side of 75% utilization: 3.25 / 4.60 / 5.95). The ledger text calls it asymmetric. Numbers encoded as recorded; decide whether to add asymmetry.