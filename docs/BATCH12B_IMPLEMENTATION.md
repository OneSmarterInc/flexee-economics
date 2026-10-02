# Batch 12B Implementation

Batch 12B adds the simulation week execution orchestration framework. It does not implement new week economics, new cohort response functions, publication rules, or UI.

## Architecture

```text
SectionSimulationWeek
        |
        v
WeekExecutionService
        |
        v
WeekExecutionRecord
```

The execution service is the single boundary for ordered week execution. Controllers and UI should not call the individual resolution, KPI, ranking, consequence, and content services directly when executing a week.

## Execution Pipeline

The current ordered pipeline is:

```text
validate_content_package
lock_submissions
resolve_decisions
apply_kpi_calculations
calculate_rankings
generate_consequences
apply_cohort_effects
publish_allowed_outputs
```

Supported mechanics are executed. Unsupported future-week mechanics are recorded as `deferred` rather than invented.

## Content Validation First

Execution starts by resolving the active content package through `SimulationContentResolver`.

If no active valid package exists, execution fails before decisions are resolved.

## Week 4 Support

For Week 4, the service can currently:

- count submitted decisions
- resolve submitted decisions
- create economic resolutions
- populate KPI snapshots
- calculate ranking snapshots

Week 4 consequence creation remains owned by the existing Week 4 resolution flow.

## Execution Records

`WeekExecutionRecord` stores:

- tenant
- section simulation
- runtime week
- simulation week
- execution version
- status
- ordered step results
- outputs
- failure message
- actor
- started/completed/failed timestamps

The database enforces one execution per:

```text
tenant_id + section_simulation_week_id + execution_version
```

This prevents accidental duplicate execution.

## Partial Failure

If a later step fails, the record is marked `failed` and preserves all previously completed step history. The service does not silently mark the week complete after a partial failure.

## Deferred

The following remain out of scope:

- Week 6 economics
- Week 6 what-if execution
- configured cohort response execution
- publication/visibility rules
- replacement or rerun policy for failed executions
- asynchronous job orchestration
- UI for execution monitoring
