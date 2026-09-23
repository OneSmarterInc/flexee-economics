# Batch 7A Implementation

Batch 7A adds the advisor consultation framework. It stores advisor content and consultation history but does not connect advice to standing, consequences, scoring, faculty causal trace, or LLM behavior.

## Architecture

The advisor foundation is:

TeamSimulation
-> AdvisorConsultationSession
-> AdvisorResponse

`AdvisorConsultationService` owns advisor availability, slot enforcement, consultation creation, access checks, and student-safe view shaping.

## Advisor Catalog

`AdvisorCatalog` loads seven Halden advisors as versioned content records:

- Elena Marchetti — Chief Economist
- Danielle Roy — SVP Commercial
- Bjørn Aasen — SVP Operations
- Ana Ruiz — VP Finance
- Kwame Osei — Political Risk
- Margrethe Lund — Board Member
- Priya Venkatesan — Chief of Staff

Advisor records include key, name, title, perspective, default guidance, content version, active flag, and metadata.

## Consultation Records

`advisor_consultation_sessions` records:

- tenant
- section simulation
- runtime week
- team simulation and team
- advisor
- question
- context snapshot
- requesting actor
- timestamp

`advisor_responses` records:

- consultation session
- advisor
- content version
- response text
- response snapshot
- timestamp

Sessions and responses are immutable once created.

## Slot Rules

Each team has three advisor consultation slots per runtime week.

A repeated consultation with the same advisor in the same team/week returns the existing session and does not consume another slot.

Standing does not affect advisor access in Batch 7A.

## Student Visibility

The student-facing shape includes:

- advisor identity
- advisor perspective
- question
- response
- content version
- timestamps

It excludes hidden probabilities, future outcomes, faculty-only interpretations, and scoring signals.

## Deferred Features

The following remain intentionally out of scope:

- advisor-driven standing changes
- advisor-driven consequence links
- conflicting advice logic beyond static perspectives
- advisor slot variation by week or standing
- faculty causal trace
- memo review
- LLM-generated advice
