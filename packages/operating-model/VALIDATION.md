# Operating model validation (v0.1, Quarters 1-4)

**Result: 22 of 22 checks pass.**

| # | Check | Result | Detail |
| --- | --- | --- | --- |
| 1 | Q4 2026 EBITDA calibrates to 3,750 (15,000 a year) | PASS | 3750.0000 |
| 2 | Opening return on capital matches KPI package (12.7%) | PASS | 12.74% |
| 3 | Opening debt-to-earnings matches KPI package (1.30x) | PASS | 1.30x |
| 4 | Rotterdam lost money in 3 of 4 quarters of 2026 | PASS | 3 losing quarters |
| 5 | Total EBITDA identical at cost, market and in-between crude prices | PASS | cost 3784.3582, market 3784.3582, lazy 3784.3582 |
| 6 | Moving from market ($73.70) to cost ($18.70) shifts $55/bbl x 120,000 bbl/day to the refinery | PASS | 602.250 vs 602.250 |
| 7 | Geneva earns nothing at cost, at market, inside the 10% bands, or above market | PASS | 0 at 18.70, 20.00, 67.00, 73.70, 80.00 |
| 8 | Geneva earns 35% of the gap at $46.20: 0.35 x 27.50 x 40,000 x 91.25 | PASS | $35.131M |
| 9 | Market crude price at Q4 2027 equals the Week 4 package ($73.70) | PASS | market 73.70, cost 18.70 |
| 10 | At a $4.60 European margin, running Rotterdam loses less than pausing it | PASS | run -19.9 vs pause -74.3 |
| 11 | At a $2.00 European margin (below the $2.60 shutdown point), pausing beats running | PASS | run -84.1 vs pause -74.3 |
| 12 | Running Rotterdam harder helps while the margin is above the shutdown point | PASS | 95%: -12.0 vs 78%: -22.3 |
| 13 | Restarting a paused Rotterdam charges the $85M restart once | PASS | -85.0 |
| 14 | Closing Rotterdam is permanent | PASS | closed |
| 15 | Pumping 10% less in Norway lowers EBITDA | PASS | cut 3662.1 vs run 3784.4 |
| 16 | The 11th rig pays for itself, the 12th does not, and the old presidents' 14th loses money | PASS | 11th rig worth $42.1M, 12th $36.1M, 14th $24.1M vs $40M cost |
| 17 | Rigs change production next quarter, not this one | PASS | this quarter equal; next quarter 143118 vs 141312 |
| 18 | Straits Pacific holds Singapore between 80% and 95% | PASS | 100 -> 95, 60 -> 80 |
| 19 | Holding a city price rise loses more shop traffic each quarter | PASS | 178.718 > 178.681 > 178.643 |
| 20 | Holding a city price cut gains less traffic each quarter as rivals match | PASS | 178.870 > 178.832 > 178.794 |
| 21 | Score order careful > average > careless in all four quarters | PASS | Q1: 88.1 / 82.2 / 12.2; Q2: 89.2 / 80.8 / 11.2; Q3: 92.2 / 84.6 / 7.8; Q4: 92.3 / 86.1 / 7.7 |
| 22 | Fixtures rebuild identically | PASS | 7fb41c10b2f3a4ed |
