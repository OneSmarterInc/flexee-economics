<?php

namespace Tests\Feature\Content;

use App\Domain\Content\SimulationContentActivationService;
use App\Domain\Content\Week8\Week8ContentPackageManifest;
use App\Domain\Content\Week8\Week8ContentPackageRegistrationService;
use App\Models\SimulationContentPackage;
use App\Models\SimulationWeek;
use Illuminate\Foundation\Testing\RefreshDatabase;
use InvalidArgumentException;
use Tests\CreatesFoundationData;
use Tests\TestCase;

class Week8ReferencePackageDiscoveryTest extends TestCase
{
    use CreatesFoundationData;
    use RefreshDatabase;

    public function test_week8_manifest_declares_expected_package_structure(): void
    {
        $manifest = app(Week8ContentPackageManifest::class)->manifest('week8-v1');
        $artifacts = app(Week8ContentPackageManifest::class)->artifacts();

        $this->assertSame(Week8ContentPackageManifest::PACKAGE_TYPE, $manifest['package_type']);
        $this->assertSame(8, $manifest['week_number']);
        $this->assertSame('halden-week8-data-package', $manifest['package_root']);
        $this->assertSame('authoritative_package_available', $manifest['status']);
        $this->assertSame([
            'baseline_state',
            'compliance_history',
            'opec_scenarios',
            'propagation_coefficients',
            'worked_example_prior',
        ], $manifest['expected_datasets']);
        $this->assertCount(11, $artifacts);
        $this->assertSame('student', $artifacts[0]['visibility']);
        $this->assertSame('shared', $artifacts[2]['visibility']);
        $this->assertSame('solution', $artifacts[8]['visibility']);
        $this->assertSame('authoritative_package_available', $artifacts[0]['metadata']['source_status']);
    }

    public function test_week8_package_registration_validates_against_real_artifacts(): void
    {
        $package = app(Week8ContentPackageRegistrationService::class)->register(
            $this->week(8),
            'week8-authoritative-v1',
        );

        $this->assertSame(SimulationContentPackage::STATUS_VALIDATED, $package->status);
        $this->assertSame(11, $package->artifacts()->count());
        $this->assertSame(0, $package->artifacts()->where('is_missing', true)->count());
        $this->assertTrue($package->validation_summary['valid']);
        $this->assertSame([], $package->validation_summary['errors']);
        $this->assertSame(
            hash_file('sha256', base_path('halden-week8-data-package/fixtures/week8_golden.json')),
            $package->artifacts()->where('artifact_key', 'week8_expected_outputs')->firstOrFail()->checksum,
        );
    }

    public function test_valid_week8_package_can_activate(): void
    {
        $package = app(Week8ContentPackageRegistrationService::class)->register(
            $this->week(8),
            'week8-active-v1',
        );

        $activation = app(SimulationContentActivationService::class)->activate($package);

        $this->assertSame($package->id, $activation->simulation_content_package_id);
        $this->assertSame(Week8ContentPackageManifest::PACKAGE_TYPE, $activation->package_type);
    }

    public function test_week8_package_registration_rejects_wrong_week(): void
    {
        $this->expectException(InvalidArgumentException::class);

        app(Week8ContentPackageRegistrationService::class)->register($this->week(7), 'week8-wrong-week-v1');
    }

    public function test_week8_reference_package_is_present(): void
    {
        $this->assertFileExists(base_path('halden-week8-data-package/MANIFEST.md'));
        $this->assertFileExists(base_path('halden-week8-data-package/fixtures/week8_golden.json'));
        $this->assertFileExists(base_path('halden-week8-data-package/fixtures/provenance.json'));
        $this->assertFileExists(base_path('halden-week8-data-package/halden_week8.xlsx'));
        $this->assertFileExists(base_path('halden-week8-data-package/halden_week8_analysis.ipynb'));
    }

    private function week(int $weekNumber): SimulationWeek
    {
        $structure = $this->simulationStructure(max(8, $weekNumber));

        /** @var SimulationWeek $week */
        $week = $structure['simulationWeeks']->firstWhere('week_number', $weekNumber);

        return $week;
    }
}
