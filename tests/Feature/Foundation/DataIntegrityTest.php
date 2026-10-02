<?php

namespace Tests\Feature\Foundation;

use App\Models\Course;
use App\Models\Enrollment;
use App\Models\Section;
use App\Models\Team;
use App\Models\TeamMember;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use InvalidArgumentException;
use Tests\CreatesFoundationData;
use Tests\TestCase;

class DataIntegrityTest extends TestCase
{
    use CreatesFoundationData;
    use RefreshDatabase;

    public function test_course_cannot_reference_institution_from_another_tenant(): void
    {
        $tenantA = $this->tenantGraph('A');
        $tenantB = $this->tenantGraph('B');

        $this->expectException(QueryException::class);

        Course::factory()->create([
            'tenant_id' => $tenantA['tenant']->id,
            'institution_id' => $tenantB['institution']->id,
        ]);
    }

    public function test_section_cannot_reference_course_from_another_tenant(): void
    {
        $tenantA = $this->tenantGraph('A');
        $tenantB = $this->tenantGraph('B');

        $this->expectException(QueryException::class);

        Section::factory()->create([
            'tenant_id' => $tenantA['tenant']->id,
            'course_id' => $tenantB['course']->id,
        ]);
    }

    public function test_team_cannot_reference_section_from_another_tenant(): void
    {
        $tenantA = $this->tenantGraph('A');
        $tenantB = $this->tenantGraph('B');

        $this->expectException(QueryException::class);

        Team::factory()->create([
            'tenant_id' => $tenantA['tenant']->id,
            'section_id' => $tenantB['section']->id,
        ]);
    }

    public function test_team_member_must_be_enrolled_in_the_team_section(): void
    {
        $graph = $this->tenantGraph('A');
        $otherStudent = $this->tenantGraph('B')['student'];

        $this->expectException(InvalidArgumentException::class);

        TeamMember::query()->create([
            'tenant_id' => $graph['tenant']->id,
            'team_id' => $graph['team']->id,
            'user_id' => $otherStudent->id,
        ]);
    }

    public function test_enrollment_cannot_cross_tenant_boundaries(): void
    {
        $tenantA = $this->tenantGraph('A');
        $tenantB = $this->tenantGraph('B');

        $this->expectException(QueryException::class);

        Enrollment::query()->create([
            'tenant_id' => $tenantA['tenant']->id,
            'section_id' => $tenantA['section']->id,
            'user_id' => $tenantB['student']->id,
            'status' => 'active',
        ]);
    }
}
