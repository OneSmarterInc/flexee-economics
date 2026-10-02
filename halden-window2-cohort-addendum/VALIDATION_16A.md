# Batch 16A validation — Week 6→8 cohort window 2

**Result: PASS**

| Check | Result |
| --- | --- |
| canonical package directory | PASS |
| canonical CSV inputs complete (no missing values) | PASS |
| faculty workbook recalculates with 0 errors | PASS |
| worked example: Excel formulas == Python engine | PASS |
| faculty workbook answers (live Excel formulas) == Python engine | PASS |
| faculty/platform only: no student-facing artifact present (response function stays hidden from students) | PASS |
| faculty notebook reproduces golden fixtures | PASS |
| expected outputs available (golden fixtures non-empty) | PASS |
| ASSERT: A split room (50% add capacity) produces no shift | PASS |
| ASSERT: More capacity added by the room always means a lower Week 8 crack | PASS |
| ASSERT: Asymmetric: universal overbuild hurts at least twice what universal restraint helps | PASS |
| ASSERT: Bounded: Baton Rouge margin moves no more than 15% from baseline in any cohort state | PASS |
| ASSERT: Tuned to be visible: the extreme state moves Baton Rouge margin by at least 10% | PASS |
| ASSERT: Worst case stacked on the OPEC holds-full shock still leaves crack and Baton Rouge margin positive | PASS |

## Flags (non-blocking unless marked)
- Calibration: the response is tuned for pedagogical visibility (extreme state moves Baton Rouge margin ~15%). An anchor episode and real-world elasticity have not been attached yet. Add them in the market-data refresh, as the design requires for every cohort window.
- Week 7 compounding deferred: the Week 7 spec says a cohort that also matches the rival expansion compounds the overbuild. That term is not included here, so window 2 can run now on Week 6 data alone. Add it when the Week 7 runtime exists.
- Seven-week variant does not use window 2. Its single cohort window is the Week 4 → Week 6 discount-rate linkage.