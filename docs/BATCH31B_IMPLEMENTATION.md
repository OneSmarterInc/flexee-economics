# Batch 31B Implementation

Batch 31B adds the Week 14 board-defense submission and faculty assessment workflow.

## Scope

Implemented:

- board-defense submission records;
- immutable submission revision history;
- faculty assessment records;
- rubric dimension records;
- assessment feedback publication records;
- student submission and view endpoints;
- faculty review, assessment, and publication endpoints;
- permission and publication tests.

Not implemented:

- automatic grading;
- AI grading;
- ranking-derived grades;
- economic recalculation;
- Week 14 economic engine;
- new KPI calculations;
- final polished UI.

## Workflow

```text
Team
  -> Board Defense Submission
  -> Faculty Review
  -> Rubric Assessment
  -> Feedback Record
  -> Student Published Assessment
```

The workflow is record-based. It does not run through `WeekExecutionService` and it does not create economic, KPI, ranking, standing, or consequence records.

## Models

Added:

- `BoardDefenseSubmission`
- `BoardDefenseSubmissionRevision`
- `BoardDefenseAssessment`
- `BoardDefenseAssessmentDimension`
- `BoardDefenseAssessmentFeedback`

The models preserve tenant, section simulation, runtime week, team simulation, team, actor, timestamps, and source snapshots where relevant.

## Submission Records

Students can save a draft and submit final Week 14 materials for their own team.

Submission stores:

- status;
- final synthesis memo;
- artifact references;
- submitted/updated actor;
- draft/submitted timestamps;
- lock version.

Supported artifact types:

- `board_presentation`
- `defense_material`

Submitted records are locked. Later mutation attempts fail.

## Assessment Records

Faculty/admin can assess teams only in authorized sections/tenants.

Assessment stores:

- reviewer;
- rubric version;
- status;
- optional four-tier classification;
- faculty private notes;
- history packet snapshot;
- completion timestamp.

Rubric dimensions are stored as qualitative records:

- strategic coherence;
- decision quality;
- self-understanding.

No score, points, weight, grade, or ranking-derived field exists.

## Feedback Publication

Feedback is stored separately from the assessment record.

Students see feedback only when:

- the feedback record exists;
- `is_published = true`.

Unpublished feedback and faculty private notes remain faculty-only.

## Student Visibility

Students can view:

- their own board-defense submission;
- their own final synthesis memo;
- their own published assessment;
- their own published feedback.

Students cannot view:

- other teams;
- faculty assessment workspace;
- faculty private notes;
- unpublished feedback.

## Faculty Capabilities

Faculty/admin can:

- view submitted defenses for assigned sections;
- inspect the assessment rubric dimensions;
- save rubric evaluations and comments;
- write feedback;
- publish feedback.

No automatic recommendations or grades are generated.

## Routes

Student:

- `GET student/week14/weeks/{sectionSimulationWeek}/defense`
- `POST student/week14/weeks/{sectionSimulationWeek}/defense/draft`
- `POST student/week14/weeks/{sectionSimulationWeek}/defense/submit`

Faculty:

- `GET faculty/week14/weeks/{sectionSimulationWeek}/teams/{teamSimulation}/assessment`
- `POST faculty/week14/weeks/{sectionSimulationWeek}/teams/{teamSimulation}/assessment`
- `POST faculty/week14/assessments/{boardDefenseAssessment}/publish`

## Deferred Grading Decisions

Still unresolved:

- numeric grading scale;
- dimension weights;
- publication timing/policy;
- final presentation artifact requirements;
- whether faculty should record one final tier or separate reasoning/outcome axes;
- future LLM synthesis prompt/version.

These remain in `docs/OPEN_QUESTIONS.md`.
