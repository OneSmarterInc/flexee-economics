# Batch 16A validation — Week 7

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
| ASSERT: Core clusters (suburban, rural): ignoring the cut costs far less than matching it | PASS |
| ASSERT: Capacity game: holding beats matching at the assessed build probability (0.70) | PASS |
| ASSERT: Breakeven build probability is 0.375 — match only if the rival is likely bluffing | PASS |
| ASSERT: Window 3 reproduces the ledger: 0.380 / 0.420 / 0.450 | PASS |
| ASSERT: Window 3 bounded within 15% of base | PASS |
| ASSERT: A price war hurts more than discipline helps | PASS |

## Flags (non-blocking unless marked)
- Under the ledger elasticities, matching the price cut is not justified in ANY cluster, including urban. The Week 7 spec implied an elastic cluster might justify a response. That only happens at station-level elasticities (around -20; see the worked example). Decide whether urban should carry a station-level elasticity, or keep the lesson as "never match in these markets."