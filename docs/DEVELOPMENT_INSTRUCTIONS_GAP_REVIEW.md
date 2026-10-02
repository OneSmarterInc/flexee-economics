# Development Instructions Gap Review

Inventory date: 2026-09-22.

Authoritative source read completely:

- `C:\Users\sakas\Documents\Flexee-economics\halden-development-instructions.md`

## Summary

The current platform remains directionally aligned with the authoritative build sequence: Laravel first, platform spine before content, generic decision/memo submissions before Week 4 economics. The main open gaps are expected Stage 2/3/later features, not defects in Batches 1-3. Week 4 runtime economics is still blocked by the missing worked reference package.

## Requirement classification

| Requirement           | Authoritative expectation                                                                                                                                                        | Current Batch 1-3 status                                                                                                 | Classification                                               | Notes                                                                                                                           |
| --------------------- | -------------------------------------------------------------------------------------------------------------------------------------------------------------------------------- | ------------------------------------------------------------------------------------------------------------------------ | ------------------------------------------------------------ | ------------------------------------------------------------------------------------------------------------------------------- |
| Laravel platform      | The platform is Laravel; economic runtime is application logic.                                                                                                                  | Laravel app exists with simulation lifecycle and submission framework.                                                   | Satisfied for current stage                                  | Batch 4 must keep economics in PHP/Laravel domain services.                                                                     |
| Student frontend      | Student-facing interfaces use Inertia with Vue.                                                                                                                                  | Student dashboard and submission UI use Inertia/Vue.                                                                     | Satisfied for current stage                                  | Week 4 student screen should continue this path.                                                                                |
| Faculty frontend      | Faculty-facing interfaces use Livewire.                                                                                                                                          | Faculty lifecycle/status foundations use Livewire.                                                                       | Satisfied for current stage                                  | Cohort view, causal trace, what-if, and weekly brief remain Stage 3.                                                            |
| Python boundary       | Python is offline only for synthetic path generation and response-function calibration.                                                                                          | No runtime Python engine exists.                                                                                         | Satisfied as a boundary; not implemented as content pipeline | Do not add Python request-time economics. Python fixtures are future content-build artifacts.                                   |
| Laravel/PHP runtime   | PHP owns decision processing, scoring, standing, consequence propagation, runtime response-function application, and leaderboard/ranking.                                        | Decision processing exists generically; scoring, standing, propagation, response application, leaderboard remain future. | Partially satisfied                                          | Batch 4 should add PHP deterministic Week 4 calculation only after package gate; later stages add scoring/standing/leaderboard. |
| LLM integration       | Dedicated service builds payload, queued job calls the API, controllers do not call the API directly.                                                                            | No LLM integration exists.                                                                                               | Later-stage requirement                                      | Implement once as a shared queued pattern before faculty/student LLM assist.                                                    |
| Multi-tenancy         | Teams belong to sections; sections belong to installations; rankings scope to section or installation. Pacing varies by section; magnitudes/sequence are installation constants. | Tenant/section/team foundations exist.                                                                                   | Partially satisfied                                          | Ranking comparability and immutable installation-level magnitudes are future scoring concerns.                                  |
| Decision history      | Decisions must preserve alternatives not taken.                                                                                                                                  | Generic submissions store structured decision payloads and revisions.                                                    | Partially satisfied                                          | Week definitions must explicitly persist available alternatives, not just selected value.                                       |
| Standing              | Qualitative states, reason strings, and history; never numeric to students.                                                                                                      | Not yet implemented.                                                                                                     | Stage 2 requirement                                          | Model must support named counterparties and historical reasons.                                                                 |
| Cohort secrecy        | No team decisions or submission status leakage during open windows.                                                                                                              | Submission flow can be scoped by team.                                                                                   | Partially satisfied                                          | Faculty/status UI must avoid peer counters such as `3 of 8 submitted` during open windows.                                      |
| Prediction vs outcome | Store predictions separately from realized outcomes where required.                                                                                                              | No prediction/outcome module yet.                                                                                        | Later-stage requirement                                      | Required for Weeks 8, 10, and Week 14 reasoning-versus-luck assessment.                                                         |
| Data packages         | Canonical CSVs are source of truth; Excel and notebook load the same CSVs; faculty solution sets ship alongside student packages.                                                | No package ingestion yet.                                                                                                | Not implemented yet                                          | Blocked by missing worked package.                                                                                              |
| Build sequence        | Stage 1 spine, Stage 2 sim mechanics, Stage 3 faculty layer, Stage 4 content build-out, Stage 5 compressed variant.                                                              | Batches 1-3 build the spine and generic submission framework.                                                            | Satisfied for current stage                                  | Continue not adding content/economics before Week 4 package reconciliation.                                                     |

## Student/faculty guide gap check

| Feature                                                    | Source expectation                                     | Stage classification                                        |
| ---------------------------------------------------------- | ------------------------------------------------------ | ----------------------------------------------------------- |
| Advisor consultations with three slots from seven advisors | Student guide and design v2                            | Stage 2                                                     |
| Standing panel with qualitative reasons/history            | Development instructions, faculty guide, design v2     | Stage 2                                                     |
| Weekly rank and seven-KPI leaderboard                      | Student guide, faculty guide, design v2                | Stage 1 scoring extension / Stage 2 publication             |
| Causal trace                                               | Development instructions, faculty guide, design v2     | Stage 3                                                     |
| What-if console                                            | Faculty guide, design v2                               | Stage 3                                                     |
| Interpretive assistant                                     | Faculty guide, design v2                               | Stage 3 LLM                                                 |
| Memo portfolio                                             | Development instructions, faculty guide, design v2     | Stage 2/3, already structurally seeded by Batch 3 revisions |
| Week 14 board defense support                              | Student guide, faculty guide, design v2                | Later-stage arc completion                                  |
| Role charters per seat                                     | Development instructions, role charters, student guide | Stage 2 content integration                                 |

## Role charter integration

The five role charters should be loaded later as versioned content records keyed to simulation content version and seat:

- Executive Vice President, Integrated Operations
- Upstream Head
- Refining Head
- Downstream and Retail Head
- Trading and Finance Head

Do not encode charter prose directly into PHP classes. Runtime code should select the active content version and show the appropriate charter to the student occupying that seat, including after role rotation.

## Calibration status

The authoritative values currently used for development are provisional design-draft calibration values from the ledger/spec. The calibration review says final launch needs a verification-and-refresh pass against current market sources, but the structural relationships must be preserved. Development should therefore use the supplied design calibration now and avoid market refresh work during Batch 4.

Load-bearing relationships called out by the calibration review include:

- transfer price splits a fixed integrated margin pie
- disciplined Week 4 behavior must place Week 6 discount rate below the NPV/IRR crossover while lax behavior lands above it
- Rotterdam must remain net-negative while covering variable cost
- product income elasticity ordering remains jet > diesel > gasoline
- Week 8 crack-crude coefficient must preserve upstream/refining conflict

## Decision

Development-instruction readiness: partially ready for architecture planning, blocked for Week 4 economic implementation until `halden-week4-data-package/` is supplied and reconciled.
