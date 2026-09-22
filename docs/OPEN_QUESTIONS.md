# Open Questions

Updated: 2026-09-22.

The authoritative markdown sources and flat Week 4 files are now present. Remaining open questions are tied to package defects, missing faculty/expected-output files, precision/rules not specified in the package, or later-stage product choices.

## Package-shape questions

- Should the flat Week 4 files in `C:\Users\sakas\Documents\Flexee-economics` be treated as authoritative despite the missing `halden-week4-data-package/` directory?
- Where is the Week 4-specific MANIFEST? The available `MANIFEST.md` describes Week 10.
- Should the CSVs be supplied under `data/` to match the notebook, or should the notebook be corrected to load flat CSV paths?
- Where is `halden-week10-data-package/`?
- Where are the Week 4 faculty solution workbook/notebook and expected-output files?

## Week 4 reference package questions

- What are the source-version identifiers for the flat CSV/workbook/notebook package?
- What are the exact expected-output files for the worked example and current-week faculty solution?
- What rounding mode applies to half-cent or three-decimal outputs such as Geneva `9.625/bbl`?
- What time basis applies to Geneva volume/P&L beyond the supplied `40,000 bbl/day` cap?
- What precision, bounds, and increment apply to custom/intermediate transfer prices?
- Which analytical outputs, if any, are submitted by students versus kept entirely in the external Excel/Python package?
- Are any Week 4 package outputs intended to become KPI values, score inputs, scores, or rank inputs?

## Week 4 -> Week 6 consequence questions

- What aggregation rule maps the cohort transfer-price distribution to disciplined/base/lax?
- What thresholds separate disciplined, base, and lax cohorts?
- Does the classification use mean transfer price, median, share near marginal-cost anchor, dispersion, custom-price penalty, or another measure?
- How are outliers or non-anchor custom prices treated?

## Faculty/stage questions

- What exact standing-state transitions does Week 4 create for Delacroix, upstream leadership, Kuhn/Geneva, and related counterparties?
- Which Week 4 causal trace fields are required for Stage 3 faculty views?
- What exact payload schema should the queued faculty LLM service assemble for Week 4?
- What are the prepared teaching moment trigger thresholds for bimodal, midpoint-clustered, or market-clustered transfer-price distributions?

## Later content/versioning questions

- How should role charter content versions be named and promoted?
- Is the Week 7 role rotation fixed or optional?
- What institution-level setting governs student-side LLM help for analytical work?
- Should the memo grading rubric be published to students on day one or discovered through feedback?
