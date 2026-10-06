<?php

namespace Database\Factories;

use App\Models\Enrollment;
use App\Models\Seat;
use App\Models\SimulationSeatAssignmentPeriod;
use App\Models\TeamMember;
use App\Models\TeamSimulation;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<SimulationSeatAssignmentPeriod>
 */
class SimulationSeatAssignmentPeriodFactory extends Factory
{
    public function definition(): array
    {
        $teamSimulation = TeamSimulation::factory()->create();
        $member = TeamMember::query()
            ->where('tenant_id', $teamSimulation->tenant_id)
            ->where('team_id', $teamSimulation->team_id)
            ->first();

        if (! $member) {
            $student = User::factory()->student()->create([
                'tenant_id' => $teamSimulation->tenant_id,
            ]);
            Enrollment::query()->create([
                'tenant_id' => $teamSimulation->tenant_id,
                'section_id' => $teamSimulation->section_id,
                'user_id' => $student->id,
                'status' => 'active',
            ]);
            $seat = Seat::factory()->create();
            $member = TeamMember::query()->create([
                'tenant_id' => $teamSimulation->tenant_id,
                'team_id' => $teamSimulation->team_id,
                'user_id' => $student->id,
                'seat_id' => $seat->id,
            ]);
        }

        return [
            'tenant_id' => $teamSimulation->tenant_id,
            'team_simulation_id' => $teamSimulation->id,
            'team_id' => $teamSimulation->team_id,
            'user_id' => $member->user_id,
            'seat_id' => $member->seat_id ?? Seat::factory()->create()->id,
            'role_phase' => null,
            'effective_from_week_number' => 1,
            'effective_until_week_number' => null,
            'source' => 'factory',
            'metadata' => [],
        ];
    }
}
