# Batch 33C Implementation

Batch 33C exposes the authoritative seven-week pilot as a first-class seeded experience.

## Baseline

Starting checkpoint:

```text
dd35222215e4ab037fdf7f9d0afb22a4a67b001e
test: validate final post-integration regression
```

Batch 33C does not change economic formulas, cohort windows, KPI calculations, consequence rules, standing rules, Week 14 assessment logic, grading, or LLM behavior.

## Seven-Week Variant

The seeded pilot uses the authoritative compressed sequence:

```text
1 -> 4 -> 6 -> 8 -> 10 -> 12 -> 14
```

The variant is seeded as:

- variant: `Seven-Week Variant`
- version: `2026-seven-week-pilot`
- section simulation: `Seven-Week Pilot Halden Energy`
- teams: `Team Alpha`, `Team Beta`
- students: five per team
- open week at reset: Week 1
- future weeks: draft/upcoming

## Role Rotation

The student dashboard now explains the seven-week role cadence:

- first seat: Weeks 1, 4, 6, and 8
- second seat: Weeks 10, 12, and 14

The current assigned seat is displayed for the signed-in student.

## Student Experience

The student dashboard now shows:

- the seven-week variant label;
- the pilot sequence;
- only Weeks 1, 4, 6, 8, 10, 12, and 14;
- active/current week state;
- upcoming future weeks;
- Week 14 Board Defense note;
- student-safe artifact labels.

The submission workspace also uses friendly artifact labels rather than raw package keys.

## Faculty Experience

The faculty operations dashboard and week control show:

- the selected section's simulation variant;
- the version;
- the seven-week pilot path;
- only the seven pilot weeks for the seven-week section.

The flagship section remains available separately.

## Artifact Visibility

Students continue to see student/shared package materials only.

Student views now explicitly exclude authoritative validation/provenance/golden-result artifacts:

- `validation_report`
- `provenance`
- `expected_outputs`

Faculty/admin access to package validation and solution materials is unchanged.

## Cohort Window Boundary

The seven-week variant excludes full-arc cohort windows:

- Window 1
- Window 2
- Window 3

It retains the documented Week 4 -> Week 6 discount-rate/capital-capacity path.

No cohort aggregates or cohort feedback effects are created by the seed.

## Week 14 Boundary

Week 14 remains an assessment workflow, not an economic engine.

The seven-week student dashboard identifies Week 14 as the Board Defense endpoint, but Batch 33C does not change grading or assessment logic.

## Browser Rehearsal Notes

The seven-week pilot was inspected through the local browser UI at:

```text
http://127.0.0.1:8000
```

Confirmed:

- faculty dashboard identifies `Seven-Week Variant 2026-seven-week-pilot`;
- faculty week control lists the seven-week pilot path;
- student dashboard shows the seven-week sequence and role rotation;
- student materials show course-friendly labels;
- student workspace accepts Week 1 decision and memo submissions;
- faculty/student boundaries remain distinct.

Mobile layout uses the existing responsive cards and grids. A manual phone-size pass is still recommended before a live class.

## Tests

Focused Batch 33C tests cover:

- seeded seven-week pilot sequence;
- two teams and ten pilot students;
- student dashboard visibility and role rotation;
- student workspace artifact labels;
- faculty dashboard/week-control variant exposure;
- exclusion of full-arc cohort windows.

Final verification:

- seven-week/student/faculty focused regression: `26 tests / 408 assertions`;
- core runtime/cohort regression: `23 tests / 424 assertions`;
- full Laravel suite: `457 tests / 3,228 assertions`;
- Composer validate: passed;
- Pint/lint: passed;
- PHPStan: `0 errors`;
- frontend check/types/build: passed.

## Deferred

Deferred intentionally:

- new economics;
- new cohort response functions;
- KPI/ranking changes;
- consequence mappings;
- standing changes;
- Week 14 grading automation;
- LLM interpretation;
- flagship pacing changes.
