<?php

namespace Tests\Feature\Foundation;

use App\Models\Section;
use App\Models\Team;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\CreatesFoundationData;
use Tests\TestCase;

class SectionAndTeamAuthorizationTest extends TestCase
{
    use CreatesFoundationData;
    use RefreshDatabase;

    public function test_faculty_assigned_to_one_section_cannot_manage_another_section(): void
    {
        $graph = $this->tenantGraph('A');
        $otherSection = Section::factory()->create([
            'tenant_id' => $graph['tenant']->id,
            'course_id' => $graph['course']->id,
            'name' => 'Section B',
        ]);

        $this->actingAs($graph['faculty'])
            ->patch(route('foundation.sections.update', $otherSection), ['name' => 'Renamed'])
            ->assertForbidden();
    }

    public function test_student_cannot_access_another_section_team(): void
    {
        $graph = $this->tenantGraph('A');
        $otherSection = Section::factory()->create([
            'tenant_id' => $graph['tenant']->id,
            'course_id' => $graph['course']->id,
            'name' => 'Section B',
        ]);
        $otherTeam = Team::factory()->create([
            'tenant_id' => $graph['tenant']->id,
            'section_id' => $otherSection->id,
            'slug' => 'section-b-team',
        ]);

        $this->actingAs($graph['student'])
            ->get(route('foundation.teams.show', $otherTeam))
            ->assertForbidden();
    }

    public function test_student_cannot_perform_faculty_or_admin_operations(): void
    {
        $graph = $this->tenantGraph('A');

        $this->actingAs($graph['student'])
            ->patch(route('foundation.sections.update', $graph['section']), ['name' => 'Nope'])
            ->assertForbidden();

        $this->actingAs($graph['student'])
            ->patch(route('foundation.courses.update', $graph['course']), ['name' => 'Nope'])
            ->assertForbidden();
    }

    public function test_faculty_cannot_perform_institution_admin_operations(): void
    {
        $graph = $this->tenantGraph('A');

        $this->actingAs($graph['faculty'])
            ->patch(route('foundation.courses.update', $graph['course']), ['name' => 'Faculty Edit'])
            ->assertForbidden();
    }

    public function test_authorized_faculty_can_view_assigned_section_and_team(): void
    {
        $graph = $this->tenantGraph('A');

        $this->actingAs($graph['faculty'])
            ->get(route('foundation.sections.show', $graph['section']))
            ->assertOk()
            ->assertJsonPath('name', 'Section A');

        $this->actingAs($graph['faculty'])
            ->get(route('foundation.teams.show', $graph['team']))
            ->assertOk()
            ->assertJsonPath('name', 'Team A');
    }
}
