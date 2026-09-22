# Open Questions

Updated: 2026-09-22.

The authoritative markdown sources are now present. Remaining open questions are tied to missing worked packages, precision/rules not specified in the markdown sources, or later-stage product choices.

## Missing packages

- Where is `halden-week4-data-package/`?
- Where is `halden-week10-data-package/`?
- For each package, where are the MANIFEST, canonical CSV files, XLSX workbook, Jupyter notebook, Python support files, faculty solution workbook/notebook, and expected-output files?

## Week 4 reference package questions

- What are the exact CSV schemas and source-version identifiers?
- What are the exact workbook and notebook formulas?
- What are the exact expected outputs for the worked example and current-week blank analysis?
- What rounding mode, decimal scale, and display format apply to per-barrel values, percentages, rates, and monthly Geneva arbitrage?
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
