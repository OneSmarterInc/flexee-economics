<?php

namespace Database\Factories;

use App\Enums\SubmissionStatus;
use App\Models\DecisionFormDefinition;
use App\Models\DecisionSubmission;
use App\Models\SectionSimulationWeek;
use App\Models\TeamSimulation;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<DecisionSubmission>
 */
class DecisionSubmissionFactory extends Factory
{
    public function definition(): array
    {
        $runtimeWeek = SectionSimulationWeek::factory()->create();
        $teamSimulation = TeamSimulation::factory()->create([
            'tenant_id' => $runtimeWeek->tenant_id,
            'section_simulation_id' => $runtimeWeek->section_simulation_id,
            'section_id' => $runtimeWeek->sectionSimulation->section_id,
        ]);
        $definition = DecisionFormDefinition::factory()->create([
            'simulation_version_id' => $runtimeWeek->simulation_version_id,
            'simulation_week_id' => $runtimeWeek->simulation_week_id,
        ]);

        return [
            'tenant_id' => $runtimeWeek->tenant_id,
            'section_simulation_id' => $runtimeWeek->section_simulation_id,
            'section_simulation_week_id' => $runtimeWeek->id,
            'team_simulation_id' => $teamSimulation->id,
            'team_id' => $teamSimulation->team_id,
            'decision_form_definition_id' => $definition->id,
            'status' => SubmissionStatus::Draft,
            'answers' => [],
            'lock_version' => 0,
        ];
    }
}
