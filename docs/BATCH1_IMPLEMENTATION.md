# Batch 1 Implementation

## Summary

Batch 1 establishes a functioning Laravel foundation for the Halden Energy simulation without implementing Week 4 economics or later simulation lifecycle work.

Implemented:

- Laravel 13 project scaffold in the repository root.
- Inertia/Vue student-facing starter shell.
- Livewire installed for faculty/admin foundation pages.
- Fortify authentication with login, logout, password reset, email verification, two-factor, and passkey support.
- Public self-registration disabled for institution-managed tenants.
- Explicit tenant-owned academic schema.
- Core models, factories, and demo seeder.
- Policies and routes proving tenant/section/team authorization.
- Automated tests for authentication, tenant isolation, role authorization, and data integrity.

## Architectural Decisions

- Platform roles are stored as `administrator`, `faculty`, and `student` on `users.global_role`.
- Halden simulation seats are separate `seats` records and are not mixed with platform roles.
- The application uses explicit relational tenancy rather than a third-party tenancy package.
- Public routes use ULIDs via `getRouteKeyName()` while numeric IDs remain internal database keys.
- Seats are modeled structurally now and can be attached to team members. Role-charter content remains deferred until the source file is available.
- Simulation lifecycle tables were not created in Batch 1.

## Models And Tables

Tables:

- `tenants`
- `users`
- `institutions`
- `courses`
- `sections`
- `seats`
- `section_faculty`
- `enrollments`
- `teams`
- `team_members`
- Laravel starter tables for sessions, cache, jobs, password reset tokens, passkeys, and two-factor authentication.

Models:

- `Tenant`
- `User`
- `Institution`
- `Course`
- `Section`
- `Enrollment`
- `Seat`
- `SectionFaculty`
- `Team`
- `TeamMember`

## Tenancy Enforcement

Every tenant-owned academic table carries `tenant_id`.

Layered protections:

- composite foreign keys such as `(tenant_id, course_id)` and `(tenant_id, section_id)`;
- unique constraints scoped by `tenant_id`;
- model relationships scoped through tenant-owned parents;
- policies that reject cross-tenant access even when a valid ULID is supplied;
- tests for URL/ID manipulation across tenants.

## Authorization

Policies:

- `CoursePolicy`
- `SectionPolicy`
- `TeamPolicy`

Rules:

- administrators can manage academic structures only inside their tenant;
- faculty can view/manage only assigned sections and their teams;
- students can view only their own enrolled section/team context;
- cross-tenant access is rejected for all platform roles.

## Seeded Demo Structure

The seeder creates:

- tenant/institution: Halden University Demo;
- course: Managerial Economics;
- sections: Section A and Section B;
- five structural Halden seats: EVP and four segment heads;
- demo administrator, faculty, and students;
- Team Alpha and Team Bravo.

Development-only credentials are documented in `README.md`.

## Testing

Batch 1 test coverage includes:

- authentication and logout;
- disabled public registration;
- tenant isolation;
- course/section/team isolation;
- role authorization;
- cross-tenant data integrity constraints;
- team membership enrollment constraints.

## Commands

```bash
php artisan test
composer run lint:check
composer run types:check
npm run check
npm run types:check
npm run build
```

## Notes

`halden-development-instructions.md`, Week 4/10 packages, Week 4 spec, role charters, and calibration materials are still missing. Batch 1 does not depend on their economic details, but later batches will.
