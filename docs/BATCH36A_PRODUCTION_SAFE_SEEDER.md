# Batch 36A Production-Safe Seeder

## Original Problem

`DatabaseSeeder` previously created demo accounts with the local development default credential path. That was acceptable for local development and CI, but unsafe for an internet-facing production database rebuild because demo users could be created without an explicitly supplied strong seed credential.

## Seeder Split

`DatabaseSeeder` now only orchestrates seeding in this order:

```text
ContentSeeder
    -> DemoAccountSeeder
```

The order is intentional: content and application configuration must exist before demo accounts, enrollments, teams, and runtime demo assignments are created.

## ContentSeeder

`ContentSeeder` owns application/content configuration, including:

- tenant, institution, course, sections, and seats;
- simulation, variants, versions, and weeks;
- package registration and activation;
- decision and memo definitions;
- KPI definitions and cohort response functions;
- capital projects and discount-rate schedules;
- seven-week variant content configuration.

It does not create users, enrollments, teams, team members, section faculty, team simulations, standing baselines, or runtime demo openings.

## DemoAccountSeeder

`DemoAccountSeeder` owns demo-user-dependent data, including:

- administrator, faculty, and student demo users;
- section faculty assignments;
- enrollments;
- teams and team members;
- seven-week pilot teams;
- section simulation assignment;
- seven-week standing baseline;
- initial demo runtime week openings.

## Production Credential Requirement

`DemoAccountSeeder` uses `SEED_DEMO_PASSWORD` when present. For non-production environments, the local development fallback remains available so existing local and CI workflows keep working.

For production, `DemoAccountSeeder` refuses to run unless `SEED_DEMO_PASSWORD` is explicitly supplied and is at least 16 characters long. It does not generate, log, or commit a credential.

## Tests

Focused seeder coverage verifies:

- production seeding fails without an explicit strong demo credential;
- production seeding succeeds with a supplied strong demo credential;
- the development fallback still creates locally usable demo accounts;
- the seven-week pilot seed still creates the pilot section simulation and teams;
- content seeding still creates the simulation variants, weeks, content activations, KPI definitions, cohort functions, decision definitions, and memo definitions without creating demo users.

## Production Rebuild Dependency

This batch prepares the seeder for a later production database rebuild. It does not rebuild production and does not deploy production.

```text
Production-safe seeder: VERIFIED
Production database rebuild: NOT YET RUN
Production deployment: NOT DEPLOYED
```
