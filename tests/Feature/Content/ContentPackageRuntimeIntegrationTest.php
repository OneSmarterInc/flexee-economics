<?php

namespace Tests\Feature\Content;

use App\Domain\Content\SimulationContentActivationService;
use App\Domain\Content\SimulationContentPackageService;
use App\Domain\Content\SimulationContentResolver;
use App\Domain\Simulation\SimulationLifecycleService;
use App\Enums\SectionSimulationWeekStatus;
use App\Models\SectionSimulationWeek;
use App\Models\SimulationContentActivation;
use App\Models\SimulationContentPackage;
use App\Models\SimulationWeek;
use Illuminate\Foundation\Testing\RefreshDatabase;
use InvalidArgumentException;
use Tests\CreatesFoundationData;
use Tests\TestCase;

class ContentPackageRuntimeIntegrationTest extends TestCase
{
    use CreatesFoundationData;
    use RefreshDatabase;

    public function test_invalid_package_cannot_activate(): void
    {
        $week = $this->week();
        $package = app(SimulationContentPackageService::class)->register(
            $week,
            'reference_package',
            'invalid-v1',
            ['week' => 6],
            [[
                'artifact_key' => 'missing',
                'artifact_type' => 'csv',
                'visibility' => 'student',
                'path_reference' => 'missing/week6.csv',
                'version' => 'v1',
            ]],
        );

        $this->expectException(InvalidArgumentException::class);

        app(SimulationContentActivationService::class)->activate($package);
    }

    public function test_valid_package_activates_and_resolves_for_runtime_week(): void
    {
        $graph = $this->tenantGraph('A');
        $runtimeWeek = $this->runtimeWeek($graph);
        $package = $this->validPackage($runtimeWeek->definition);

        $activation = app(SimulationContentActivationService::class)->activate($package);
        $resolved = app(SimulationContentResolver::class)->activePackageFor($runtimeWeek);

        $this->assertSame(SimulationContentActivation::STATUS_ACTIVE, $activation->status);
        $this->assertSame($package->id, $resolved->id);
    }

    public function test_student_sees_only_student_and_shared_artifacts(): void
    {
        $graph = $this->tenantGraph('A');
        $runtimeWeek = $this->runtimeWeek($graph);
        $package = $this->validPackage($runtimeWeek->definition);
        app(SimulationContentActivationService::class)->activate($package);

        $artifacts = app(SimulationContentResolver::class)
            ->authorizedArtifactsFor($graph['student'], $runtimeWeek);

        $this->assertSame(['shared-guide', 'student-brief'], $artifacts->pluck('artifact_key')->sort()->values()->all());
        $this->assertNotContains('faculty-solution', $artifacts->pluck('artifact_key')->all());
    }

    public function test_faculty_sees_student_faculty_solution_and_shared_artifacts(): void
    {
        $graph = $this->tenantGraph('A');
        $runtimeWeek = $this->runtimeWeek($graph);
        $package = $this->validPackage($runtimeWeek->definition);
        app(SimulationContentActivationService::class)->activate($package);

        $artifacts = app(SimulationContentResolver::class)
            ->authorizedArtifactsFor($graph['faculty'], $runtimeWeek);

        $this->assertSame(
            ['faculty-solution', 'shared-guide', 'student-brief'],
            $artifacts->pluck('artifact_key')->sort()->values()->all(),
        );
    }

    public function test_cross_tenant_student_cannot_resolve_artifacts(): void
    {
        $first = $this->tenantGraph('A');
        $second = $this->tenantGraph('B');
        $runtimeWeek = $this->runtimeWeek($second);
        $package = $this->validPackage($runtimeWeek->definition);
        app(SimulationContentActivationService::class)->activate($package);

        $this->expectException(InvalidArgumentException::class);

        app(SimulationContentResolver::class)->authorizedArtifactsFor($first['student'], $runtimeWeek);
    }

    public function test_content_activation_is_immutable(): void
    {
        $graph = $this->tenantGraph('A');
        $runtimeWeek = $this->runtimeWeek($graph);
        $package = $this->validPackage($runtimeWeek->definition);
        $activation = app(SimulationContentActivationService::class)->activate($package);

        $this->expectException(InvalidArgumentException::class);

        $activation->update(['status' => SimulationContentActivation::STATUS_RETIRED]);
    }

    public function test_second_active_package_for_same_week_and_type_is_rejected(): void
    {
        $graph = $this->tenantGraph('A');
        $runtimeWeek = $this->runtimeWeek($graph);
        app(SimulationContentActivationService::class)->activate($this->validPackage($runtimeWeek->definition, 'valid-v1'));

        $this->expectException(InvalidArgumentException::class);

        app(SimulationContentActivationService::class)->activate($this->validPackage($runtimeWeek->definition, 'valid-v2'));
    }

    private function validPackage(SimulationWeek $week, string $version = 'valid-v1'): SimulationContentPackage
    {
        $doc = 'docs/BATCH11A_IMPLEMENTATION.md';

        return app(SimulationContentPackageService::class)->register(
            $week,
            'reference_package',
            $version,
            ['week' => $week->week_number, 'package' => $version],
            [
                [
                    'artifact_key' => 'student-brief',
                    'artifact_type' => 'briefing',
                    'visibility' => 'student',
                    'path_reference' => $doc,
                    'checksum' => hash_file('sha256', base_path($doc)),
                    'version' => 'student-v1',
                ],
                [
                    'artifact_key' => 'faculty-solution',
                    'artifact_type' => 'solution',
                    'visibility' => 'solution',
                    'path_reference' => $doc,
                    'checksum' => hash_file('sha256', base_path($doc)),
                    'version' => 'faculty-v1',
                ],
                [
                    'artifact_key' => 'shared-guide',
                    'artifact_type' => 'guide',
                    'visibility' => 'shared',
                    'path_reference' => $doc,
                    'checksum' => hash_file('sha256', base_path($doc)),
                    'version' => 'shared-v1',
                ],
            ],
        );
    }

    private function runtimeWeek(array $graph): SectionSimulationWeek
    {
        $structure = $this->simulationStructure(6);
        $sectionSimulation = $this->assignSimulation($graph, $structure['version']);
        /** @var SimulationWeek $weekDefinition */
        $weekDefinition = $structure['simulationWeeks']->firstWhere('week_number', 6);
        /** @var SectionSimulationWeek $runtimeWeek */
        $runtimeWeek = $sectionSimulation->weeks()->where('simulation_week_id', $weekDefinition->id)->firstOrFail();
        $lifecycle = app(SimulationLifecycleService::class);
        $runtimeWeek = $lifecycle->transitionWeek($runtimeWeek, SectionSimulationWeekStatus::Released, $graph['faculty']);

        return $runtimeWeek->refresh();
    }

    private function week(): SimulationWeek
    {
        $structure = $this->simulationStructure(6);

        /** @var SimulationWeek $week */
        $week = $structure['simulationWeeks']->firstWhere('week_number', 6);

        return $week;
    }
}
