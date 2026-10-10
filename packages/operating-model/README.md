# Halden Energy · Quarterly Operating Model · v0.6 (Quarters 1–10)

**What this is:** the economics engine for the quarterly play-through, for Quarters 1–10 (Q1 2027 to Q2 2029) plus the four history quarters of 2026. The Python model in `model/` is the reference. The Laravel engine has to reproduce `fixtures/golden_quarters.csv` (tolerance: relative 1e-6, absolute 1e-4).

**Status:** provisional calibration. 63 of 63 validation checks pass (`VALIDATION.md`).

---

## What happens in one quarter

1. **Oil fields.**
   - **Texas (Permian):** production last quarter shrinks 8%, then new wells from last quarter's rigs are added. 120,000 bbl/day go to Baton Rouge at the internal crude price. The rest sell at the wellhead price (WTI − $3.50).
   - **Norway operated:** pumps as planned or 10% less. Cutting saves only the variable 40% of lifting cost plus transport.
   - **Norway partner-run fields, Kessana, and gas and other** are fixed this early in the course.
2. **Refineries.**
   - **Baton Rouge** earns the Gulf Coast margin + $4.75 − $4.00 variable cost on what it runs, minus fixed cost on full capacity. It also gains (market crude price − internal price) × 120,000 bbl/day, and pays Geneva anything Geneva makes on the gap.
   - **Rotterdam:** run, pause or close. Pausing still pays fixed cost plus a $5M care cost, and restarting costs $85M. Closing costs $150M once and is permanent.
   - **Singapore:** Halden's 40%. Straits Pacific accepts requests between 80% and 95%.
3. **Geneva:** a $45M base desk, plus the Week 4 gap rule. Geneva gets 35% of (market − internal price) on up to 40,000 bbl/day. That's zero within 10% of the cost or market price, and zero at or above market.
4. **Gas stations.**
   - **Cordell:** four clusters. Changing what Cordell charges dealers moves the fuel margin cent for cent. Dealers pass part of it to drivers (the pass-through), and volume moves by the cluster's elasticity. Holding a price rise makes the elasticity grow 50% a quarter (up to 4 quarters). Holding a cut makes the gain shrink 50% a quarter as rivals match.
   - **Shop profit:** Halden keeps 36% of dealer shop margin ($0.42 per gallon).
   - **Europe:** Halden sets the pump price itself. Volume falls 1% a quarter.
5. **Head office:** $200M, plus advisor time at $0.12M per answer.
6. **Money.**
   - D&A is 1.5% of capital employed per quarter, and tax is 30% of (EBITDA − D&A).
   - Capital spending is $565M plus $40M per rig.
   - Free cash flow is EBITDA − tax − capital spending.
   - Net debt moves by −FCF + $1,750M paid to shareholders.
7. **From Quarter 5 (2028 on): currency.** Norway's lifting cost moves with the krone, Rotterdam and the European stations with the euro, Singapore with its dollar, each against fixed reference rates (1.085, 10.20, 1.34). A hedge set this quarter settles next quarter: crude at this quarter's WTI, euros and kroner at today's rates. A $300M euro forward sold at 1.085 before the shock settles in Q1 2028.
8. **From Quarter 6: big projects.** Going ahead costs the full outlay now (capital spending, in net debt) and pays a quarter of each year's forecast cash flow from the next quarter, cut to 88% (refining) or 61% (new business). The Rotterdam upgrade stops paying if Rotterdam is closed. The score's free cash flow adds the outlay back (decision S2).
9. **From Quarter 7: the rival's moves.** A rival cuts street prices 6c a gallon in every Cordell market. Where a team holds its price, drivers drift to the rival by the cluster's elasticity × 6c ÷ $3.20 (a fraction of a percent). Where it matches, Cordell gives up 6c on every gallon there. The rival also announces a Gulf Coast expansion; once it builds (Q4 2028 on), holding costs Halden $40M a year and matching $140M, under Refineries (`data/capacity_game.csv`).
10. **From Quarter 8: OPEC+.** The quarter opens at pre-decision prices (WTI $74, Gulf Coast margin $21.50). At the close the outcome is drawn (holds in full +$14, 35%; partly holds +$7, 40%; falls apart −$4, 25%) and WTI moves by it; the Gulf Coast margin compresses 35 cents a dollar; station volumes move by −0.05 × 0.60 × the pump change. Geneva buys crude for Baton Rouge ahead of the decision according to the case the team plans for (30, 15 or 0 days), gaining or losing the move and paying 8.5% a year on the money tied up.
11. **From Quarter 9: the rebrand.** Cordell's 4,300 stations sit in three regions by what the name is worth (`data/rebrand_markets.csv`): Louisiana and Mississippi (1,850 sites, the Cordell name adds 5.5 cents a fill), the Gulf Coast beyond the core (1,400, 1.5 cents) and the Southeast edge (1,050, where it costs half a cent). Putting the Halden name on a region costs $79,070 a site (a $340M programme), paid now as capital like a project, and from the next quarter earns (Halden's pull − Cordell's) × sites × 180,000 fills a year in the shop, scaled by the shop margin the class is living with (Window 3). The heartland loses $15M a year if rebranded; the edge earns $15M and pays back in 5.5 years. A rebrand is permanent.
12. **Quarter 10: the recession.** No new decision. The economy shrinks 3%; demand falls by product (gasoline 0.35 × 3%, diesel 0.85 ×, jet 1.60 ×), so each refinery's runs fall by its product mix (Baton Rouge −1.99%, Rotterdam −2.23%, Singapore −2.62%; `data/product_elasticities.csv`, `data/refinery_yields.csv`) and station volumes by the gasoline hit. Oil, every margin and the krone fall on the price path. Two threads from the team's own history bite: Marcus delivers only half of a requested Baton Rouge run cut if the Q4 2027 crude price left his refinery reporting above target (at cost, or well below market), and a Straits Pacific strained by repeated asks above 95% runs Singapore at 80% whatever Halden asks. The runner works both flags out from the team's record.
13. **Plant condition:** loses 0.5 points for each point Baton Rouge runs above 97%. It loses another 0.5 for each quarter Rotterdam runs below 80%.
8. **Score:** the seven published measures, weighted 30/15/15/10/10/10/10, each scaled 0–100 from the worst team to the best team in the section. Proposed change S1 applies (see below).

## What the reference teams show (Q4 2027)

| | Careful | Average | Careless |
| --- | --- | --- | --- |
| EBITDA | $3,786M | $3,782M | $3,624M |
| Free cash flow | $1,929M | $1,807M | $1,223M |
| Oil fields / Refineries (reported) | $1,914M / $1,685M | $2,537M / $1,067M | $2,135M / $1,320M |
| Score | 92.3 | 86.1 | 7.7 |
| Texas output | 137,370 bbl/day | 141,313 bbl/day | 145,255 bbl/day |

**What the numbers show:**
- **Same total earnings, different split.** Careful and Average earn almost the same EBITDA. The internal crude price moves about $600M a quarter from the oil fields to the refinery and changes nothing in total. This is the Week 4 lesson in the numbers.
- **Careful wins on cash.** Over four quarters it has $498M more free cash flow than Average, because it drills 11 rigs, not 14.
- **Careless pays twice.** It over-drills at 26 rigs, cuts Norway, wears out Baton Rouge, and pauses Rotterdam (which still pays its fixed costs). It is $2.29B behind Average in free cash flow after four quarters.

## Findings that change the design

1. **The old presidents over-drilled.** At early-2027 prices the 11th rig pays for itself ($42M of oil against a $40M rig), the 12th doesn't ($36M), and the 14th loses money ($24M). That's the Week 1 marginal-versus-average lesson, built into the numbers that students inherit.
2. **Decision S1 (approved by Vikram, 9 Oct): a smallest-difference-that-counts rule for the score.** The locked rule scales each measure from worst team to best team. When teams differ by pennies, a $20 gap in shop profit per station becomes a 100-point swing, and the better team ranked lower in Q4. The fix: if the spread between best and worst is smaller than a set amount, the scale is stretched to that amount around the middle. The amounts are $0.50 per barrel, 0.25 points of return on capital, $50M of free cash flow, $0.25/bbl of refining margin, $500 of shop profit per station, 0.05x of debt to earnings, and 2 points of plant condition. Weights and everything else stay as locked.
3. **"Profit per barrel" is a company number now.** It is EBITDA before head office ÷ total production, about $37 a barrel. The prototype's $76.75 was the Week 4 single-barrel chain margin. I'll update the prototype to the real figure.

## New constants (provisional, all in `data/constants.csv`)

These are the numbers the quarterly loop needed that the weekly design never fixed:
- Capacities: Baton Rouge 520k, Rotterdam 330k and Singapore 580k bbl/day.
- Upstream volumes: Norway 210k operated and 90k partner-run, Kessana 150k boe/day.
- Texas decline, rig productivity and rig cost.
- Station volumes and Halden's share of shop margin.
- Fixed costs, head office cost and payout to shareholders.

**What the whole class does (hidden until it lands).** Window 1: the class's average Q3 2027 Rotterdam run rate sets the Q1 2028 European margin, $4.60 + 9 × (0.75 − average), never below $2.60. Capital terms: each team's Q4 2027 crude price scores 1 at cost, 0.5 at market, 0 otherwise; a class average above two-thirds gives $1,520M at 6.5%, below one-third $950M at 11%, otherwise $1,150M at 8.5%. Window 2: the share of the class that went ahead with the Baton Rouge upgrade in Q2 2028 moves the Q4 2028 Gulf Coast margin, −6 × (share − 0.5) above the pivot and +3 × (0.5 − share) below it (−$3.00 to +$1.50). Window 3: the class's average Q3 2028 aggression (share of Cordell markets where a team matched the rival) sets the Cordell shop margin per gallon for all four quarters of 2029, $0.42 − 0.10 × (average − 0.5), within 15% of $0.42.

**Every constant from the ledger is used unchanged.** Gas and other EBITDA ($147M a quarter) is calibrated so Q4 2026 EBITDA = $3,750M. That matches the KPI package's opening state: return on capital 12.7% and debt to earnings 1.30x.

## Files

| Path | What |
| --- | --- |
| `data/` | Prices by quarter (with the rival's moves), constants, Cordell clusters, European countries, projects, capital terms by class behaviour, the capacity game, decisions and their on-screen labels |
| `model/halden_model.py` | The reference model |
| `model/run_reference.py` | Runs the three reference teams and writes the fixtures |
| `model/validate.py` | 63 checks; writes `VALIDATION.md` |
| `fixtures/golden_quarters.csv` | Every line, segment, money figure, measure, score and rank, by team and quarter |
| `fixtures/reference_decisions.csv`, `state_after_quarter.csv`, `calibration.json`, `class_effects.json` | Inputs, carried state and class effects for the engine tests |
| `halden-operating-model-summary.xlsx` | The same results laid out for reading |
| `MANIFEST.md` | SHA-256 for every file |

**To rebuild:** `cd model && python3 validate.py`

## Next

Quarter 11 is the Kessana hold-up: a one-time negotiating position. Quarter 12 replaces the project list with a four-bucket portfolio. Each arrives as a module and its fixtures, the same way.
