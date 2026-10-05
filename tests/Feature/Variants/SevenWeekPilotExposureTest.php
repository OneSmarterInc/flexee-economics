<?php

namespace Tests\Feature\Variants;

use App\Enums\SectionSimulationWeekStatus;
use App\Enums\StandingValue;
use App\Livewire\FacultyOperationsDashboard;
use App\Livewire\FacultyWeekControl;
use App\Models\CohortDecisionAggregate;
use App\Models\CohortFeedbackEffect;
use App\Models\Counterparty;
use App\Models\DecisionFieldDefinition;
use App\Models\SectionSimulation;
use App\Models\StandingState;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class SevenWeekPilotExposureTest extends TestCase
{
    use RefreshDatabase;

    private const SEQUENCE = [1, 4, 6, 8, 10, 12, 14];

    public function test_demo_seed_exposes_authoritative_seven_week_pilot_sequence(): void
    {
        $this->seed();

        $pilot = $this->pilotSectionSimulation();
        $weeks = $pilot->weeks()
            ->with('definition')
            ->get()
            ->sortBy(fn ($runtimeWeek): int => $runtimeWeek->definition->week_number)
            ->values();

        $this->assertSame('Seven-Week Variant', $pilot->variant->name);
        $this->assertSame(7, $pilot->variant->duration_weeks);
        $this->assertSame(self::SEQUENCE, $weeks->pluck('definition.week_number')->all());
        $this->assertSame(2, $pilot->teamSimulations()->count());
        $this->assertSame(10, $pilot->teamSimulations()->with('team.members')->get()->flatMap(fn ($teamSimulation) => $teamSimulation->team->members)->count());

        $statusByWeek = $weeks->mapWithKeys(fn ($runtimeWeek): array => [
            $runtimeWeek->definition->week_number => $runtimeWeek->statusValue(),
        ]);

        $this->assertSame(SectionSimulationWeekStatus::Open->value, $statusByWeek[1]);
        foreach ([4, 6, 8, 10, 12, 14] as $futureWeek) {
            $this->assertSame(SectionSimulationWeekStatus::Draft->value, $statusByWeek[$futureWeek]);
        }
    }

    public function test_student_dashboard_shows_only_variant_weeks_and_student_safe_materials(): void
    {
        $this->seed();

        $student = User::query()->where('email', 'pilot-alpha1@example.test')->firstOrFail();

        $this->actingAs($student)
            ->get(route('student.dashboard'))
            ->assertOk()
            ->assertDontSee('Team Beta')
            ->assertInertia(fn ($page) => $page
                ->where('journey.simulations.0.variant', 'Seven-Week Variant')
                ->where('journey.simulations.0.progress.total', 7)
                ->where('journey.simulations.0.variant_summary.is_seven_week_variant', true)
                ->where('journey.simulations.0.variant_summary.sequence', self::SEQUENCE)
                ->where('journey.simulations.0.current_week.number', 1)
                ->where('journey.simulations.0.role_rotation.phase', 'first_seat')
                ->where('journey.simulations.0.role_rotation.phase_weeks', [1, 4, 6, 8])
                ->where('journey.simulations.0.timeline', fn ($timeline): bool => collect($timeline)->pluck('number')->all() === self::SEQUENCE)
                ->where('journey.simulations.0.timeline.1.number', 4)
                ->where('journey.simulations.0.timeline.1.url', null)
                ->where('journey.simulations.0.timeline.6.number', 14)
                ->where('journey.simulations.0.current_content.artifacts', function ($artifacts): bool {
                    $types = collect($artifacts)->pluck('type')->all();
                    $labels = collect($artifacts)->pluck('label')->all();

                    return ! in_array('validation_report', $types, true)
                        && ! in_array('provenance', $types, true)
                        && ! in_array('expected_outputs', $types, true)
                        && in_array('Student workbook', $labels, true)
                        && in_array('Student analysis notebook', $labels, true);
                }));
    }

    public function test_student_week_workspace_uses_friendly_artifact_labels(): void
    {
        $this->seed();

        $student = User::query()->where('email', 'pilot-alpha1@example.test')->firstOrFail();
        $runtimeWeek = $this->pilotSectionSimulation()
            ->weeks()
            ->whereHas('definition', fn ($query) => $query->where('week_number', 1))
            ->firstOrFail();

        $this->actingAs($student)
            ->get(route('student.submissions.show', $runtimeWeek))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->where('artifacts', function ($artifacts): bool {
                    $types = collect($artifacts)->pluck('type')->all();
                    $labels = collect($artifacts)->pluck('label')->all();

                    return ! in_array('validation_report', $types, true)
                        && ! in_array('provenance', $types, true)
                        && ! in_array('expected_outputs', $types, true)
                        && in_array('Student workbook', $labels, true)
                        && in_array('Student analysis notebook', $labels, true)
                        && in_array('Benchmarks dataset', $labels, true);
                }));
    }

    public function test_student_dashboard_explains_second_seat_after_role_rotation_point(): void
    {
        $this->seed();

        $pilot = $this->pilotSectionSimulation();
        $pilot->weeks()
            ->whereHas('definition', fn ($query) => $query->where('week_number', 1))
            ->firstOrFail()
            ->forceFill([
                'status' => SectionSimulationWeekStatus::Published->value,
                'published_at' => now(),
            ])
            ->save();
        $pilot->weeks()
            ->whereHas('definition', fn ($query) => $query->where('week_number', 10))
            ->firstOrFail()
            ->forceFill([
                'status' => SectionSimulationWeekStatus::Open->value,
                'opened_at' => now(),
                'closes_at' => now()->addWeek(),
            ])
            ->save();

        $student = User::query()->where('email', 'pilot-alpha1@example.test')->firstOrFail();

        $this->actingAs($student)
            ->get(route('student.dashboard'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->where('journey.simulations.0.current_week.number', 10)
                ->where('journey.simulations.0.role_rotation.phase', 'second_seat')
                ->where('journey.simulations.0.role_rotation.phase_weeks', [10, 12, 14])
                ->where('journey.simulations.0.role_rotation.label', 'Second seat'));
    }

    public function test_faculty_surfaces_identify_pilot_sequence_and_exclude_omitted_weeks(): void
    {
        $this->seed();

        $pilot = $this->pilotSectionSimulation();
        $faculty = User::query()->where('email', 'faculty@example.test')->firstOrFail();

        Livewire::actingAs($faculty)
            ->test(FacultyOperationsDashboard::class)
            ->set('sectionSimulationId', $pilot->id)
            ->assertSee('Seven-Week Variant')
            ->assertSee('Week 1')
            ->assertSee('Week 14')
            ->assertDontSee('Week 2')
            ->assertDontSee('Week 13');

        Livewire::actingAs($faculty)
            ->test(FacultyWeekControl::class)
            ->set('sectionSimulationId', $pilot->id)
            ->assertSee('Seven-Week Variant')
            ->assertSee('Pilot sequence: Week 1')
            ->assertSee('Week 14: Board defense')
            ->assertDontSee('Week 2: Elasticity estimation')
            ->assertDontSee('Week 13: Factor markets');
    }

    public function test_seven_week_pilot_keeps_window_two_excluded(): void
    {
        $this->seed();

        $pilot = $this->pilotSectionSimulation();

        $this->assertSame(['window1', 'window2', 'window3'], $pilot->version->configuration['excluded_windows']);
        $this->assertSame(['week4_to_week6_discount_rate'], $pilot->version->configuration['cohort_windows']);
        $this->assertSame(0, CohortDecisionAggregate::query()->count());
        $this->assertSame(0, CohortFeedbackEffect::query()->count());
    }

    public function test_seven_week_pilot_excludes_student_entered_week10_dependencies(): void
    {
        $this->seed();

        $pilot = $this->pilotSectionSimulation();
        $counterparty = Counterparty::query()->where('key', 'straits_pacific')->firstOrFail();

        $this->assertSame(
            $pilot->teamSimulations()->count(),
            StandingState::query()
                ->where('section_simulation_id', $pilot->id)
                ->where('counterparty_id', $counterparty->id)
                ->where('state', StandingValue::Cooperative->value)
                ->count(),
        );

        $week4 = $pilot->weeks()->whereHas('definition', fn ($query) => $query->where('week_number', 4))->firstOrFail();
        $week8 = $pilot->weeks()->whereHas('definition', fn ($query) => $query->where('week_number', 8))->firstOrFail();

        $this->assertFalse($this->fieldExists($week4->simulation_week_id, 'br_reported_margin_strong'));
        $this->assertFalse($this->fieldExists($week8->simulation_week_id, 'cash_cushion_musd'));
    }

    private function fieldExists(int $simulationWeekId, string $fieldKey): bool
    {
        return DecisionFieldDefinition::query()
            ->whereHas('formDefinition', fn ($query) => $query->where('simulation_week_id', $simulationWeekId))
            ->where('field_key', $fieldKey)
            ->exists();
    }

    private function pilotSectionSimulation(): SectionSimulation
    {
        return SectionSimulation::query()
            ->where('name', 'Seven-Week Pilot Halden Energy')
            ->with(['variant', 'version', 'weeks.definition'])
            ->firstOrFail();
    }
}
