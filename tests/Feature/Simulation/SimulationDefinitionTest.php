<?php

namespace Tests\Feature\Simulation;

use App\Models\SectionSimulation;
use App\Models\SimulationVersion;
use App\Models\SimulationWeek;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use InvalidArgumentException;
use Tests\CreatesFoundationData;
use Tests\TestCase;

class SimulationDefinitionTest extends TestCase
{
    use CreatesFoundationData;
    use RefreshDatabase;

    public function test_simulation_definition_has_variant_version_weeks_and_content_versions(): void
    {
        $structure = $this->simulationStructure(3);

        $this->assertSame('Halden Energy', $structure['simulation']->name);
        $this->assertSame(3, $structure['variant']->duration_weeks);
        $this->assertCount(3, $structure['version']->weeks);
        $this->assertDatabaseHas('week_content_versions', [
            'simulation_week_id' => $structure['simulationWeeks']->first()->id,
            'version' => 'placeholder-v1',
        ]);
    }

    public function test_duplicate_week_numbers_are_rejected_inside_a_version(): void
    {
        $structure = $this->simulationStructure(1);
        $week = $structure['simulationWeeks']->first();

        $this->expectException(QueryException::class);

        SimulationWeek::factory()->create([
            'simulation_id' => $week->simulation_id,
            'simulation_variant_id' => $week->simulation_variant_id,
            'simulation_version_id' => $week->simulation_version_id,
            'week_number' => $week->week_number,
            'slug' => 'duplicate-week',
        ]);
    }

    public function test_published_versions_cannot_be_materially_modified(): void
    {
        $version = $this->simulationStructure(1)['version'];

        $this->expectException(InvalidArgumentException::class);

        $version->update(['config_hash' => 'new-hash']);
    }

    public function test_in_use_versions_cannot_be_materially_modified(): void
    {
        $graph = $this->tenantGraph('A');
        $version = $this->draftSimulationVersion();

        SectionSimulation::factory()->create([
            'tenant_id' => $graph['tenant']->id,
            'section_id' => $graph['section']->id,
            'simulation_id' => $version->simulation_id,
            'simulation_variant_id' => $version->simulation_variant_id,
            'simulation_version_id' => $version->id,
            'created_by_user_id' => $graph['faculty']->id,
        ]);

        $this->expectException(InvalidArgumentException::class);

        $version->update(['configuration' => ['changed' => true]]);
    }

    public function test_week_must_match_its_version_hierarchy(): void
    {
        $one = $this->simulationStructure(1);
        $two = $this->simulationStructure(1);

        $this->expectException(InvalidArgumentException::class);

        SimulationWeek::factory()->create([
            'simulation_id' => $one['simulation']->id,
            'simulation_variant_id' => $one['variant']->id,
            'simulation_version_id' => $two['version']->id,
        ]);
    }
}
