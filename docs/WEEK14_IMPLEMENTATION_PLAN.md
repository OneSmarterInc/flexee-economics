# Week 14 Implementation Plan

Batch 31A does not implement this plan. It records the implementation boundary for a future Week 14 board-defense workflow.

## Status

READY WITH NON-BLOCKING QUESTIONS

The authoritative sources are sufficient to design a board-defense assessment workflow. They are not sufficient to automate numeric grading or exact scheduling without faculty/product decisions.

## Product Boundary

Week 14 is not a computational week.

There is no computational package for Week 14.

Implement:

- student board-defense preparation;
- final synthesis memo submission;
- faculty assessment workspace;
- history packet assembly;
- four-tier assessment classification;
- assessment record persistence;
- visibility and authorization boundaries.

Do not implement:

- economic engine;
- canonical data package;
- WeekExecutionService economic runtime;
- KPI or ranking calculations;
- standing changes;
- consequence links;
- automatic LLM grading.

## Suggested Future Workflow

1. Faculty opens Week 14.
2. Students review their own decision/memo history and prepare board-defense materials.
3. Teams submit final synthesis memo and optional presentation artifact.
4. Faculty reviews the history packet:
    - memo portfolio;
    - decisions and alternatives;
    - causal trace;
    - KPI/ranking history;
    - reasoning-versus-luck decomposition;
    - counterfactuals where supported.
5. Team presents strategic narrative.
6. Faculty conducts decision defense from the memo portfolio.
7. Faculty asks counterfactual/self-understanding questions.
8. Faculty records rubric judgments:
    - strategic coherence;
    - decision quality;
    - self-understanding.
9. Faculty records four-tier outcome/reasoning classification.
10. Faculty publishes only the approved student-facing assessment feedback.

## Data Model Candidates

These are candidate future models, not Batch 31A work:

- `BoardDefenseSubmission`
- `BoardDefenseArtifact`
- `FacultyAssessment`
- `FacultyAssessmentDimension`
- `AssessmentTier`
- `AssessmentFeedback`

Any model should preserve tenant, section, team, simulation version, week, assessor, timestamps, source snapshots, and immutable assessment history.

## Required History Packet

The Week 14 history packet should be assembled from persisted runtime records:

- decision submissions and alternatives not taken;
- memo submissions;
- Week 4 economic resolutions;
- Week 5/8/9/10/11/12/13 economic evaluations;
- Week 6 capital evaluations;
- KPI snapshots;
- ranking snapshots;
- standing state and history;
- consequence links;
- advisor consultation history;
- reasoning-versus-luck records;
- faculty what-if/counterfactual records where already authorized.

Missing evidence should be shown explicitly. Do not substitute defaults.

## Assessment UI Requirements

Student:

- sees current Week 14 deliverable requirements;
- sees own memo portfolio;
- sees own released historical results;
- submits final synthesis memo and defense artifact;
- cannot see faculty-only causal trace, peer data, or assessment notes before publication.

Faculty:

- sees all assigned-section teams;
- opens each team's history packet;
- records rubric dimension judgments;
- records tier classification;
- references KPI/ranking as context only;
- can launch causal trace and what-if tools.

Admin:

- same as faculty within tenant scope.

## Non-Blocking Questions

- What exact numeric grading scale should be used for strategic coherence, decision quality, and self-understanding?
- Are the three rubric dimensions equally weighted?
- What final synthesis memo length and file formats are allowed?
- Should student presentation files be uploaded, linked, or only recorded as delivered?
- When should final assessment feedback become visible to students?
- Should faculty record one final tier only, or both reasoning axis and outcome axis separately?
- What exact LLM synthesis prompt/version should be used if the assistant is added to this workflow?

## Recommended Next Batch

Implement Week 14 assessment workflow only after faculty/product decisions confirm the grading scale and publication rules. A safe next implementation batch would be:

`Batch 31B - Week 14 Assessment Data Model and Faculty Workspace`

That batch should still avoid economic computation.
