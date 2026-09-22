<?php

namespace Tests\Feature\Submissions;

use App\Domain\Submissions\SubmissionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\CreatesFoundationData;
use Tests\TestCase;

class StudentAndFacultySubmissionUiTest extends TestCase
{
    use CreatesFoundationData;
    use RefreshDatabase;

    public function test_student_can_view_authorized_submission_screen_and_other_student_cannot(): void
    {
        $graph = $this->tenantGraph('A');
        $other = $this->tenantGraph('B');
        $context = $this->openRuntimeWeekWithDefinitions($graph);

        $this->actingAs($graph['student'])
            ->get(route('student.submissions.show', $context['runtimeWeek']))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('week.title', 'Week 1')
                ->where('decisionDefinition.fields.0.key', 'demo_quantity'));

        $this->actingAs($other['student'])
            ->get(route('student.submissions.show', $context['runtimeWeek']))
            ->assertForbidden();
    }

    public function test_faculty_lifecycle_page_shows_submission_status_for_authorized_section(): void
    {
        $graph = $this->tenantGraph('A');
        $context = $this->openRuntimeWeekWithDefinitions($graph);

        app(SubmissionService::class)->submitDecision(
            $graph['student'],
            $context['runtimeWeek'],
            $context['teamSimulation'],
            $context['decisionDefinition'],
            ['demo_quantity' => 5],
        );

        $this->actingAs($graph['faculty'])
            ->get(route('simulation-lifecycle.overview'))
            ->assertOk()
            ->assertSee('Team submission status')
            ->assertSee('Decisions: submitted');
    }
}
