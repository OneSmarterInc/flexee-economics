# Batch 11B Implementation

Batch 11B connects validated simulation content packages to runtime week lookup. It does not ingest a Week 6 package, implement economics, generate artifacts, or add student/faculty download UI.

## Architecture

```text
SimulationContentPackage
        |
        v
SimulationContentActivation
        |
        v
SectionSimulationWeek
        |
        v
SimulationContentResolver
        |
        v
Authorized ContentArtifact list
```

Content packages remain platform-scoped. Runtime lookup happens through a tenant-owned `SectionSimulationWeek`, where authorization and artifact visibility are applied.

## Package Activation

`SimulationContentActivationService` activates one validated package for a simulation week and package type.

Activation requires:

- package status is `validated`
- package simulation version/week matches the activation target
- no other active package exists for the same simulation week and package type

Activations are immutable once created.

## Runtime Resolver

`SimulationContentResolver` is the boundary for runtime content lookup.

It provides:

- `activePackageFor()`
- `authorizedArtifactsFor()`

Controllers and UI components should use this service rather than querying `ContentArtifact` directly.

## Student and Faculty Visibility

Students can resolve only:

- `student`
- `shared`
- unscoped artifacts

Faculty and administrators can resolve:

- `student`
- `faculty`
- `solution`
- `shared`
- unscoped artifacts

Missing artifacts are never returned by the resolver.

## Tenant Isolation

The resolver authorizes through the tenant-owned runtime week:

- actor tenant must match runtime week tenant
- faculty must be assigned to the section
- students must have active enrollment in the section
- cross-tenant artifact lookup is rejected

## Deferred

The following remain out of scope:

- actual Week 6 package ingestion
- Week 5-14 content creation
- Python generators
- artifact generation pipelines
- student/faculty artifact download UI
- package activation replacement policy
- runtime engine consumption of package contents
