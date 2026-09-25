# Batch 16A validation — Week 10

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
| ASSERT: Recession is uneven: jet falls hardest, gasoline least | PASS |
| ASSERT: Ledger reconciliation: refinery hits Baton Rouge -1.99%, Rotterdam -2.23%, Singapore -2.62% | PASS |
| ASSERT: Jet-heavy Singapore is hit hardest; gasoline-heavy Baton Rouge least | PASS |
| ASSERT: Disciplined reference team: no constraint binds | PASS |
| ASSERT: Constrained reference team: all five constraints bind (inherited, not new) | PASS |

## Flags (non-blocking unless marked)
- Replaces the earlier Week 10 reference build, which predated the 16A standard (no faculty notebook, golden fixtures, or provenance).
- Seven-week variant: Week 10 also folds in the Norwegian union negotiation. Its data and fixtures live in the Week 13 package.