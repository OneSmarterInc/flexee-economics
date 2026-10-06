<?php

namespace Tests\Feature\Assignments;

use App\Domain\Assessment\Week14BoardDefenseService;
use App\Domain\Assignments\EffectiveSeatAssignmentService;
use App\Domain\CausalTrace\CausalTraceService;
use App\Domain\Simulation\SimulationLifecycleService;
use App\Domain\Submissions\SubmissionService;
use App\Enums\DecisionFieldType;
use App\Enums\PlatformRole;
use App\Enums\SectionSimulationWeekStatus;
use App\Models\DecisionFieldDefinition;
use App\Models\DecisionFormDefinition;
use App\Models\DecisionSubmission;
use App\Models\Enrollment;
use App\Models\Seat;
use App\Models\SectionSimulationWeek;
use App\Models\Simulation;
use App\Models\SimulationSeatAssignment;
use App\Models\SimulationVariant;
use App\Models\SimulationVersion;
use App\Models\SimulationWeek;
use App\Models\TeamMember;
use App\Models\User;
use App\Models\WeekContentVersion;
use Illuminate\Foundation\Testing\RefreshDatabase;
use InvalidArgumentException;
use Tests\CreatesFoundationData;
use Tests\TestCase;

class EffectiveDatedSeatAssignmentTest extends TestCase
{
    use CreatesFoundationData;
    use RefreshDatabase;

    public function test_historical_decision_reconstructs_effective_seat_and_form_definition_after_rotation(): void
    {
        $context = $this->seatContext([1, 4, 8, 10, 14]);
        $service = app(EffectiveSeatAssignmentService::class);
        $firstSeat = Seat::factory()->create(['code' => 'commercial_operations', 'name' => 'Commercial Operations']);
        $secondSeat = Seat::factory()->create(['code' => 'operations_finance', 'name' => 'Operations Finance']);

        $service->createPeriod($context['teamSimulation'], $context['graph']['student'], $firstSeat, 1, 8, 'first_seat', source: 'test');
        $service->createPeriod($context['teamSimulation'], $context['graph']['student'], $secondSeat, 10, 14, 'second_seat', source: 'test');

        $week4Decision = $this->submitDecision($context, 4, ['choice' => 'protect_margin'], 'week4_form_v1');
        $week10Decision = $this->submitDecision($context, 10, ['choice' => 'protect_cash'], 'week10_form_v1');

        SimulationSeatAssignment::query()->create([
            'tenant_id' => $context['graph']['tenant']->id,
            'team_simulation_id' => $context['teamSimulation']->id,
            'team_id' => $context['teamSimulation']->team_id,
            'user_id' => $context['graph']['student']->id,
            'seat_id' => $secondSeat->id,
        ]);
        SimulationSeatAssignment::query()
            ->where('team_simulation_id', $context['teamSimulation']->id)
            ->where('user_id', $context['graph']['student']->id)
            ->sole()
            ->update(['seat_id' => $firstSeat->id]);

        $this->assertSame('commercial_operations', $week4Decision->refresh()->definition_snapshot['seat_context']['seat_code']);
        $this->assertSame('first_seat', $week4Decision->definition_snapshot['seat_context']['role_phase']);
        $this->assertSame('operations_finance', $week10Decision->refresh()->definition_snapshot['seat_context']['seat_code']);
        $this->assertSame('second_seat', $week10Decision->definition_snapshot['seat_context']['role_phase']);
        $this->assertSame('week4_form_v1', $week4Decision->definition_snapshot['definition']['version']);

        $trace = app(CausalTraceService::class)->forwardFromDecision($context['graph']['faculty'], $week4Decision);
        $decisionNode = collect($trace->nodes)->firstWhere('type', 'decision');

        $this->assertSame('commercial_operations', $decisionNode->payload['seat_context']['seat_code']);
        $this->assertSame('week4_form_v1', $decisionNode->payload['definition_version']);
        $this->assertSame('protect_margin', $decisionNode->payload['available_alternatives']['choice']['selected']);
    }

    public function test_seven_week_variant_resolves_first_and_second_seat_schedule(): void
    {
        $context = $this->seatContext([1, 4, 6, 8, 10, 12, 14], durationWeeks: 7, configuration: [
            'authoritative_week_sequence' => [1, 4, 6, 8, 10, 12, 14],
            'role_rotation' => [
                'first_seat_weeks' => [1, 4, 6, 8],
                'second_seat_weeks' => [10, 12, 14],
                'rotation_before_week' => 10,
            ],
        ]);
        $service = app(EffectiveSeatAssignmentService::class);
        $firstSeat = Seat::factory()->create(['code' => 'commercial_operations', 'name' => 'Commercial Operations']);
        $secondSeat = Seat::factory()->create(['code' => 'operations_finance', 'name' => 'Operations Finance']);

        $service->createPeriod($context['teamSimulation'], $context['graph']['student'], $firstSeat, 1, 8, 'first_seat', source: 'seven_week_schedule');
        $service->createPeriod($context['teamSimulation'], $context['graph']['student'], $secondSeat, 10, 14, 'second_seat', source: 'seven_week_schedule');

        foreach ([1, 4, 6, 8] as $weekNumber) {
            $this->assertSame('commercial_operations', $service->contextFor($context['teamSimulation'], $context['graph']['student'], $context['weeks'][$weekNumber])['seat_code']);
        }

        foreach ([10, 12, 14] as $weekNumber) {
            $this->assertSame('operations_finance', $service->contextFor($context['teamSimulation'], $context['graph']['student'], $context['weeks'][$weekNumber])['seat_code']);
        }
    }

    public function test_fourteen_week_rotation_is_supported_without_inventing_an_automatic_second_seat_schedule(): void
    {
        $context = $this->seatContext(range(1, 14));
        $service = app(EffectiveSeatAssignmentService::class);
        $firstSeat = Seat::factory()->create(['code' => 'upstream_head', 'name' => 'Upstream Head']);
        $secondSeat = Seat::factory()->create(['code' => 'refining_head', 'name' => 'Refining Head']);

        $service->createPeriod($context['teamSimulation'], $context['graph']['student'], $firstSeat, 1, 7, 'first_seat', source: 'fourteen_week_manual');
        $service->createPeriod($context['teamSimulation'], $context['graph']['student'], $secondSeat, 8, 14, 'second_seat', source: 'fourteen_week_manual');

        $this->assertSame('upstream_head', $service->contextFor($context['teamSimulation'], $context['graph']['student'], $context['weeks'][7])['seat_code']);
        $this->assertSame('refining_head', $service->contextFor($context['teamSimulation'], $context['graph']['student'], $context['weeks'][8])['seat_code']);
        $this->assertSame('second_seat', $service->contextFor($context['teamSimulation'], $context['graph']['student'], $context['weeks'][14])['role_phase']);
    }

    public function test_multiple_members_team_and_tenant_isolation_are_enforced(): void
    {
        $context = $this->seatContext([1, 8, 10]);
        $otherStudent = $this->addTeamStudent($context, 'second-member@example.test');
        $service = app(EffectiveSeatAssignmentService::class);
        $evpSeat = Seat::factory()->create(['code' => 'evp_integrated_ops', 'name' => 'EVP Integrated Operations']);
        $tradingSeat = Seat::factory()->create(['code' => 'trading_finance', 'name' => 'Trading and Finance']);

        $service->createPeriod($context['teamSimulation'], $context['graph']['student'], $evpSeat, 1, 14, 'first_seat', source: 'test');
        $service->createPeriod($context['teamSimulation'], $otherStudent, $tradingSeat, 1, 14, 'first_seat', source: 'test');

        $this->assertSame('evp_integrated_ops', $service->contextFor($context['teamSimulation'], $context['graph']['student'], $context['weeks'][8])['seat_code']);
        $this->assertSame('trading_finance', $service->contextFor($context['teamSimulation'], $otherStudent, $context['weeks'][8])['seat_code']);

        $otherContext = $this->seatContext([1, 8, 10], suffix: 'OtherTenant');

        $this->assertNull($service->contextFor($context['teamSimulation'], $otherContext['graph']['student'], $context['weeks'][8]));
        $this->assertSame([], $service->teamHistory($otherContext['teamSimulation']));

        $this->expectException(InvalidArgumentException::class);
        $service->createPeriod($context['teamSimulation'], $otherContext['graph']['student'], $evpSeat, 1, 14, 'first_seat', source: 'bad_cross_tenant');
    }

    public function test_week14_history_packet_includes_effective_seat_history(): void
    {
        $context = $this->seatContext([1, 8, 10, 14], durationWeeks: 7);
        $service = app(EffectiveSeatAssignmentService::class);
        $firstSeat = Seat::factory()->create(['code' => 'commercial_operations', 'name' => 'Commercial Operations']);
        $secondSeat = Seat::factory()->create(['code' => 'operations_finance', 'name' => 'Operations Finance']);

        $service->createPeriod($context['teamSimulation'], $context['graph']['student'], $firstSeat, 1, 8, 'first_seat', source: 'test');
        $service->createPeriod($context['teamSimulation'], $context['graph']['student'], $secondSeat, 10, 14, 'second_seat', source: 'test');

        $view = app(Week14BoardDefenseService::class)->facultyView(
            $context['graph']['faculty'],
            $context['weeks'][14],
            $context['teamSimulation'],
        );

        $this->assertSame(['commercial_operations', 'operations_finance'], collect($view['history_packet']['seat_history'])->pluck('seat_code')->all());
        $this->assertSame(['first_seat', 'second_seat'], collect($view['history_packet']['seat_history'])->pluck('role_phase')->all());
        $this->assertArrayNotHasKey('faculty_private_notes', $view['history_packet']);
    }

    /**
     * @param  list<int>  $weekNumbers
     * @param  array<string, mixed>  $configuration
     * @return array<string, mixed>
     */
    private function seatContext(array $weekNumbers, int $durationWeeks = 14, array $configuration = [], string $suffix = 'Seats'): array
    {
        $graph = $this->tenantGraph($suffix.uniqid());
        $simulation = Simulation::factory()->create([
            'slug' => 'halden-seat-history-'.uniqid(),
            'name' => 'Halden Seat History',
        ]);
        $variant = SimulationVariant::factory()->create([
            'simulation_id' => $simulation->id,
            'slug' => 'seat-history-'.$durationWeeks.'-'.uniqid(),
            'name' => $durationWeeks === 7 ? 'Seven-week compressed variant' : 'Fourteen-week flagship',
            'duration_weeks' => $durationWeeks,
        ]);
        $version = SimulationVersion::factory()->published()->create([
            'simulation_id' => $simulation->id,
            'simulation_variant_id' => $variant->id,
            'version' => 'seat-history-'.uniqid(),
            'configuration' => [
                'authoritative_week_sequence' => $weekNumbers,
                ...$configuration,
            ],
        ]);
        $simulationWeeks = collect($weekNumbers)->map(function (int $weekNumber) use ($simulation, $variant, $version): SimulationWeek {
            $week = SimulationWeek::factory()->create([
                'simulation_id' => $simulation->id,
                'simulation_variant_id' => $variant->id,
                'simulation_version_id' => $version->id,
                'week_number' => $weekNumber,
                'slug' => 'seat-history-week-'.$weekNumber,
                'title' => 'Week '.$weekNumber,
            ]);

            WeekContentVersion::factory()->create([
                'simulation_version_id' => $version->id,
                'simulation_week_id' => $week->id,
            ]);

            return $week;
        });
        $structure = compact('simulation', 'variant', 'version', 'simulationWeeks');

        $sectionSimulation = $this->assignSimulation($graph, $version->refresh());
        $weeks = collect($weekNumbers)->mapWithKeys(fn (int $weekNumber): array => [
            $weekNumber => $sectionSimulation->weeks()
                ->whereHas('definition', fn ($query) => $query->where('week_number', $weekNumber))
                ->firstOrFail(),
        ]);
        $teamSimulation = $sectionSimulation->teamSimulations()
            ->where('team_id', $graph['team']->id)
            ->firstOrFail();

        return compact('graph', 'structure', 'sectionSimulation', 'weeks', 'teamSimulation');
    }

    /**
     * @param  array<string, mixed>  $context
     * @param  array<string, mixed>  $answers
     */
    private function submitDecision(array $context, int $weekNumber, array $answers, string $definitionVersion): DecisionSubmission
    {
        $runtimeWeek = $this->openWeek($context, $context['weeks'][$weekNumber]);
        $definition = DecisionFormDefinition::factory()->create([
            'simulation_version_id' => $runtimeWeek->simulation_version_id,
            'simulation_week_id' => $runtimeWeek->simulation_week_id,
            'key' => 'week_'.$weekNumber.'_seat_test',
            'name' => 'Week '.$weekNumber.' seat test',
            'version' => $definitionVersion,
            'metadata' => [
                'seat_test' => true,
            ],
        ]);
        DecisionFieldDefinition::factory()->create([
            'decision_form_definition_id' => $definition->id,
            'field_key' => 'choice',
            'label' => 'Choice',
            'field_type' => DecisionFieldType::Radio,
            'is_required' => true,
            'display_order' => 1,
            'options' => [
                ['value' => 'protect_margin', 'label' => 'Protect margin'],
                ['value' => 'protect_cash', 'label' => 'Protect cash'],
            ],
        ]);

        return app(SubmissionService::class)->submitDecision(
            $context['graph']['student'],
            $runtimeWeek,
            $context['teamSimulation'],
            $definition,
            $answers,
        );
    }

    /**
     * @param  array<string, mixed>  $context
     */
    private function openWeek(array $context, SectionSimulationWeek $week): SectionSimulationWeek
    {
        $lifecycle = app(SimulationLifecycleService::class);

        if ($week->statusEnum() === SectionSimulationWeekStatus::Draft) {
            $week = $lifecycle->transitionWeek($week, SectionSimulationWeekStatus::Released, $context['graph']['faculty']);
        }

        if ($week->statusEnum() === SectionSimulationWeekStatus::Released) {
            $week = $lifecycle->transitionWeek($week->refresh(), SectionSimulationWeekStatus::Open, $context['graph']['faculty'], now()->addDay());
        }

        return $week->refresh();
    }

    /**
     * @param  array<string, mixed>  $context
     */
    private function addTeamStudent(array $context, string $email): User
    {
        $student = User::factory()->create([
            'tenant_id' => $context['graph']['tenant']->id,
            'global_role' => PlatformRole::Student,
            'email' => $email,
        ]);
        Enrollment::query()->create([
            'tenant_id' => $context['graph']['tenant']->id,
            'section_id' => $context['graph']['section']->id,
            'user_id' => $student->id,
            'status' => 'active',
        ]);
        TeamMember::query()->create([
            'tenant_id' => $context['graph']['tenant']->id,
            'team_id' => $context['graph']['team']->id,
            'user_id' => $student->id,
        ]);

        return $student;
    }
}
