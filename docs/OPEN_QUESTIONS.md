# Open Questions

Updated: 2026-09-28.

The authoritative markdown sources and package roots for Weeks 1, 2, 3, 5, 6, 7, 8, 9, 10, 11, 12, and 13 are now present and registered through the content package framework. Week 4 remains the stable vertical-slice baseline. Week 1 now has a package-backed asset-register economic engine and runtime evaluation persistence. Week 4 and Week 6 have runtime paths; Week 8 has a package-backed Laravel economic engine, runtime evaluation persistence, KPI/ranking integration for package-supported metrics, and Window 2 Week 6 -> Week 8 cohort adjustment support from `halden-window2-cohort-addendum/`. Week 9 now has a package-backed Laravel economic engine and runtime evaluation persistence. Week 10 now has a package-backed convergence economic engine, runtime state assembly, execution integration, and KPI/ranking integration with unsupported KPIs preserved as unavailable/null. Week 11 now has a package-backed Kessana hold-up economic engine, golden tests, and runtime evaluation persistence. Week 12 has package-backed runtime evaluation. Week 13 now has a package-backed factor-markets economic engine, golden tests, and runtime evaluation persistence.

## Week 1 package questions

Resolved by the authoritative package and Week 1 runtime:

- Week 1 manifest;
- canonical CSVs;
- student workbook and notebook;
- faculty solution workbook and notebook;
- golden fixture;
- provenance hashes;
- package validation report;
- package-backed Laravel asset-register economic engine;
- economic-versus-reported ranking calculations;
- Rotterdam contribution/net/shutdown-crack calculation;
- Norwegian tax-shield calculation;
- worked-example parity;
- runtime evaluation persistence through `Week1EconomicEvaluation`;
- package-backed Week 1 execution through `WeekExecutionService`;
- student resolved-status visibility from persisted Week 1 evaluations;
- seven-week variant compatibility using the same Week 1 economics package.

Still open:

- Which Week 1 outputs, if any, should become published KPI values?
- What standing transitions, if any, should the first-meeting choice create?
- What consequence links should Week 1 create, if a future authoritative consequence table supplies target state, timing, and future consumer?

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
- Which package-backed economic engine should receive implementation after Week 9 runtime validation?

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
- runtime evaluation persistence through `Week9EconomicEvaluation`;
- `fills_per_site_year = 180000` consumed from package data as a calibration lever, not hard-coded engine policy.

Still open:

- What production source supplies the Week 7 to Week 9 cohort non-fuel state for runtime evaluation?
- Which Week 9 outputs should feed KPI snapshots after runtime economics are integrated?
- What standing/consequence rules connect Delacroix and dealer cooperation to Week 9 execution?

## Week 10 package questions

Resolved by the authoritative package, Batch 20B engine, and subsequent runtime/scoring integration:

- Week 10 manifest;
- canonical CSVs;
- student workbook and notebook;
- faculty solution workbook and notebook;
- golden fixture;
- provenance hashes;
- package validation report;
- package-backed Laravel convergence economic engine;
- demand impacts by product;
- refinery-specific recession impacts;
- binding-constraint rules for the two reference fixture teams;
- runtime assembly of `cancellable_capex_musd` from Week 6 capital-allocation evaluation history;
- runtime assembly of `crude_hedge_coverage` from persisted Week 5 economic evaluation history;
- runtime assembly of `br_reported_margin_strong` from Week 4 economic resolution history;
- runtime assembly of `straits_pacific_standing` from standing state;
- runtime assembly of `cash_cushion_musd` from Week 8 economic evaluation history;
- Week 10 KPI snapshots created from calculated evaluations, with unsupported KPI values preserved as unavailable/null;
- Week 10 ranking snapshots created as incomplete while the full KPI basis is unavailable.

Still open:

- Which standing states beyond package-defined `strained` and `hostile`, if any, should block Singapore flexibility in production content?
- Which Week 10 convergence outputs, if any, should become available published KPI values beyond the current unavailable/null snapshots?
- What consequence links should Week 10 create after the runtime engine exists?

## Week 11 package questions

Resolved by the authoritative package, Batch 24A engine, and Batch 24B runtime integration:

- Week 11 manifest;
- canonical CSVs;
- student workbook and notebook;
- faculty solution workbook and notebook;
- golden fixture;
- provenance hashes;
- package validation report;
- package-backed Laravel Kessana hold-up economic engine;
- stay-versus-exit PV calculation across the take grid;
- indifference take;
- comparable fiscal-term range;
- sunk-capital invariance in the forward decision calculation;
- runtime evaluation persistence through `Week11EconomicEvaluation`;
- package-backed Week 11 execution through `WeekExecutionService`;
- student resolved-status visibility from persisted Week 11 evaluations;
- Week 11 KPI snapshots created from calculated evaluations, with unsupported KPI values preserved as unavailable/null;
- Week 11 ranking snapshots created as incomplete while the full KPI basis is unavailable.
- Batch 27A consequence review completed: no authoritative Week 11 consequence links or standing transitions are currently defined.

Still open:

- What persisted production source supplies Week 11 Tetteh standing at runtime?
- What persisted production source supplies Week 6 Kessana capital exposure at runtime?
- What standing transitions should Week 11 create for Tetteh or related counterparties?
- What consequence links should Week 11 create after runtime evaluation, if a future authoritative consequence-propagation table supplies target state, timing, and future consumer?
- Which Week 11 outputs, if any, should become available published KPI values beyond the current unavailable/null snapshots?

## Week 12 package questions

Resolved by the authoritative package readiness gate:

- Week 12 manifest;
- canonical CSVs;
- student workbook and notebook;
- faculty solution workbook and notebook;
- golden fixture;
- provenance hashes;
- package validation report;
- Helix Rotterdam `$1,200M` cost reconciled with revised adjacent-transition ceiling of `$1,200M`;
- Euro retail divestment proceeds reconciled at `$550M`;
- Helix Rotterdam plus offshore wind interlock validated at `$1,750M`;
- feasible portfolio counts and ordering assertions pinned by `fixtures/week12_golden.json`;
- Week 12 classified as ready for a package-backed transition-portfolio economic engine;
- package-backed Laravel transition-portfolio economic engine;
- runtime evaluation persistence through `Week12EconomicEvaluation`;
- package-backed Week 12 execution through `WeekExecutionService`;
- student resolved-status visibility from persisted Week 12 evaluations;
- project scenario NPV calculations;
- portfolio feasibility enumeration;
- Helix/divestment interlock behavior;
- worked-example parity.
- Batch 27A consequence review completed: no authoritative Week 12 consequence links or standing transitions are currently defined.

Still open:

- Which Week 12 outputs, if any, should become published KPI values after the economic engine exists?
- What standing transitions should Week 12 create for transition portfolio choices?
- What consequence links should Week 12 create after runtime evaluation, if a future authoritative consequence-propagation table supplies target state, timing, and future consumer?

## Week 13 package questions

Resolved by the authoritative package readiness gate and Batch 28B engine:

- Week 13 manifest;
- canonical CSVs;
- student workbook and notebook;
- faculty solution workbook and notebook;
- golden fixture;
- provenance hashes;
- package validation report;
- Norwegian tax-shield wage-cost parameters;
- Permian MRP/wage parameters;
- turnaround peak/delay parameters;
- asset-health penalty value as package data;
- Week 13 classified as ready for a package-backed factor-market economic engine;
- package-backed Laravel factor-markets economic engine;
- golden fixture parity for Norway tax shield, Permian MRP, turnaround timing tradeoff, and asset-health penalty output;
- worked-example parity;
- deterministic decimal-safe calculation snapshots;
- runtime evaluation persistence through `Week13EconomicEvaluation`;
- package-backed Week 13 execution through `WeekExecutionService`;
- student resolved-status visibility from persisted Week 13 evaluations.

Still open:

- What final Week 13 decision fields should replace or extend the current generic runtime field once authored UI content exists?
- Which Week 13 outputs, if any, should become published KPI values after the asset-health mechanic exists?
- What standing transitions should Week 13 create for Norwegian union or labor-market decisions?
- What consequence links should Week 13 create after runtime evaluation, if a future authoritative consequence-propagation table supplies target state, timing, and future consumer?
- How should the package-defined `asset_health_penalty_pts` connect to a future asset-health state model?

## Week 14 board-defense questions

Resolved by the authoritative Week 14 readiness gate:

- Week 14 has no computational data package by design.
- Week 14 is a board-defense and assessment workflow, not an economic engine.
- Student deliverables are a board presentation/defense and final synthesis memo.
- The faculty assessment dimensions are strategic coherence, decision quality, and self-understanding.
- The four-tier model separates reasoning quality from leaderboard/KPI outcomes.
- The workflow consumes the complete simulation history, including decisions, alternatives, memos, KPI/ranking history, standing, causal trace, reasoning-versus-luck records, and counterfactuals where available.
- Week 14 submission and assessment records exist for board-defense materials, qualitative rubric capture, feedback, and student-visible published assessment.
- Submitted board-defense records are locked through submission revision history.
- Assessment feedback publication is explicit; unpublished feedback and faculty private notes remain hidden from students.
- No automatic score, points, weight, grade, ranking-to-grade conversion, or Week 14 economic runtime exists.

Still open:

- What numeric grading scale should be used for the three Week 14 assessment dimensions?
- Are strategic coherence, decision quality, and self-understanding equally weighted?
- What final synthesis memo length, file format, and submission deadline should be enforced?
- Should presentation artifacts be uploaded, linked, or recorded only as delivered?
- When should final assessment feedback become visible to students?
- Should faculty record a single final tier, or separately record reasoning-axis and outcome-axis positions?
- What prompt version and stored-result policy should govern any future Week 14 LLM synthesis?

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

Resolved by `halden-window2-cohort-addendum/`:

- production response-function registration;
- share-of-teams Baton Rouge funding metric;
- pivot share `0.50`;
- asymmetric slopes `-6.0` and `+3.0`;
- canonical states `all_add`, `most_add`, `split`, `few_add`, and `none_add`;
- Week 8 integration rule preserving separate OPEC and cohort contributions;
- golden outputs and provenance hashes.

Still open:

- What documented anchor episode and real elasticity should be attached in the future market-data refresh?
- Should Week 7 capacity-response behavior compound the Week 6 Window 2 effect, and if so, where are the authoritative parameters?

Do not reuse the Week 8 OPEC `-0.35` coefficient as a capacity-response function. It remains only one term in the Week 8 OPEC shock propagation formula.

## Week 3 and Week 7 runtime questions

Resolved by the authoritative Week 3 and Week 7 package-backed runtime activation:

- Week 3 shutdown-point economic engine and immutable runtime evaluation exist.
- Week 7 competitive-response economic engine and immutable runtime evaluation exist.
- Window 1 uses Week 3 average European utilization to produce a Week 5 NWE crack handoff through `CohortFeedbackService`.
- Window 3 uses Week 7 average retail pricing aggression to produce a Week 9 non-fuel margin handoff through `CohortFeedbackService`.
- Window 1 and Window 3 remain excluded from seven-week variants.
- Week 5 and Week 9 evaluations preserve the relevant cohort handoff snapshots when available.

Still open:

- What final authored student decision fields should replace the current minimal Week 3 and Week 7 runtime fields?
- Which Week 3 or Week 7 economic outputs, if any, should become published KPI values?
- Which Week 3 or Week 7 decisions should create non-cohort consequence links, if future authoritative mappings define target state and timing?
