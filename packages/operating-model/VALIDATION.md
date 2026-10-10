# Operating model validation (v1.0, Quarters 1-14)

**Result: 89 of 89 checks pass.**

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
| 21 | Norwegian lifting falls from $28.00 to about $25.85 when the krone weakens to 11.05 | PASS | $25.8462 |
| 22 | European station profit loses about 6.45% in dollars when the euro falls to 1.015 | PASS | -6.45% |
| 23 | Rotterdam is a natural hedge: its net euro swing is under a fifth of the swing on its gross euro costs (Week 5: about a tenth) | PASS | net 1.28 vs gross -11.00 (12%) |
| 24 | Before 2028, currency effects are held at zero (rates moved less than 1%) | PASS | fx_live = 0 for 2026-2027 |
| 25 | The $300M euro forward sold before the shock pays $19.35M in Q1 2028 | PASS | $19.3548M |
| 26 | A crude hedge gains when oil falls and loses when it rises (euro and krone legs equal in both) | PASS | oil -$5: 93.9; oil +$5: -107.4 |
| 27 | Selling $600M of euros at 1.015 loses when the euro recovers to 1.031 | PASS | $-9.46M |
| 28 | Window 1: at a 75% average the margin stays $4.60; at 90% it falls to $3.25; it never goes below $2.60 | PASS | 4.60 / 3.25 / 2.60 |
| 29 | Capital envelope: all at cost $1,520M at 6.5%; mixed $1,150M at 8.5%; lax $950M at 11% | PASS | 1520 / 1150 / 950 |
| 30 | A committed project costs its outlay now and pays a quarter of year one, at 88% of forecast, next quarter | PASS | outlay 640, then 39.60 a quarter |
| 31 | A Rotterdam project stops paying if Rotterdam closes | PASS | 0 after closure |
| 32 | The score counts free cash flow before new projects; net debt still carries the outlay | PASS | score FCF 1848.7 vs cash FCF 1208.7 |
| 33 | Holding price against the rival's cut costs under a twentieth of matching it, in every Cordell market | PASS | urban: hold 0.19 vs match 21.3; suburban: hold 0.12 vs match 24.8; rural: hold 0.02 vs match 14.2; interstate: hold 0.04 vs match 10.6 ($M a quarter) |
| 34 | Matching everywhere costs 6c on every Cordell gallon and shows as its own line | PASS | -70.95 |
| 35 | Before the rival moves, the answer levers change nothing | PASS | Q2 2028 EBITDA equal |
| 36 | Capacity game: at a 70% chance the rival builds, holding (-$28M a year) beats matching (-$80M); breakeven is 37.5% | PASS | hold -28, match -80, breakeven 0.375 |
| 37 | Once the rival builds, holding costs $10M a quarter and matching $35M, under Refineries | PASS | -10 / -35; 0 before it builds |
| 38 | Window 3 reproduces the ledger: a price war 0.38, base 0.42, disciplined 0.45 a fill, bounded within 15% of base | PASS | 0.38 / 0.42 / 0.45; floor 0.357 at full aggression |
| 39 | A team that matches in two of four Cordell markets is half aggressive | PASS | 0.5 |
| 40 | Expected WTI across the three OPEC+ outcomes is $80.70 from a $74 start | PASS | 80.70 |
| 41 | Outcomes: the cut holds WTI 88 / margin 16.60; partly holds 81 / 19.05; fails 70 / 22.90 (crack -0.35 a dollar, Window 2 at pivot) | PASS | 88/16.60, 81/19.05, 70/22.90 |
| 42 | Integration conflict: when the cut holds, the oil fields earn more and Baton Rouge earns less than when it fails | PASS | Permian 935 vs 703; Baton Rouge 700 vs 987 |
| 43 | Drivers barely react: a $14 crude rise moves station volume -0.31% | PASS | -0.3125% |
| 44 | Planning for the cut to hold buys 30 days of Baton Rouge crude ahead: +$210M if it holds, -$60M if it fails, less $24M of interest either way | PASS | +209.7 / -59.9; carry 23.5; planning for failure buys nothing |
| 45 | Outside the OPEC+ quarter the planning case changes nothing | PASS | Q3 2028 EBITDA equal |
| 46 | Window 2: all building -$3.00; half 0; none +$1.50; overbuilding costs twice what restraint earns | PASS | -3.00 / 0 / +1.50 |
| 47 | Worst case (the cut holds and everyone built): Gulf Coast margin $13.60 | PASS | 13.60 |
| 48 | Putting the Halden name on a station costs $79,070 (a $340M programme over 4,300 sites) | PASS | $79,069.77 |
| 49 | At a $0.42 shop margin the rebrand earns -$15.0M a year in the heartland, +$7.6M on the Gulf Coast, +$15.1M on the Southeast edge | PASS | core -14.985, gulf 7.560, edge 15.120 |
| 50 | Paybacks: Southeast edge 5.5 years, Gulf Coast 14.6; both together 8.5 years on $193.7M; all three regions earn only $7.7M a year | PASS | edge 5.49, gulf 14.64, both 8.54; all three 7.695/yr |
| 51 | After a price war (shop margin $0.38) the same rebrand takes 9.4 years to pay back | PASS | 9.44 years |
| 52 | Rebranding the Southeast edge costs $83.0M of capital now (added back in the score) and pays $3.78M a quarter in the shop from the next quarter | PASS | outlay 83.02, then 3.780 a quarter, charged once |
| 53 | Window 3 lands: a class that fought a price war earns less in the shops than one that held its prices | PASS | 161.6 < 178.6 < 191.3 |
| 54 | Demand falls 1.05% for gasoline, 2.55% for diesel and 4.8% for jet when the economy shrinks 3% | PASS | gasoline -1.05%, diesel -2.55%, jet -4.80% |
| 55 | Runs fall 1.99% at Baton Rouge, 2.23% at Rotterdam and 2.62% at Singapore (Week 10 package: -1.992, -2.226, -2.622): jet-heavy plants fall furthest | PASS | br -1.99%, rot -2.23%, sg -2.62% |
| 56 | In the recession quarter Baton Rouge runs 1.99% below what the team asked for, Singapore 2.62% | PASS | throughput cut; the accepted request itself unchanged |
| 57 | Marcus has cover after a crude price at cost or well below market (46.20), none after market or near-market (70) | PASS | cost yes, 46.20 yes, market no, 70 no |
| 58 | With cover, a cut from 96% to 90% is only half delivered (93%); without it, the cut lands; a cut outside the recession is never resisted | PASS | 93% vs 90% |
| 59 | Asking Singapore for 100% in two of the last four quarters strains the partnership; once does not | PASS | 2 of 4 yes, 1 of 4 no |
| 60 | A strained Straits Pacific runs Singapore at 80% in the recession whatever Halden asks; a cooperative one honours the request | PASS | 80 vs 90; no effect outside the recession |
| 61 | Rotterdam sits just above its shutdown point in the recession: running still beats pausing, barely | PASS | margin 2.90 vs shutdown point 2.60 |
| 62 | Kessana valuation reproduces the Week 11 package: profit oil $67, annuity 5.2161, staying worth $4,515M at 62%, $3,802M at 68%, $3,089M at 74%, $2,376M at 80% | PASS | current 4515.28, mid 3802.34, demanded 3089.40, harsh 2376.46 |
| 63 | Staying beats the $180M exit at every take on the grid, including 80%, and falls as the take rises | PASS | worst case 2376 vs exit 180 |
| 64 | On economics alone the government could push the take to 98.5% before Halden walks (package 0.984851); sunk capital never enters | PASS | 0.9849 |
| 65 | The 74% demand sits inside the range of comparable fiscal terms (50% to 85%) | PASS | 0.50-0.85 |
| 66 | At $68 oil the bigger share shows as its own line: signing at 74% costs $100M a quarter, settling at 68% $50M, a called bluff at 80% $150M | PASS | accept -100.2, counter -50.1, threaten -150.3 |
| 67 | Leaving Kessana ends the line, pays $180M off the debt and takes the $2,300M book value off capital employed; the write-down never touches EBITDA | PASS | EBITDA loses 317.3; net debt 19222.3 vs 19180.2 |
| 68 | The settled take carries into every later quarter, and the position only acts in the quarter the government asks | PASS | 0.68 carried; 0.62 outside Q3 2029 |
| 69 | Over ten years signing at 74% is worth $2,909M more than leaving, and settling at 68% another $713M on top | PASS | 2909.4; 712.9 |
| 70 | Project values across the nine worlds reproduce the Week 12 package (Helix at Rotterdam -180 to +308, Permian +220 to -126, biofuels -40 to +136, wind -90 to +120, selling Europe -60 to +102) | PASS | helix_rotterdam -180..308; permian_expansion -126..220; biofuel_conversion -40..136; offshore_wind -90..120; euro_retail_divest -60..102 |
| 71 | Every project swings sign across the worlds: there is no portfolio that wins everywhere | PASS | Permian wins when carbon stays cheap and demand holds; Helix at Rotterdam wins when carbon is dear and demand collapses |
| 72 | After the $600M sustaining floor, $1,200M is free; 17 sets of projects can be funded, 3 of them with Helix at Rotterdam, and 2 only because the European stations are sold | PASS | 17 fundable, 3 with Helix at Rotterdam, 2 unlocked by the sale |
| 73 | The full transition bet (Helix at Rotterdam plus offshore wind, $1,750M) is affordable only with the $550M from selling the European stations; Helix and biofuels together break the $1,200M adjacent ceiling | PASS | envelope; ok with the sale; adjacent ceiling |
| 74 | A closed Rotterdam cannot be converted to biofuels (the Q3 2027 call reaches Q4 2029) | PASS | 10 fundable sets with Rotterdam closed |
| 75 | Selling the European stations brings $550M in now (off the debt, not into EBITDA) and the stations' line is gone from the next quarter, with Europe's share of the retail fixed cost | PASS | next quarter Europe 82.3 -> 0; fixed -60.0 -> -47.8 |
| 76 | Helix at Rotterdam's $1,200M goes out evenly over five years from the quarter after the go-ahead ($60M a quarter, added back in the score); nothing goes out in the go-ahead quarter | PASS | 60 a quarter |
| 77 | Outside Q4 2029 the portfolio page does nothing | PASS | same net debt |
| 78 | The union's 8% costs $33.6M a year gross and $7.39M after Norway's 78% tax: the concession costs Halden 22% of its face value (Week 13 package) | PASS | 33.6 gross, 7.392 after tax |
| 79 | A marginal Permian worker earns Halden $507.6k a year against a $145k wage (3.5 times): Halden takes the market wage and competes on keeping people | PASS | 507.6k, 3.5007x |
| 80 | The turnaround costs $81M at the contractor peak now, or an expected $78M off-peak next quarter (60 plus a 12% chance of a $150M breakdown): a $3M saving, within 5%, for three points of plant condition | PASS | 81 vs 78 |
| 81 | Accepting the 8% costs $8.4M a quarter on the Norway line and $1.85M after the tax shield; nothing stops | PASS | wages -8.40; after tax 1.848 |
| 82 | Refusing brings a two-week stoppage on the operated fields and the union's 8% anyway by arbitration; half brings a one-week stoppage and 4%: both cost more than accepting, even after tax | PASS | stoppage refuse -181.5, half -90.8 |
| 83 | The settled raise stays on the wage bill in every later quarter; outside Q1 2030 the answer changes nothing | PASS | 8.4 a quarter carried |
| 84 | Doing the turnaround now costs $81M under Refineries this quarter; waiting costs nothing now, three points of plant condition, then $60M next quarter, or $210M if the plant breaks down first | PASS | now -81; later -60 / -210 |
| 85 | The world the portfolio is valued in is drawn from nine futures whose odds add up to one (carbon 30/45/25, demand 45/35/20); the likeliest is pricier carbon with a slow decline | PASS | mid:slow |
| 86 | A set of projects is valued in the drawn world as the sum of its projects' values there: Helix at Rotterdam plus wind plus the sale is worth -$330M if carbon stays cheap and demand holds, +$530M if carbon is dear and demand collapses | PASS | -330 / +530 |
| 87 | In the board quarter every page carries and the one-time answers do nothing new: the delayed turnaround's crews come ($60M), the raise stays, no new stoppage | PASS | 60; 8.4; 0 |
| 88 | Score order careful > average > careless in all fourteen quarters | PASS | Q1: 88.1 / 82.2 / 12.2; Q2: 89.2 / 80.8 / 11.2; Q3: 92.2 / 84.6 / 7.8; Q4: 92.3 / 86.1 / 7.7; Q5: 90.4 / 84.9 / 9.3; Q6: 89.1 / 88.7 / 8.6; Q7: 90.9 / 83.3 / 9.0; Q8: 91.0 / 79.4 / 8.9; Q9: 91.2 / 83.3 / 8.7; Q10: 94.4 / 68.2 / 9.0; Q11: 94.9 / 79.4 / 8.7; Q12: 95.0 / 78.4 / 8.7; Q13: 95.1 / 77.9 / 8.7; Q14: 95.1 / 72.4 / 8.7 |
| 89 | Fixtures rebuild identically | PASS | f32de9e9126eed08 |
