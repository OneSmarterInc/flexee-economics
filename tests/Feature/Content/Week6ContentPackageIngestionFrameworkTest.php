<?php

namespace Tests\Feature\Content;

use App\Domain\Content\SimulationContentActivationService;
use App\Domain\Content\SimulationContentResolver;
use App\Domain\Content\Week6\Week6ContentPackageManifest;
use App\Domain\Content\Week6\Week6ContentPackageRegistrationService;
use App\Domain\Simulation\SimulationLifecycleService;
use App\Enums\SectionSimulationWeekStatus;
use App\Models\SectionSimulationWeek;
use App\Models\SimulationContentPackage;
use App\Models\SimulationWeek;
use Illuminate\Foundation\Testing\RefreshDatabase;
use InvalidArgumentException;
use Tests\CreatesFoundationData;
use Tests\TestCase;

class Week6ContentPackageIngestionFrameworkTest extends TestCase
{
    use CreatesFoundationData;
    use RefreshDatabase;

    public function test_week6_manifest_declares_required_package_structure(): void
    {
        $manifest = app(Week6ContentPackageManifest::class)->manifest('week6-v1');
        $artifacts = app(Week6ContentPackageManifest::class)->artifacts();

        $this->assertSame(Week6ContentPackageManifest::PACKAGE_TYPE, $manifest['package_type']);
        $this->assertSame(6, $manifest['week_number']);
        $this->assertSame('halden-week6-data-package', $manifest['package_root']);
        $this->assertSame(['workbook', 'notebook', 'canonical_datasets'], $manifest['required_sections']['student']);
        $this->assertSame(['outputs', 'provenance'], $manifest['required_sections']['expected']);
        $this->assertCount(12, $artifacts);
        $this->assertSame('student', $artifacts[0]['visibility']);
        $this->assertSame('shared', $artifacts[2]['visibility']);
        $this->assertSame('solution', $artifacts[9]['visibility']);
    }

    public function test_authoritative_week6_package_validates_against_real_artifacts(): void
    {
        $week = $this->week(6);

        $package = app(Week6ContentPackageRegistrationService::class)->register($week, 'week6-authoritative-v1');

        $this->assertSame(SimulationContentPackage::STATUS_VALIDATED, $package->status);
        $this->assertSame(0, $package->artifacts()->where('is_missing', true)->count());
        $this->assertSame(12, $package->artifacts()->count());
        $this->assertSame(
            hash_file('sha256', base_path('halden-week6-data-package/fixtures/week6_golden.json')),
            $package->artifacts()->where('artifact_key', 'week6_expected_outputs')->firstOrFail()->actual_checksum,
        );
    }

    public function test_week6_registration_records_missing_artifacts_without_creating_fake_files(): void
    {
        $week = $this->week(6);

        $package = app(Week6ContentPackageRegistrationService::class)->register(
            $week,
            'week6-missing-v1',
            $this->missingPathOverrides(),
        );

        $this->assertSame(SimulationContentPackage::STATUS_INVALID, $package->status);
        $this->assertSame(12, $package->artifacts()->where('is_missing', true)->count());
        $this->assertStringContainsString('week6_student_workbook', $package->validation_summary['errors'][0]);
    }

    public function test_invalid_week6_package_cannot_activate(): void
    {
        $package = app(Week6ContentPackageRegistrationService::class)->register(
            $this->week(6),
            'week6-invalid-v1',
            $this->missingPathOverrides(),
        );

        $this->expectException(InvalidArgumentException::class);

        app(SimulationContentActivationService::class)->activate($package);
    }

    public function test_week6_package_registration_rejects_wrong_week(): void
    {
        $this->expectException(InvalidArgumentException::class);

        app(Week6ContentPackageRegistrationService::class)->register($this->week(5), 'week6-wrong-week-v1');
    }

    public function test_supplied_week6_package_can_activate_and_resolve_for_runtime_week(): void
    {
        $graph = $this->tenantGraph('A');
        $runtimeWeek = $this->runtimeWeek($graph);
        $package = app(Week6ContentPackageRegistrationService::class)->register(
            $runtimeWeek->definition,
            'week6-supplied-v1',
        );

        app(SimulationContentActivationService::class)->activate($package);
        $resolved = app(SimulationContentResolver::class)->activePackageFor(
            $runtimeWeek,
            Week6ContentPackageManifest::PACKAGE_TYPE,
        );

        $this->assertSame(SimulationContentPackage::STATUS_VALIDATED, $package->status);
        $this->assertSame($package->id, $resolved->id);
    }

    public function test_week6_artifact_authorization_uses_runtime_resolver(): void
    {
        $graph = $this->tenantGraph('A');
        $runtimeWeek = $this->runtimeWeek($graph);
        $package = app(Week6ContentPackageRegistrationService::class)->register(
            $runtimeWeek->definition,
            'week6-auth-v1',
        );
        app(SimulationContentActivationService::class)->activate($package);
        $resolver = app(SimulationContentResolver::class);

        $studentArtifacts = $resolver
            ->authorizedArtifactsFor($graph['student'], $runtimeWeek, Week6ContentPackageManifest::PACKAGE_TYPE)
            ->pluck('artifact_key')
            ->sort()
            ->values()
            ->all();
        $facultyArtifacts = $resolver
            ->authorizedArtifactsFor($graph['faculty'], $runtimeWeek, Week6ContentPackageManifest::PACKAGE_TYPE)
            ->pluck('artifact_key')
            ->sort()
            ->values()
            ->all();

        $this->assertContains('week6_project_cashflows', $studentArtifacts);
        $this->assertContains('week6_student_workbook', $studentArtifacts);
        $this->assertContains('week6_student_notebook', $studentArtifacts);
        $this->assertNotContains('week6_faculty_solution_workbook', $studentArtifacts);
        $this->assertNotContains('week6_expected_outputs', $studentArtifacts);
        $this->assertContains('week6_faculty_solution_workbook', $facultyArtifacts);
        $this->assertContains('week6_expected_outputs', $facultyArtifacts);
        $this->assertSame([
            'week6_cohort_discount_schedule',
            'week6_cost_of_capital',
            'week6_currency_helix',
            'week6_forecast_haircuts',
            'week6_manifest',
            'week6_project_cashflows',
            'week6_student_notebook',
            'week6_student_workbook',
            'week6_worked_example_prior',
        ], $studentArtifacts);
    }

    public function test_cross_tenant_week6_content_resolution_is_rejected(): void
    {
        $first = $this->tenantGraph('A');
        $second = $this->tenantGraph('B');
        $runtimeWeek = $this->runtimeWeek($second);
        $package = app(Week6ContentPackageRegistrationService::class)->register(
            $runtimeWeek->definition,
            'week6-cross-tenant-v1',
        );
        app(SimulationContentActivationService::class)->activate($package);

        $this->expectException(InvalidArgumentException::class);

        app(SimulationContentResolver::class)->authorizedArtifactsFor(
            $first['student'],
            $runtimeWeek,
            Week6ContentPackageManifest::PACKAGE_TYPE,
        );
    }

    /**
     * @return array<string, string>
     */
    private function missingPathOverrides(): array
    {
        return array_fill_keys(
            collect(app(Week6ContentPackageManifest::class)->artifacts())->pluck('artifact_key')->all(),
            'missing/week6-artifact-placeholder',
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

    private function week(int $weekNumber): SimulationWeek
    {
        $structure = $this->simulationStructure(max(6, $weekNumber));

        /** @var SimulationWeek $week */
        $week = $structure['simulationWeeks']->firstWhere('week_number', $weekNumber);

        return $week;
    }
}
