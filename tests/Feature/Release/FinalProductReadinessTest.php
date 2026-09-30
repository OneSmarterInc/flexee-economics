<?php

namespace Tests\Feature\Release;

use App\Domain\Assessment\Week14BoardDefenseService;
use App\Domain\Content\AuthoritativePackages\AuthoritativeContentPackageManifest;
use App\Domain\Simulation\SimulationLifecycleService;
use App\Enums\SectionSimulationWeekStatus;
use App\Models\BoardDefenseSubmission;
use App\Models\SectionSimulationWeek;
use App\Models\TeamSimulation;
use Illuminate\Foundation\Testing\RefreshDatabase;
use InvalidArgumentException;
use Tests\CreatesFoundationData;
use Tests\TestCase;

class FinalProductReadinessTest extends TestCase
{
    use CreatesFoundationData;
    use RefreshDatabase;

    public function test_release_package_manifest_preserves_supported_and_non_computational_week_boundaries(): void
    {
        $manifest = app(AuthoritativeContentPackageManifest::class);

        $this->assertSame([1, 2, 3, 5, 6, 7, 8, 9, 10, 11, 12, 13], $manifest->registrableWeeks());
        $this->assertArrayHasKey(4, $manifest->excludedWeeks());
        $this->assertArrayHasKey(14, $manifest->excludedWeeks());
        $this->assertStringContainsString('stable golden baseline', $manifest->excludedWeeks()[4]);
        $this->assertStringContainsString('board-defense assessment week', $manifest->excludedWeeks()[14]);

        $this->assertSame('authoritative_week13_reference_package', $manifest->packageType(13));

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Week 14 is a board-defense assessment week with no computational package by design.');

        $manifest->packageRoot(14);
    }

    public function test_release_security_boundaries_deny_student_faculty_tools_and_unpublished_assessment_feedback(): void
    {
        $context = $this->week14ReleaseContext();

        $this->actingAs($context['graph']['student'])
            ->get(route('faculty.dashboard'))
            ->assertForbidden();

        $this->actingAs($context['graph']['student'])
            ->get(route('faculty.causal-trace'))
            ->assertForbidden();

        $this->actingAs($context['graph']['student'])
            ->get(route('faculty.what-if'))
            ->assertForbidden();

        $this->actingAs($context['graph']['student'])
            ->postJson(route('student.week14.defense.submit', $context['runtimeWeek']), [
                'final_synthesis_memo' => 'Final board defense synthesis for release readiness.',
                'artifact_references' => [[
                    'type' => 'board_presentation',
                    'reference' => 'https://example.test/release-readiness-deck',
                ]],
            ])
            ->assertOk()
            ->assertJsonPath('submission.status', BoardDefenseSubmission::STATUS_SUBMITTED);

        $assessment = app(Week14BoardDefenseService::class)->saveAssessment(
            $context['graph']['faculty'],
            $context['runtimeWeek'],
            $context['teamSimulation'],
            [
                'strategic_coherence' => [
                    'faculty_evaluation' => 'coherent',
                    'comments' => 'History-backed final narrative.',
                ],
            ],
            'strong_reasoning_weaker_outcomes',
            'Faculty private release note.',
            'Student-facing release feedback.',
            complete: true,
        );

        $this->actingAs($context['graph']['student'])
            ->getJson(route('student.week14.defense.show', $context['runtimeWeek']))
            ->assertOk()
            ->assertJsonPath('assessment', null)
            ->assertJsonMissing(['faculty_private_notes' => 'Faculty private release note.'])
            ->assertJsonMissing(['body' => 'Student-facing release feedback.']);

        $this->actingAs($context['graph']['faculty'])
            ->postJson(route('faculty.week14.assessment.publish', $assessment))
            ->assertOk();

        $this->actingAs($context['graph']['student'])
            ->getJson(route('student.week14.defense.show', $context['runtimeWeek']))
            ->assertOk()
            ->assertJsonPath('assessment.reasoning_outcome_tier', 'strong_reasoning_weaker_outcomes')
            ->assertJsonPath('assessment.feedback.body', 'Student-facing release feedback.')
            ->assertJsonMissing(['faculty_private_notes' => 'Faculty private release note.']);
    }

    /**
     * @return array{
     *     graph: array<string, mixed>,
     *     runtimeWeek: SectionSimulationWeek,
     *     teamSimulation: TeamSimulation
     * }
     */
    private function week14ReleaseContext(): array
    {
        $graph = $this->tenantGraph('ReleaseReady');
        $structure = $this->simulationStructure(14);
        $sectionSimulation = $this->assignSimulation($graph, $structure['version']);

        /** @var SectionSimulationWeek $runtimeWeek */
        $runtimeWeek = $sectionSimulation->weeks()
            ->whereHas('definition', fn ($query) => $query->where('week_number', 14))
            ->firstOrFail();

        $lifecycle = app(SimulationLifecycleService::class);
        $runtimeWeek = $lifecycle->transitionWeek($runtimeWeek, SectionSimulationWeekStatus::Released, $graph['faculty']);
        $runtimeWeek = $lifecycle->transitionWeek($runtimeWeek->refresh(), SectionSimulationWeekStatus::Open, $graph['faculty'], now()->addWeek());

        /** @var TeamSimulation $teamSimulation */
        $teamSimulation = $sectionSimulation->teamSimulations()
            ->where('team_id', $graph['team']->id)
            ->firstOrFail();

        return compact('graph', 'runtimeWeek', 'teamSimulation');
    }
}
