<?php

namespace Tests\Feature\Advisors;

use App\Domain\Advisors\AdvisorCatalog;
use App\Domain\Advisors\AdvisorConsultationService;
use App\Enums\TeamSimulationStatus;
use App\Models\Advisor;
use App\Models\AdvisorConsultationSession;
use App\Models\AdvisorResponse;
use App\Models\Enrollment;
use App\Models\SectionSimulation;
use App\Models\SectionSimulationWeek;
use App\Models\SimulationWeek;
use App\Models\Team;
use App\Models\TeamMember;
use App\Models\TeamSimulation;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use InvalidArgumentException;
use Tests\CreatesFoundationData;
use Tests\TestCase;

class AdvisorConsultationFrameworkTest extends TestCase
{
    use CreatesFoundationData;
    use RefreshDatabase;

    public function test_advisor_catalog_loads_seven_halden_advisors(): void
    {
        $advisors = app(AdvisorCatalog::class)->ensureHaldenAdvisors();

        $this->assertCount(7, $advisors);
        $this->assertSame([
            'elena_marchetti',
            'danielle_roy',
            'bjorn_aasen',
            'ana_ruiz',
            'kwame_osei',
            'margrethe_lund',
            'priya_venkatesan',
        ], $advisors->pluck('key')->all());
        $this->assertTrue($advisors->every(fn (Advisor $advisor): bool => $advisor->content_version === AdvisorCatalog::CONTENT_VERSION));
    }

    public function test_team_can_use_three_advisor_slots_per_week(): void
    {
        $context = $this->advisorContext();
        $service = app(AdvisorConsultationService::class);
        $advisors = app(AdvisorCatalog::class)->ensureHaldenAdvisors();

        foreach ($advisors->take(3) as $advisor) {
            $service->requestConsultation(
                actor: $context['graph']['student'],
                runtimeWeek: $context['runtimeWeek'],
                teamSimulation: $context['teamSimulation'],
                advisor: $advisor,
                question: 'What should we watch?',
            );
        }

        $this->expectException(InvalidArgumentException::class);

        $service->requestConsultation(
            actor: $context['graph']['student'],
            runtimeWeek: $context['runtimeWeek'],
            teamSimulation: $context['teamSimulation'],
            advisor: $advisors[3],
            question: 'Can we ask a fourth advisor?',
        );
    }

    public function test_duplicate_advisor_request_returns_existing_session_without_consuming_slot(): void
    {
        $context = $this->advisorContext();
        $service = app(AdvisorConsultationService::class);
        $advisor = app(AdvisorCatalog::class)->ensureHaldenAdvisors()->first();

        $first = $service->requestConsultation($context['graph']['student'], $context['runtimeWeek'], $context['teamSimulation'], $advisor, 'First question');
        $second = $service->requestConsultation($context['graph']['student'], $context['runtimeWeek'], $context['teamSimulation'], $advisor, 'Second question');

        $this->assertSame($first->id, $second->id);
        $this->assertSame(1, AdvisorConsultationSession::query()->count());
        $this->assertSame(1, AdvisorResponse::query()->count());
    }

    public function test_consultation_preserves_question_response_and_content_version(): void
    {
        $context = $this->advisorContext();
        $advisor = app(AdvisorCatalog::class)->ensureHaldenAdvisors()->firstWhere('key', 'ana_ruiz');

        $session = app(AdvisorConsultationService::class)->requestConsultation(
            actor: $context['graph']['student'],
            runtimeWeek: $context['runtimeWeek'],
            teamSimulation: $context['teamSimulation'],
            advisor: $advisor,
            question: 'How should we think about incentives?',
            context: ['week' => 4],
        );

        $this->assertSame('How should we think about incentives?', $session->question);
        $this->assertSame(['week' => 4], $session->context_snapshot);
        $this->assertSame(AdvisorCatalog::CONTENT_VERSION, $session->response->content_version);
        $this->assertSame($advisor->default_guidance, $session->response->response);
        $this->assertSame('ana_ruiz', $session->response->response_snapshot['advisor_key']);
    }

    public function test_consultation_history_is_immutable(): void
    {
        $session = $this->createConsultation();

        $this->expectException(InvalidArgumentException::class);

        $session->update(['question' => 'changed']);
    }

    public function test_advisor_response_history_is_immutable(): void
    {
        $session = $this->createConsultation();

        $this->expectException(InvalidArgumentException::class);

        $session->response->update(['response' => 'changed']);
    }

    public function test_student_cannot_view_or_request_another_team_consultation(): void
    {
        $context = $this->advisorContext();
        [$otherStudent, , $otherTeamSimulation] = $this->addSecondTeam($context['graph'], $context['sectionSimulation']);
        $advisor = app(AdvisorCatalog::class)->ensureHaldenAdvisors()->first();
        $service = app(AdvisorConsultationService::class);
        $session = $service->requestConsultation($otherStudent, $context['runtimeWeek'], $otherTeamSimulation, $advisor, 'Other team question');

        try {
            $service->requestConsultation($context['graph']['student'], $context['runtimeWeek'], $otherTeamSimulation, $advisor, 'Invalid request');
            $this->fail('Expected student to be blocked from another team consultation.');
        } catch (InvalidArgumentException) {
            $this->assertSame(1, AdvisorConsultationSession::query()->count());
        }

        $this->expectException(InvalidArgumentException::class);

        $service->assertCanView($context['graph']['student'], $session);
    }

    public function test_student_view_exposes_no_hidden_probabilities_or_future_outcomes(): void
    {
        $session = $this->createConsultation();

        $view = app(AdvisorConsultationService::class)->studentView($session);

        $this->assertSame(['advisor', 'question', 'response', 'content_version', 'requested_at', 'responded_at'], array_keys($view));
        $this->assertArrayNotHasKey('probability', $view);
        $this->assertArrayNotHasKey('future_outcome', $view);
        $this->assertSame(['key', 'name', 'title', 'perspective'], array_keys($view['advisor']));
    }

    private function createConsultation(): AdvisorConsultationSession
    {
        $context = $this->advisorContext();
        $advisor = app(AdvisorCatalog::class)->ensureHaldenAdvisors()->first();

        return app(AdvisorConsultationService::class)->requestConsultation(
            actor: $context['graph']['student'],
            runtimeWeek: $context['runtimeWeek'],
            teamSimulation: $context['teamSimulation'],
            advisor: $advisor,
            question: 'What should we watch?',
        );
    }

    /**
     * @return array{
     *     graph: array<string, mixed>,
     *     sectionSimulation: SectionSimulation,
     *     runtimeWeek: SectionSimulationWeek,
     *     teamSimulation: TeamSimulation
     * }
     */
    private function advisorContext(string $suffix = 'A'): array
    {
        $graph = $this->tenantGraph($suffix);
        $structure = $this->simulationStructure(4);
        $sectionSimulation = $this->assignSimulation($graph, $structure['version']);
        /** @var SimulationWeek $week4 */
        $week4 = $structure['simulationWeeks']->firstWhere('week_number', 4);
        $runtimeWeek = $sectionSimulation->weeks()
            ->where('simulation_week_id', $week4->id)
            ->firstOrFail();
        $teamSimulation = $sectionSimulation->teamSimulations()
            ->where('team_id', $graph['team']->id)
            ->firstOrFail();

        return compact('graph', 'sectionSimulation', 'runtimeWeek', 'teamSimulation');
    }

    /**
     * @param  array<string, mixed>  $graph
     * @return array{User, Team, TeamSimulation}
     */
    private function addSecondTeam(array $graph, SectionSimulation $sectionSimulation): array
    {
        $student = User::factory()->student()->create([
            'tenant_id' => $graph['tenant']->id,
            'email' => 'student-second-advisor@example.test',
        ]);
        $team = Team::factory()->create([
            'tenant_id' => $graph['tenant']->id,
            'section_id' => $graph['section']->id,
            'name' => 'Team second advisor',
            'slug' => 'team-second-advisor',
        ]);

        Enrollment::query()->create([
            'tenant_id' => $graph['tenant']->id,
            'section_id' => $graph['section']->id,
            'user_id' => $student->id,
            'status' => 'active',
        ]);

        TeamMember::query()->create([
            'tenant_id' => $graph['tenant']->id,
            'team_id' => $team->id,
            'user_id' => $student->id,
        ]);

        $teamSimulation = TeamSimulation::query()->create([
            'tenant_id' => $graph['tenant']->id,
            'section_simulation_id' => $sectionSimulation->id,
            'section_id' => $graph['section']->id,
            'team_id' => $team->id,
            'status' => TeamSimulationStatus::Active,
            'metadata' => [],
        ]);

        return [$student, $team, $teamSimulation];
    }
}
