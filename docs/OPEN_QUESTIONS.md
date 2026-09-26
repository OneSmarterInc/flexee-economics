# Open Questions

Updated: 2026-09-26.

The authoritative markdown sources and package roots for Weeks 1, 2, 3, 5, 6, 7, 8, 9, 10, 11, 12, and 13 are now present and registered through the content package framework. Week 4 remains the stable vertical-slice baseline. Week 4 and Week 6 have runtime paths; Week 8 has a package-backed Laravel economic engine, runtime evaluation persistence, and KPI/ranking integration for package-supported metrics. Week 9 now has a package-backed Laravel economic engine; runtime integration is still deferred.

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
- Which package-backed economic engine should receive runtime integration after Week 9 engine validation?

## Week 9 package questions

Resolved by the authoritative package:

- Week 9 manifest;
- canonical CSVs;
- student workbook and notebook;
- faculty solution workbook and notebook;
- golden fixture;
- provenance hashes;
- package validation report;
- package-backed Laravel Cordell rebrand economic engine;
- `fills_per_site_year = 180000` consumed from package data as a calibration lever, not hard-coded engine policy.

Still open:

- Which Week 9 student decision fields are submitted through the application versus kept in workbook/notebook analysis?
- What production source supplies the Week 7 to Week 9 cohort non-fuel state for runtime evaluation?
- Which Week 9 outputs should feed KPI snapshots after runtime economics are integrated?
- What standing/consequence rules connect Delacroix and dealer cooperation to Week 9 execution?

## Week 8 package questions

Resolved by the authoritative package:

- Week 8 manifest;
- canonical CSVs;
- student workbook and notebook;
- faculty solution workbook;
- golden fixture;
- provenance hashes;
- package validation script;
- package-backed Laravel OPEC/scenario economic engine;
- prediction distribution and realized scenario represented separately in the engine result.
- KPI snapshots populated from realized Week 8 evaluation where package-supported;
- `refining_net_margin_vs_benchmark` populated from realized refining crack versus package baseline;
- unsupported published KPIs remain unavailable with null values;
- Week 8 ranking snapshots remain incomplete until the full KPI basis exists.

Still open:

- Native Excel recalculation was not performed in Batch 18A; openpyxl structure/formula/error scans and cached values were validated instead.
- What production scenario-resolution source should set `realized_scenario_key` when it is not supplied by controlled runtime/test data?
- Which Week 8 student probability and posture fields are submitted through the application versus kept in the workbook/notebook package?

## Week 6 to Week 8 cohort response questions

Still open:

- What production response function maps Week 6 aggregate Gulf Coast capacity additions to Week 8 refining margin?
- What artifact contains the Window 2 anchor episode, real elasticity, pedagogical multiplier, bounds, and parallel-universe baseline?
- What project-to-capacity mapping should production use beyond the Batch 17A fixture?
- Should Week 7 capacity-response behavior compound the Week 6 Window 2 effect, and if so, where are the authoritative parameters?

Do not use Week 8 OPEC shock propagation coefficients to fill this gap. They are separate mechanics.
