<?php

namespace Database\Factories;

use App\Models\AuditEvent;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<AuditEvent>
 */
class AuditEventFactory extends Factory
{
    public function definition(): array
    {
        $tenant = Tenant::factory()->create();

        return [
            'tenant_id' => $tenant->id,
            'actor_user_id' => User::factory()->create(['tenant_id' => $tenant->id])->id,
            'action' => 'simulation.test',
            'auditable_type' => 'test',
            'auditable_id' => 1,
            'before_state' => null,
            'after_state' => [],
            'metadata' => [],
            'occurred_at' => now(),
        ];
    }
}
