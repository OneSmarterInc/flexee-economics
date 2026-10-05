# Batch 16A validation — Week KPI scoring & consequences

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
| ASSERT: Composite weights sum to 1.00 (30/15/15/10/10/10/10) | PASS |
| ASSERT: Opening state reproduces design: ROACE 12.7%, FCF $7,116M, net debt/EBITDA 1.30x | PASS |
| ASSERT: All seven KPIs are available for every team in every week (no more incomplete rankings) | PASS |
| ASSERT: Week 1: identical opening state, so every team ties at rank 1 | PASS |
| ASSERT: Ranks do not change when a KPI is rescaled (units cannot distort the composite) | PASS |
| ASSERT: Composite stays within 0-100 for every team and week | PASS |
| ASSERT: Lazy transfer price lowers integrated margin in Week 4 | PASS |
| ASSERT: Funding Helix depresses near-term ROACE versus funding Rotterdam (back-loaded project) | PASS |
| ASSERT: Deferred maintenance shows up: the harvester ends with the lowest asset health | PASS |
| ASSERT: Window 2 input used in KPI rules equals the shift resolved from the section's Week 6 decisions | PASS |
| ASSERT: Every active consequence re-derives from team decisions to its expected value | PASS |
| ASSERT: Week 10 binding counts used in KPI rules equal those derived from the consequence rules | PASS |

## Flags (non-blocking unless marked)
- Reference team inputs for Weeks 2, 5, 8, 11, 12, 13 are synthetic test paths chosen to exercise the rules. They are fixtures for the scoring engine, not economic claims.
- KPI rule coefficients are first-pass calibration; review alongside the market-data refresh.