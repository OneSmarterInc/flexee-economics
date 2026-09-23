# Batch 10B Implementation

Batch 10B adds the Week 6 capital economics engine boundary. It does not implement NPV, IRR, project cash-flow economics, Week 6 faculty what-if tools, leaderboard effects, or downstream consequences.

## Reference Package Status

The repository was checked for authoritative Week 6 materials before implementing formulas. No Week 6 reference package, project cash-flow package, expected-output file, or golden Week 6 parity test was present.

The available local materials only define the framework dependency:

- Batch 10A capital allocation decisions preserve selected and rejected projects.
- Discount-rate consequences can provide the Week 6 discount rate and capital envelope.
- Existing documentation marks Week 6 NPV/IRR crossover and project economics as deferred/unresolved.

Because the canonical package is missing, Batch 10B records an explicit unavailable evaluation instead of inventing formulas or expected values.

## Architecture

```text
CapitalAllocationDecision
        |
        v
Week6CapitalEconomicsService
        |
        v
Week6CapitalEconomicsEngine
        |
        v
CapitalAllocationEvaluation
```

The engine identifier is:

```text
week6_capital_economics_v1_framework
```

The engine version is:

```text
week6_capital_economics_v1
```

## Reference Package Boundary

`Week6CapitalReferencePackage` represents whether canonical Week 6 economics inputs are available.

Current behavior:

- `isAvailable()` returns `false`
- the unavailable reason is stored with every evaluation
- no NPV/IRR calculation is attempted

Future Week 6 work should replace the missing package with a canonical package containing project cash flows, expected outputs, and golden-test fixtures.

## Stored Evaluation Model

`CapitalAllocationEvaluation` stores:

- tenant
- section simulation
- runtime week
- team simulation and team
- capital allocation decision reference
- engine identifier and version
- status
- nullable decimal-safe future output columns:
    - portfolio NPV in MUSD
    - portfolio IRR percent
    - capital required in MUSD
- nullable capital envelope feasibility
- input snapshot
- output snapshot
- unavailable reason
- actor/process information
- evaluation timestamp

Evaluations are immutable once created.

## Idempotency

The database enforces one evaluation per tenant, capital allocation decision, and engine identifier:

```text
tenant_id + capital_allocation_decision_id + engine_identifier
```

Repeated evaluation requests return the existing immutable evaluation.

## Downstream Safety

A Week 6 capital economics evaluation does not create or mutate:

- KPI snapshots
- ranking snapshots
- consequence links
- standing events
- economic resolutions

The evaluation is a calculation boundary only.

## Deferred

The following remain out of scope until the authoritative Week 6 package is supplied:

- project cash-flow ingestion
- NPV calculation
- IRR calculation
- discount-rate sensitivity golden tests
- capital-envelope feasibility calculation
- Week 6 faculty what-if console
- Week 6 KPI/ranking effects
- Week 6 downstream consequence propagation
