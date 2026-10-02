<?php

namespace Tests\Feature\Content;

use App\Domain\Content\AuthoritativePackages\AuthoritativeContentPackageManifest;
use App\Domain\Content\AuthoritativePackages\AuthoritativeContentPackageRegistrationService;
use App\Domain\Content\SimulationContentActivationService;
use App\Domain\Content\SimulationContentResolver;
use App\Domain\Simulation\SimulationLifecycleService;
use App\Enums\SectionSimulationWeekStatus;
use App\Models\ContentArtifact;
use App\Models\SectionSimulationWeek;
use App\Models\SimulationContentPackage;
use App\Models\SimulationWeek;
use Illuminate\Foundation\Testing\RefreshDatabase;
use InvalidArgumentException;
use Tests\CreatesFoundationData;
use Tests\TestCase;

class AuthoritativePackageBatchIngestionTest extends TestCase
{
    use CreatesFoundationData;
    use RefreshDatabase;

    public function test_all_approved_authoritative_packages_can_be_registered(): void
    {
        $structure = $this->simulationStructure(14);
        $registered = [];

        foreach (AuthoritativeContentPackageManifest::REGISTRABLE_WEEKS as $weekNumber) {
            $package = app(AuthoritativeContentPackageRegistrationService::class)
                ->register($this->week($structure, $weekNumber));

            $registered[] = $weekNumber;

            $this->assertSame(SimulationContentPackage::STATUS_VALIDATED, $package->status);
            $this->assertSame($weekNumber, $package->manifest['week_number']);
            $this->assertSame('authoritative_package_available', $package->manifest['status']);
            $this->assertSame(
                AuthoritativeContentPackageManifest::GOLDEN_RELATIVE_TOLERANCE,
                $package->manifest['golden_tolerance']['relative'],
            );
            $this->assertSame(
                AuthoritativeContentPackageManifest::GOLDEN_ABSOLUTE_TOLERANCE,
                $package->manifest['golden_tolerance']['absolute'],
            );
            $this->assertSame(0, $package->artifacts()->where('is_missing', true)->count());
            $this->assertGreaterThanOrEqual(11, $package->artifacts()->count());
        }

        $this->assertSame([1, 2, 3, 5, 6, 7, 8, 9, 10, 11, 12, 13], $registered);
    }

    public function test_provenance_hashes_are_preserved_on_registered_artifacts(): void
    {
        $package = app(AuthoritativeContentPackageRegistrationService::class)
            ->register($this->week($this->simulationStructure(14), 1));

        /** @var ContentArtifact $golden */
        $golden = $package->artifacts()
            ->where('artifact_key', 'week1_fixtures_week1_golden_json')
            ->firstOrFail();

        $this->assertSame(
            hash_file('sha256', base_path('halden-week1-data-package/fixtures/week1_golden.json')),
            $golden->checksum,
        );
        $this->assertSame($golden->checksum, $golden->actual_checksum);
    }

    public function test_invalid_bulk_package_cannot_activate(): void
    {
        $package = app(AuthoritativeContentPackageRegistrationService::class)->register(
            $this->week($this->simulationStructure(14), 1),
            pathOverrides: ['week1_halden_week1_xlsx' => 'missing/week1.xlsx'],
        );

        $this->assertSame(SimulationContentPackage::STATUS_INVALID, $package->status);

        $this->expectException(InvalidArgumentException::class);

        app(SimulationContentActivationService::class)->activate($package);
    }

    public function test_authoritative_package_versions_are_immutable(): void
    {
        $package = app(AuthoritativeContentPackageRegistrationService::class)
            ->register($this->week($this->simulationStructure(14), 2));

        $this->expectException(InvalidArgumentException::class);

        $package->update(['version' => 'changed']);
    }

    public function test_student_and_faculty_visibility_is_enforced_for_registered_package_artifacts(): void
    {
        $graph = $this->tenantGraph('PackageVisibility');
        $runtimeWeek = $this->runtimeWeek($graph, 1);
        $package = app(AuthoritativeContentPackageRegistrationService::class)
            ->register($runtimeWeek->definition);

        app(SimulationContentActivationService::class)->activate($package);

        $resolver = app(SimulationContentResolver::class);
        $packageType = app(AuthoritativeContentPackageManifest::class)->packageType(1);
        $studentArtifacts = $resolver->authorizedArtifactsFor($graph['student'], $runtimeWeek, $packageType);
        $facultyArtifacts = $resolver->authorizedArtifactsFor($graph['faculty'], $runtimeWeek, $packageType);

        $this->assertTrue($studentArtifacts->contains('artifact_key', 'week1_halden_week1_xlsx'));
        $this->assertTrue($studentArtifacts->contains('artifact_key', 'week1_data_benchmarks_csv'));
        $this->assertFalse($studentArtifacts->contains('artifact_key', 'week1_fixtures_week1_golden_json'));
        $this->assertFalse($studentArtifacts->contains('artifact_key', 'week1_faculty_halden_week1_faculty_solution_xlsx'));

        $this->assertTrue($facultyArtifacts->contains('artifact_key', 'week1_fixtures_week1_golden_json'));
        $this->assertTrue($facultyArtifacts->contains('artifact_key', 'week1_faculty_halden_week1_faculty_solution_xlsx'));
    }

    public function test_week10_upgraded_package_can_register_and_activate(): void
    {
        $runtimeWeek = $this->runtimeWeek($this->tenantGraph('Week10Package'), 10);
        $package = app(AuthoritativeContentPackageRegistrationService::class)
            ->register($runtimeWeek->definition);
        $activation = app(SimulationContentActivationService::class)->activate($package);

        $this->assertSame(SimulationContentPackage::STATUS_VALIDATED, $package->status);
        $this->assertSame(10, $package->manifest['week_number']);
        $this->assertSame(
            'week10_fixtures_week10_golden_json',
            $package->artifacts()->where('artifact_type', 'expected_outputs')->firstOrFail()->artifact_key,
        );
        $this->assertSame($package->id, $activation->simulation_content_package_id);
    }

    public function test_week12_revised_package_can_register_and_activate(): void
    {
        $runtimeWeek = $this->runtimeWeek($this->tenantGraph('Week12Package'), 12);
        $package = app(AuthoritativeContentPackageRegistrationService::class)
            ->register($runtimeWeek->definition);
        $activation = app(SimulationContentActivationService::class)->activate($package);

        $this->assertSame(SimulationContentPackage::STATUS_VALIDATED, $package->status);
        $this->assertSame(12, $package->manifest['week_number']);
        $this->assertSame(
            'week12_fixtures_week12_golden_json',
            $package->artifacts()->where('artifact_type', 'expected_outputs')->firstOrFail()->artifact_key,
        );
        $this->assertSame($package->id, $activation->simulation_content_package_id);
    }

    public function test_week4_stable_reference_package_is_left_out_of_bulk_registration(): void
    {
        $this->assertFileExists(base_path('halden-week4-data-package/MANIFEST.md'));
        $this->assertFileExists(base_path('halden-week4-data-package/expected/week4_reference.json'));

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Week 4 remains the stable golden baseline');

        app(AuthoritativeContentPackageRegistrationService::class)
            ->register($this->week($this->simulationStructure(14), 4));
    }

    private function runtimeWeek(array $graph, int $weekNumber): SectionSimulationWeek
    {
        $structure = $this->simulationStructure(14);
        $sectionSimulation = $this->assignSimulation($graph, $structure['version']);
        $week = $this->week($structure, $weekNumber);

        /** @var SectionSimulationWeek $runtimeWeek */
        $runtimeWeek = $sectionSimulation->weeks()
            ->where('simulation_week_id', $week->id)
            ->firstOrFail();

        $runtimeWeek = app(SimulationLifecycleService::class)
            ->transitionWeek($runtimeWeek, SectionSimulationWeekStatus::Released, $graph['faculty']);

        return $runtimeWeek->refresh();
    }

    /**
     * @param  array<string, mixed>  $structure
     */
    private function week(array $structure, int $weekNumber): SimulationWeek
    {
        /** @var SimulationWeek $week */
        $week = $structure['simulationWeeks']->firstWhere('week_number', $weekNumber);

        return $week;
    }
}
