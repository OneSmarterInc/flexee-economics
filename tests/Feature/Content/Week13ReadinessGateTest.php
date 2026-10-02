<?php

namespace Tests\Feature\Content;

use App\Domain\Content\AuthoritativePackages\AuthoritativeContentPackageManifest;
use App\Domain\Content\AuthoritativePackages\AuthoritativeContentPackageRegistrationService;
use App\Domain\Content\SimulationContentActivationService;
use App\Domain\Content\SimulationContentResolver;
use App\Domain\Simulation\SimulationLifecycleService;
use App\Enums\SectionSimulationWeekStatus;
use App\Models\SectionSimulationWeek;
use App\Models\SimulationContentPackage;
use App\Models\SimulationWeek;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\CreatesFoundationData;
use Tests\TestCase;

class Week13ReadinessGateTest extends TestCase
{
    use CreatesFoundationData;
    use RefreshDatabase;

    public function test_week13_package_artifacts_and_provenance_hashes_are_validated(): void
    {
        $provenance = $this->week13Json('fixtures/provenance.json');
        $artifacts = $provenance['artifacts'];

        $requiredArtifacts = [
            'MANIFEST.md',
            'VALIDATION_16A.md',
            'halden_week13.xlsx',
            'halden_week13_analysis.ipynb',
            'data/norway_union.csv',
            'data/permian_labor.csv',
            'data/turnaround.csv',
            'data/wage_benchmarks.csv',
            'data/worked_example_labor.csv',
            'faculty/halden_week13_FACULTY_SOLUTION.ipynb',
            'faculty/halden_week13_FACULTY_SOLUTION.xlsx',
            'fixtures/week13_golden.json',
        ];

        foreach ($requiredArtifacts as $relativePath) {
            $this->assertArrayHasKey($relativePath, $artifacts);
            $absolutePath = $this->week13Path($relativePath);
            $this->assertFileExists($absolutePath);
            $this->assertSame($artifacts[$relativePath], hash_file('sha256', $absolutePath));
        }
    }

    public function test_week13_canonical_csvs_define_factor_market_inputs_without_missing_values(): void
    {
        $norway = $this->csvByKey('data/norway_union.csv', 'parameter');
        $permian = $this->csvByKey('data/permian_labor.csv', 'parameter');
        $turnaround = $this->csvByKey('data/turnaround.csv', 'parameter');
        $wages = $this->csvByKey('data/wage_benchmarks.csv', 'market');

        $this->assertSame('420.0', $norway['wage_bill_musd']['value']);
        $this->assertSame('0.08', $norway['union_demand_pct']['value']);
        $this->assertSame('0.78', $norway['tax_rate']['value']);

        $this->assertSame('9000.0', $permian['bbl_per_marginal_worker']['value']);
        $this->assertSame('56.4', $permian['margin_per_bbl']['value']);
        $this->assertSame('145.0', $permian['market_wage_k']['value']);

        $this->assertSame('60.0', $turnaround['labor_cost_base_musd']['value']);
        $this->assertSame('1.35', $turnaround['peak_multiplier']['value']);
        $this->assertSame('0.12', $turnaround['outage_probability_if_delayed']['value']);
        $this->assertSame('150.0', $turnaround['outage_cost_musd']['value']);
        $this->assertSame('3.0', $turnaround['asset_health_penalty_pts']['value']);

        $this->assertSame('bilateral monopoly', $wages['Norwegian offshore']['structure']);
        $this->assertSame('competitive, tight', $wages['Permian field']['structure']);
        $this->assertSame('competitive spot', $wages['Gulf Coast contractor']['structure']);
    }

    public function test_week13_golden_fixture_declares_expected_outputs_and_ordering_assertions(): void
    {
        $golden = $this->week13Json('fixtures/week13_golden.json');
        $results = $golden['results'];

        $this->assertSame(13, $golden['meta']['week']);
        $this->assertSame(0.001, $golden['meta']['tolerance']['rel']);
        $this->assertSame(1e-05, $golden['meta']['tolerance']['abs']);
        $this->assertSame('1.0.0-draft', $golden['meta']['package_version']);

        $this->assertSame(33.6, $results['norway_gross_cost_musd']);
        $this->assertSame(7.392, $results['norway_after_tax_cost_musd']);
        $this->assertSame(0.22, $results['norway_after_tax_share']);
        $this->assertSame(507.6, $results['permian_mrp_k']);
        $this->assertSame(3.50069, $results['mrp_to_wage']);
        $this->assertSame(81.0, $results['turnaround_peak_cost_musd']);
        $this->assertSame(78.0, $results['delay_expected_cost_musd']);
        $this->assertSame(3.0, $results['delay_saving_musd']);
        $this->assertSame(3.0, $results['asset_health_penalty_pts']);

        $this->assertSame(50000.0, $golden['worked_example']['mrp']);
        $this->assertSame(10000.0, $golden['worked_example']['mrp_minus_wage']);
        $this->assertSame(50.0, $golden['worked_example']['after_tax_wage_increase']);

        foreach ($golden['ordering_assertions'] as $assertion) {
            $this->assertTrue($assertion['holds'], $assertion['assert']);
        }
    }

    public function test_week13_package_can_activate_and_resolve_authorized_artifacts(): void
    {
        $graph = $this->tenantGraph('Week13Readiness');
        $runtimeWeek = $this->runtimeWeek($graph, 13);
        $package = app(AuthoritativeContentPackageRegistrationService::class)
            ->register($runtimeWeek->definition);

        $activation = app(SimulationContentActivationService::class)->activate($package);

        $this->assertSame(SimulationContentPackage::STATUS_VALIDATED, $package->status);
        $this->assertSame(13, $package->manifest['week_number']);
        $this->assertSame('authoritative_week13_reference_package', $package->package_type);
        $this->assertSame($package->id, $activation->simulation_content_package_id);
        $this->assertSame(0, $package->artifacts()->where('is_missing', true)->count());

        $resolver = app(SimulationContentResolver::class);
        $studentArtifacts = $resolver->authorizedArtifactsFor(
            $graph['student'],
            $runtimeWeek,
            app(AuthoritativeContentPackageManifest::class)->packageType(13),
        );
        $facultyArtifacts = $resolver->authorizedArtifactsFor(
            $graph['faculty'],
            $runtimeWeek,
            app(AuthoritativeContentPackageManifest::class)->packageType(13),
        );

        $this->assertTrue($studentArtifacts->contains('artifact_key', 'week13_halden_week13_xlsx'));
        $this->assertTrue($studentArtifacts->contains('artifact_key', 'week13_data_turnaround_csv'));
        $this->assertFalse($studentArtifacts->contains('artifact_key', 'week13_fixtures_week13_golden_json'));
        $this->assertFalse($studentArtifacts->contains('artifact_key', 'week13_faculty_halden_week13_faculty_solution_xlsx'));

        $this->assertTrue($facultyArtifacts->contains('artifact_key', 'week13_fixtures_week13_golden_json'));
        $this->assertTrue($facultyArtifacts->contains('artifact_key', 'week13_faculty_halden_week13_faculty_solution_xlsx'));
    }

    /**
     * @param  array<string, mixed>  $graph
     */
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

    /**
     * @return array<string, mixed>
     */
    private function week13Json(string $relativePath): array
    {
        $decoded = json_decode((string) file_get_contents($this->week13Path($relativePath)), true, flags: JSON_THROW_ON_ERROR);

        $this->assertIsArray($decoded);

        return $decoded;
    }

    /**
     * @return array<string, array<string, string>>
     */
    private function csvByKey(string $relativePath, string $key): array
    {
        $handle = fopen($this->week13Path($relativePath), 'rb');
        $this->assertIsResource($handle);

        $headers = fgetcsv($handle);
        $this->assertIsArray($headers);

        $rows = [];
        while (($row = fgetcsv($handle)) !== false) {
            $record = array_combine($headers, $row);
            $this->assertIsArray($record);
            $rows[(string) $record[$key]] = array_map('strval', $record);
        }

        fclose($handle);

        return $rows;
    }

    private function week13Path(string $relativePath): string
    {
        return base_path('halden-week13-data-package/'.$relativePath);
    }
}
