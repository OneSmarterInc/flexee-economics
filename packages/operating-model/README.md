# Halden Energy · Quarterly Operating Model · v0.1 (Quarters 1–4)

**What this is:** the economics engine for the quarterly play-through, for Quarters 1–4 (Q1–Q4 2027) plus the four history quarters of 2026. The Python model in `model/` is the reference. The Laravel engine has to reproduce `fixtures/golden_quarters.csv` (tolerance: relative 1e-6, absolute 1e-4).

**Status:** provisional calibration. 22 of 22 validation checks pass (`VALIDATION.md`).

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
7. **Plant condition:** loses 0.5 points for each point Baton Rouge runs above 97%. It loses another 0.5 for each quarter Rotterdam runs below 80%.
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

**Every constant from the ledger is used unchanged.** Gas and other EBITDA ($147M a quarter) is calibrated so Q4 2026 EBITDA = $3,750M. That matches the KPI package's opening state: return on capital 12.7% and debt to earnings 1.30x.

## Files

| Path | What |
| --- | --- |
| `data/` | Prices by quarter, constants, Cordell clusters, European countries, decisions and their on-screen labels |
| `model/halden_model.py` | The reference model |
| `model/run_reference.py` | Runs the three reference teams and writes the fixtures |
| `model/validate.py` | 22 checks; writes `VALIDATION.md` |
| `fixtures/golden_quarters.csv` | Every line, segment, money figure, measure, score and rank, by team and quarter |
| `fixtures/reference_decisions.csv`, `state_after_quarter.csv`, `calibration.json` | Inputs and carried state for the engine tests |
| `halden-operating-model-summary.xlsx` | The same results laid out for reading |
| `MANIFEST.md` | SHA-256 for every file |

**To rebuild:** `cd model && python3 validate.py`

## Next

Quarters 5–8 add currency and hedging, the capital projects and the cost-of-capital consequence, the rival's moves and cohort windows 2 and 3, and the OPEC outcome. Each arrives as a module and its fixtures, the same way.
