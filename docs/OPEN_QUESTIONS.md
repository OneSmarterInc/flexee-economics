# Open Questions

Updated: 2026-09-22.

The authoritative markdown sources and normalized Week 4 package are now present. The standalone Week 4 deterministic oracle is ready with non-blocking questions. Remaining questions are tied to app validation, later consequence rules, faculty/stage behavior, and product/content choices.

## Week 4 package questions

Resolved by the normalized package:

- Week 4-specific manifest and README;
- `data/` layout matching notebook execution;
- student workbook and student notebook;
- faculty solution workbook and faculty solution notebook;
- worked-example and current-week expected-output files;
- package source hashes and validation script.

Still open:

- What exact rounding mode should app displays use for half-cent or three-decimal outputs such as Geneva `9.625/bbl`?
- What time basis applies to Geneva volume/P&L beyond the supplied `40,000 bbl/day` cap?
- What precision, bounds, and increment apply to custom/intermediate transfer prices?
- Which analytical outputs, if any, are submitted by students versus kept entirely in the external Excel/Python package?
- Are any Week 4 package outputs intended to become KPI values, score inputs, scores, or rank inputs?

## Week 4 to Week 6 consequence questions

- What aggregation rule maps the cohort transfer-price distribution to disciplined/base/lax?
- What thresholds separate disciplined, base, and lax cohorts?
- Does the classification use mean transfer price, median, share near marginal-cost anchor, dispersion, custom-price penalty, or another measure?
- How are outliers or non-anchor custom prices treated?

## Faculty/stage questions

- What exact standing-state transitions does Week 4 create for Delacroix, upstream leadership, Kuhn/Geneva, and related counterparties?
- Which Week 4 causal trace fields are required for Stage 3 faculty views beyond the deterministic package outputs?
- What exact payload schema should the queued faculty LLM service assemble for Week 4?
- What are the prepared teaching moment trigger thresholds for bimodal, midpoint-clustered, or market-clustered transfer-price distributions?

## Later content/versioning questions

- How should role charter content versions be named and promoted?
- Is the Week 7 role rotation fixed or optional?
- What institution-level setting governs student-side LLM help for analytical work?
- Should the memo grading rubric be published to students on day one or discovered through feedback?
