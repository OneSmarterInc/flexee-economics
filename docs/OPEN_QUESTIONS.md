# Open Questions

These questions are unsupported by the files currently available. They should be resolved from the missing project materials before implementation, not answered by inference.

## Missing source files

- Where are `halden-development-instructions.md`, `halden-week4-data-package/`, `halden-week10-data-package/`, `halden-week4-data-package-spec.md`, `halden-energy-role-charters.md`, `halden-faculty-teaching-guide.md`, `halden-student-guide.md`, and `halden-calibration-review.md`?
- Should the current empty Laravel repository be initialized here at `C:\Users\sakas\Documents\ChatGPT\Flexee-economics`, or should development happen in `C:\Users\sakas\Documents\Flexee-economics`?

## Week 4 specification questions

- What are the exact Week 4 decision fields, allowed options, validation constraints, and submission rules?
- What are the exact Week 4 dataset files and student-visible package contents?
- What is the exact Week 4 memo prompt, expected structure, and rubric?
- Are multiple decision forms or multiple memo prompts required for a single runtime week?
- Are final submissions ever reopenable, and if so who can reopen them and what audit trail is required?
- Can specific Halden seats edit only specific decision fields, or can any team member edit every team field?
- What KPIs must Week 4 produce?
- What score components, weights, thresholds, rounding rules, and tie rules apply?
- What faculty-only causal trace or review information is required?
- What cross-section comparison is required in Stage 1, and what visibility limits apply?

## Architecture questions

- Does the development instruction require a specific Laravel version, starter kit, tenancy package, queue driver, auth package, or deployment target?
- Should users be modeled with role-specific profile tables, or with enrollments and scoped roles only?
- Are faculty assignments course-level, section-level, tenant-level, or a combination?
- What queue backend and database engine should be used for Stage 1?
- What exact queued LLM integration pattern is required?

## Content/versioning questions

- What is the required package format for generated Python artifacts?
- What precision tolerance is permitted for golden economic regression tests?
- How should content package versions be named and promoted from draft to active?
- Are constants stored directly in the database, in versioned JSON, or imported from package manifests?
