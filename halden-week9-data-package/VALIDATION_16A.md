# Batch 16A validation — Week 9

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
| ASSERT: Strong-equity core: rebranding destroys value (net per fill < 0), keep Cordell | PASS |
| ASSERT: Southeast edge pays back faster than gulf secondary | PASS |
| ASSERT: Partial rebrand earns about 3x the full rebrand (core equity destruction) | PASS |
| ASSERT: Ledger reconciliation: partial $22.7M/yr, full $7.7M/yr, edge 5.5 yrs, gulf 14.6 yrs | PASS |
| ASSERT: A price-war cohort faces a longer payback (window 3 consequence) | PASS |

## Flags (non-blocking unless marked)
- fills_per_site_year (180,000) scales every payback proportionally. It is a calibration lever for the market-data refresh, not a fixed constant.