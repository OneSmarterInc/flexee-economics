<?php

namespace Tests\Feature\Foundation;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\CreatesFoundationData;
use Tests\TestCase;

class TenantIsolationTest extends TestCase
{
    use CreatesFoundationData;
    use RefreshDatabase;

    public function test_tenant_admin_cannot_view_or_update_another_tenant_course(): void
    {
        $tenantA = $this->tenantGraph('A');
        $tenantB = $this->tenantGraph('B');

        $this->actingAs($tenantA['admin'])
            ->get(route('foundation.courses.show', $tenantB['course']))
            ->assertForbidden();

        $this->actingAs($tenantA['admin'])
            ->patch(route('foundation.courses.update', $tenantB['course']), ['name' => 'Changed'])
            ->assertForbidden();

        $this->assertSame('Managerial Economics B', $tenantB['course']->fresh()->name);
    }

    public function test_tenant_faculty_cannot_access_another_tenant_section(): void
    {
        $tenantA = $this->tenantGraph('A');
        $tenantB = $this->tenantGraph('B');

        $this->actingAs($tenantA['faculty'])
            ->get(route('foundation.sections.show', $tenantB['section']))
            ->assertForbidden();
    }

    public function test_tenant_student_cannot_access_another_tenant_team(): void
    {
        $tenantA = $this->tenantGraph('A');
        $tenantB = $this->tenantGraph('B');

        $this->actingAs($tenantA['student'])
            ->get(route('foundation.teams.show', $tenantB['team']))
            ->assertForbidden();
    }
}
