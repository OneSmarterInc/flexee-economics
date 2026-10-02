# Week 8 Implementation Plan

This plan maps the validated Week 8 reference package into Laravel runtime economics. Batch 18B implements the standalone package-backed economic engine; runtime submission/resolution integration remains future work.

## Intended Runtime Boundary

```text
Week 8 Decision Submission
    -> Week 8 Resolution Service
    -> Week 8 OPEC Scenario Engine
    -> Week 8 Economic Resolution
```

Controllers and UI should not call the engine directly.

## Implemented Engine Inputs

Batch 18B consumes package inputs:

- baseline state:
    - WTI pre-shock;
    - Brent pre-shock;
    - Gulf Coast crack base;
    - pump base;
- OPEC scenarios:
    - scenario key;
    - sim probability;
    - resolved WTI;
    - delta WTI;
- propagation coefficients:
    - upstream realization;
    - refining crack;
    - retail pass-through;
    - retail demand elasticity;
- compliance history;
- worked-example prior inputs.

Future team decision inputs:

- team probability estimate by scenario;
- upstream production posture;
- refining run-rate posture;
- hedge posture;
- memo reasoning.

Future prior state inputs:

- Week 6 balance-sheet/capital-allocation context where available;
- hedge mandate / standing when implemented;
- unresolved Week 6 -> Week 8 cohort response, if later supplied.

## Implemented Engine Outputs

Keep prediction and realized outcome separate.

Batch 18B implements:

- package probability distribution;
- optional team prediction probability distribution;
- optional realized scenario;
- expected WTI;
- expected upstream impact;
- expected refining crack;
- scenario-level upstream/refining/retail outputs;
- input/output snapshots;
- engine identifier/version.

Future prediction outputs:

- team probability estimate;
- expected WTI under team probabilities;
- expected upstream impact;
- expected refining crack;
- expected retail volume effect;
- consistency flags between probability estimate and operating posture.

Future scenario outputs once persistence/submission integration exists:

- resolved scenario;
- realized WTI;
- realized upstream impact;
- realized refining crack;
- realized retail volume effect.

Interpretive/faculty outputs:

- reasoning-versus-luck classification inputs;
- segment conflict evidence;
- decision/memo consistency signals.

## Golden Fixture Mapping

`fixtures/week8_golden.json` currently provides:

- package-level coefficients;
- sim-weight expected WTI;
- per-scenario propagation outputs;
- ordering assertions.

It does not provide team-specific decision outcomes because those depend on submitted student probabilities and operating choices.

## Week 6 -> Week 8 Cohort Feedback Separation

The current Week 8 package does not provide the missing production parameters for:

```text
Week 6 aggregate Gulf Coast capacity additions -> Week 8 refining margin
```

The Week 8 package's `refining_crack = -0.35` coefficient is an OPEC shock propagation coefficient. It must not be used as the cohort Window 2 response function.

## Deferred After Batch 18B

- scenario resolver;
- persistence model;
- KPI/ranking hooks;
- consequence links;
- what-if console;
- student/faculty runtime UI;
- Week 10 consequence propagation.
