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
        $this->assertSame('specification_ready_package_missing', $manifest['status']);
        $this->assertSame([
            'opec_compliance_history',
            'current_cut_characteristics',
            'price_propagation_reference',
            'segment_position',
            'hedge_book_balance_sheet',
            'worked_example_prior',
        ], $manifest['expected_datasets']);
        $this->assertCount(12, $artifacts);
        $this->assertSame('student', $artifacts[0]['visibility']);
        $this->assertSame('shared', $artifacts[2]['visibility']);
        $this->assertSame('solution', $artifacts[9]['visibility']);
    }

    public function test_week8_package_registration_currently_records_missing_artifacts(): void
    {
        $package = app(Week8ContentPackageRegistrationService::class)->register(
            $this->week(8),
            'week8-discovery-v1',
        );

        $this->assertSame(SimulationContentPackage::STATUS_INVALID, $package->status);
        $this->assertSame(12, $package->artifacts()->count());
        $this->assertSame(12, $package->artifacts()->where('is_missing', true)->count());
        $this->assertStringContainsString('week8_student_workbook', $package->validation_summary['errors'][0]);
    }

    public function test_invalid_week8_package_cannot_activate(): void
    {
        $package = app(Week8ContentPackageRegistrationService::class)->register(
            $this->week(8),
            'week8-invalid-v1',
        );

        $this->expectException(InvalidArgumentException::class);

        app(SimulationContentActivationService::class)->activate($package);
    }

    public function test_week8_package_registration_rejects_wrong_week(): void
    {
        $this->expectException(InvalidArgumentException::class);

        app(Week8ContentPackageRegistrationService::class)->register($this->week(7), 'week8-wrong-week-v1');
    }

    public function test_repository_contains_no_week8_reference_package_yet(): void
    {
        $this->assertFileDoesNotExist(base_path('halden-week8-data-package/MANIFEST.md'));
        $this->assertFileDoesNotExist(base_path('halden-week8-data-package/fixtures/week8_golden.json'));
        $this->assertFileDoesNotExist(base_path('halden-week8-data-package/halden_week8.xlsx'));
        $this->assertFileDoesNotExist(base_path('halden-week8-data-package/halden_week8_analysis.ipynb'));
    }

    private function week(int $weekNumber): SimulationWeek
    {
        $structure = $this->simulationStructure(max(8, $weekNumber));

        /** @var SimulationWeek $week */
        $week = $structure['simulationWeeks']->firstWhere('week_number', $weekNumber);

        return $week;
    }
}
