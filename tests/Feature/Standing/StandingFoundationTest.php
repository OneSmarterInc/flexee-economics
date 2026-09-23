<?php

namespace Tests\Feature\Standing;

use App\Domain\Standing\CounterpartyCatalog;
use App\Domain\Standing\StandingService;
use App\Enums\StandingValue;
use App\Enums\TeamSimulationStatus;
use App\Models\Counterparty;
use App\Models\Enrollment;
use App\Models\SectionSimulation;
use App\Models\SectionSimulationWeek;
use App\Models\SimulationWeek;
use App\Models\StandingEvent;
use App\Models\StandingState;
use App\Models\Team;
use App\Models\TeamMember;
use App\Models\TeamSimulation;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use InvalidArgumentException;
use Tests\CreatesFoundationData;
use Tests\TestCase;

class StandingFoundationTest extends TestCase
{
    use CreatesFoundationData;
    use RefreshDatabase;

    public function test_counterparty_catalog_loads_halden_relationships(): void
    {
        $counterparties = app(CounterpartyCatalog::class)->ensureHaldenCounterparties();

        $this->assertCount(8, $counterparties);
        $this->assertSame([
            'delacroix',
            'vestergaard',
            'kuhn',
            'whitaker',
            'straits_pacific',
            'tetteh',
            'board',
            'unions',
        ], $counterparties->pluck('key')->all());
    }

    public function test_standing_initialization_creates_qualitative_state_for_each_counterparty(): void
    {
        $context = $this->standingContext();

        $states = app(StandingService::class)->initializeTeamSimulation($context['teamSimulation']);

        $this->assertCount(8, $states);
        $this->assertSame(8, StandingState::query()->count());
        $this->assertSame(8, StandingEvent::query()->count());
        $this->assertSame(StandingValue::Watchful, $states[0]->stateEnum());
        $this->assertSame('Initial relationship baseline.', $states[0]->reason);
    }

    public function test_standing_transition_updates_current_state_and_preserves_history(): void
    {
        $context = $this->standingContext();
        $service = app(StandingService::class);
        $service->initializeTeamSimulation($context['teamSimulation']);
        $delacroix = Counterparty::query()->where('key', 'delacroix')->firstOrFail();

        $standing = $service->applyChange(
            teamSimulation: $context['teamSimulation'],
            counterparty: $delacroix,
            newState: StandingValue::Strained,
            reason: 'Transfer-pricing posture created political friction.',
            runtimeWeek: $context['runtimeWeek'],
        );

        $events = $standing->events()->orderBy('id')->get();

        $this->assertSame(StandingValue::Strained, $standing->stateEnum());
        $this->assertSame('Transfer-pricing posture created political friction.', $standing->reason);
        $this->assertCount(2, $events);
        $this->assertNull($events[0]->old_state);
        $this->assertSame(StandingValue::Watchful, $events[0]->new_state);
        $this->assertSame(StandingValue::Watchful, $events[1]->old_state);
        $this->assertSame(StandingValue::Strained, $events[1]->new_state);
        $this->assertSame($context['runtimeWeek']->id, $events[1]->section_simulation_week_id);
    }

    public function test_standing_event_history_is_immutable(): void
    {
        $context = $this->standingContext();
        $standing = app(StandingService::class)->initializeTeamSimulation($context['teamSimulation'])[0];
        $event = $standing->events()->firstOrFail();

        $this->expectException(InvalidArgumentException::class);

        $event->update(['reason' => 'changed']);
    }

    public function test_student_view_exposes_no_numeric_standing_score(): void
    {
        $context = $this->standingContext();
        $service = app(StandingService::class);
        $standing = $service->initializeTeamSimulation($context['teamSimulation'])[0];

        $view = $service->studentView($standing);

        $this->assertSame(['state', 'reason', 'history'], array_keys($view));
        $this->assertArrayNotHasKey('score', $view);
        $this->assertArrayNotHasKey('value', $view);
        $this->assertSame('watchful', $view['state']);
        $this->assertSame('watchful', $view['history'][0]['new_state']);
        $this->assertArrayNotHasKey('score', $view['history'][0]);
    }

    public function test_student_cannot_view_another_team_standing(): void
    {
        $context = $this->standingContext();
        [$otherStudent] = $this->addSecondTeam($context['graph'], $context['sectionSimulation']);
        $otherTeamSimulation = TeamSimulation::query()
            ->where('tenant_id', $context['graph']['tenant']->id)
            ->where('section_simulation_id', $context['sectionSimulation']->id)
            ->whereHas('team.members', fn ($query) => $query->whereKey($otherStudent->id))
            ->firstOrFail();
        $standing = app(StandingService::class)->initializeTeamSimulation($otherTeamSimulation)[0];

        $this->expectException(InvalidArgumentException::class);

        app(StandingService::class)->assertCanView($context['graph']['student'], $standing);
    }

    public function test_standing_runtime_week_must_match_team_simulation(): void
    {
        $context = $this->standingContext();
        $other = $this->standingContext('B');
        $counterparty = app(CounterpartyCatalog::class)->ensureHaldenCounterparties()->first();

        $this->expectException(InvalidArgumentException::class);

        app(StandingService::class)->applyChange(
            teamSimulation: $context['teamSimulation'],
            counterparty: $counterparty,
            newState: StandingValue::Guarded,
            reason: 'Invalid cross-tenant fixture.',
            runtimeWeek: $other['runtimeWeek'],
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
    private function standingContext(string $suffix = 'A'): array
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
     * @return array{User, Team}
     */
    private function addSecondTeam(array $graph, ?SectionSimulation $sectionSimulation = null): array
    {
        $student = User::factory()->student()->create([
            'tenant_id' => $graph['tenant']->id,
            'email' => 'student-second-standing@example.test',
        ]);
        $team = Team::factory()->create([
            'tenant_id' => $graph['tenant']->id,
            'section_id' => $graph['section']->id,
            'name' => 'Team second standing',
            'slug' => 'team-second-standing',
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

        if ($sectionSimulation !== null) {
            TeamSimulation::query()->create([
                'tenant_id' => $graph['tenant']->id,
                'section_simulation_id' => $sectionSimulation->id,
                'section_id' => $graph['section']->id,
                'team_id' => $team->id,
                'status' => TeamSimulationStatus::Active,
                'metadata' => [],
            ]);
        }

        return [$student, $team];
    }
}
