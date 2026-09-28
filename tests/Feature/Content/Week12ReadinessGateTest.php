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

class Week12ReadinessGateTest extends TestCase
{
    use CreatesFoundationData;
    use RefreshDatabase;

    public function test_week12_package_artifacts_and_provenance_hashes_are_validated(): void
    {
        $provenance = $this->week12Json('fixtures/provenance.json');
        $artifacts = $provenance['artifacts'];

        $requiredArtifacts = [
            'MANIFEST.md',
            'VALIDATION_16A.md',
            'halden_week12.xlsx',
            'halden_week12_analysis.ipynb',
            'data/buckets.csv',
            'data/carbon_scenarios.csv',
            'data/demand_scenarios.csv',
            'data/envelope.csv',
            'data/projects.csv',
            'data/worked_example_projects.csv',
            'faculty/halden_week12_FACULTY_SOLUTION.ipynb',
            'faculty/halden_week12_FACULTY_SOLUTION.xlsx',
            'fixtures/week12_golden.json',
        ];

        foreach ($requiredArtifacts as $relativePath) {
            $this->assertArrayHasKey($relativePath, $artifacts);
            $absolutePath = $this->week12Path($relativePath);
            $this->assertFileExists($absolutePath);
            $this->assertSame($artifacts[$relativePath], hash_file('sha256', $absolutePath));
        }
    }

    public function test_week12_helix_bucket_conflict_is_resolved_by_canonical_package_data(): void
    {
        $buckets = $this->csvByKey('data/buckets.csv', 'bucket');
        $projects = $this->csvByKey('data/projects.csv', 'project');
        $envelope = $this->csvByKey('data/envelope.csv', 'parameter');
        $golden = $this->week12Json('fixtures/week12_golden.json');

        $helixCost = $this->decimal($projects['helix_rotterdam']['cost_musd']);
        $adjacentCeiling = $this->decimal($buckets['adjacent']['ceiling']);
        $divestmentProceeds = abs($this->decimal($projects['euro_retail_divest']['cost_musd']));
        $discretionary = $this->decimal($envelope['total_envelope']['value'])
            - $this->decimal($envelope['sustaining_floor']['value']);

        $this->assertSame(1200.0, $helixCost);
        $this->assertSame(1200.0, $adjacentCeiling);
        $this->assertLessThanOrEqual($adjacentCeiling, $helixCost);
        $this->assertSame(550.0, $divestmentProceeds);
        $this->assertSame(1200.0, $discretionary);

        $results = $golden['results'];
        $this->assertSame(1200.0, $results['helix_rotterdam_cost']);
        $this->assertSame(1200.0, $results['adjacent_ceiling']);
        $this->assertSame(1750.0, $results['hr_plus_wind_cost']);
        $this->assertSame(1750.0, $results['envelope_with_divest']);
        $this->assertTrue($results['hr_plus_wind_needs_divest']);
        $this->assertSame(17, $results['feasible_portfolios']);
        $this->assertSame(3, $results['feasible_with_helix_rotterdam']);
        $this->assertSame(2, $results['portfolios_unlocked_by_divest']);
    }

    public function test_week12_golden_fixture_declares_tolerances_and_passed_ordering_assertions(): void
    {
        $golden = $this->week12Json('fixtures/week12_golden.json');

        $this->assertSame(0.001, $golden['meta']['tolerance']['rel']);
        $this->assertSame(1e-05, $golden['meta']['tolerance']['abs']);
        $this->assertSame('1.0.0-draft', $golden['meta']['package_version']);
        $this->assertSame(-50.0, $golden['worked_example']['p1_low']);
        $this->assertSame(90.0, $golden['worked_example']['p1_high']);
        $this->assertSame(150.0, $golden['worked_example']['p2_low']);
        $this->assertSame(-18.0, $golden['worked_example']['p2_high']);

        foreach ($golden['ordering_assertions'] as $assertion) {
            $this->assertTrue($assertion['holds'], $assertion['assert']);
        }
    }

    public function test_week12_revised_package_can_activate_and_resolve_authorized_artifacts(): void
    {
        $graph = $this->tenantGraph('Week12Readiness');
        $runtimeWeek = $this->runtimeWeek($graph, 12);
        $package = app(AuthoritativeContentPackageRegistrationService::class)
            ->register($runtimeWeek->definition);

        $activation = app(SimulationContentActivationService::class)->activate($package);

        $this->assertSame(SimulationContentPackage::STATUS_VALIDATED, $package->status);
        $this->assertSame(12, $package->manifest['week_number']);
        $this->assertSame('authoritative_week12_reference_package', $package->package_type);
        $this->assertSame($package->id, $activation->simulation_content_package_id);
        $this->assertSame(0, $package->artifacts()->where('is_missing', true)->count());

        $resolver = app(SimulationContentResolver::class);
        $studentArtifacts = $resolver->authorizedArtifactsFor(
            $graph['student'],
            $runtimeWeek,
            app(AuthoritativeContentPackageManifest::class)->packageType(12),
        );
        $facultyArtifacts = $resolver->authorizedArtifactsFor(
            $graph['faculty'],
            $runtimeWeek,
            app(AuthoritativeContentPackageManifest::class)->packageType(12),
        );

        $this->assertTrue($studentArtifacts->contains('artifact_key', 'week12_halden_week12_xlsx'));
        $this->assertTrue($studentArtifacts->contains('artifact_key', 'week12_data_projects_csv'));
        $this->assertFalse($studentArtifacts->contains('artifact_key', 'week12_fixtures_week12_golden_json'));
        $this->assertFalse($studentArtifacts->contains('artifact_key', 'week12_faculty_halden_week12_faculty_solution_xlsx'));

        $this->assertTrue($facultyArtifacts->contains('artifact_key', 'week12_fixtures_week12_golden_json'));
        $this->assertTrue($facultyArtifacts->contains('artifact_key', 'week12_faculty_halden_week12_faculty_solution_xlsx'));
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
    private function week12Json(string $relativePath): array
    {
        $decoded = json_decode((string) file_get_contents($this->week12Path($relativePath)), true, flags: JSON_THROW_ON_ERROR);

        $this->assertIsArray($decoded);

        return $decoded;
    }

    /**
     * @return array<string, array<string, string>>
     */
    private function csvByKey(string $relativePath, string $key): array
    {
        $handle = fopen($this->week12Path($relativePath), 'rb');
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

    private function week12Path(string $relativePath): string
    {
        return base_path('halden-week12-data-package/'.$relativePath);
    }

    private function decimal(string $value): float
    {
        return (float) $value;
    }
}
