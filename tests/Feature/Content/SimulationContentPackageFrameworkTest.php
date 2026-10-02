<?php

namespace Tests\Feature\Content;

use App\Domain\Content\ContentPackageValidator;
use App\Domain\Content\SimulationContentPackageService;
use App\Models\SimulationContentPackage;
use App\Models\SimulationWeek;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use InvalidArgumentException;
use Tests\CreatesFoundationData;
use Tests\TestCase;

class SimulationContentPackageFrameworkTest extends TestCase
{
    use CreatesFoundationData;
    use RefreshDatabase;

    public function test_package_registers_manifest_and_artifact_hashes(): void
    {
        $week = $this->week();
        $artifactPath = 'docs/BATCH10A_IMPLEMENTATION.md';
        $checksum = hash_file('sha256', base_path($artifactPath));

        $package = app(SimulationContentPackageService::class)->register(
            $week,
            'reference_package',
            'week6-framework-v1',
            ['week' => 6, 'package' => 'framework-only'],
            [[
                'artifact_key' => 'batch10a-doc',
                'artifact_type' => 'documentation',
                'visibility' => 'faculty',
                'path_reference' => $artifactPath,
                'checksum' => $checksum,
                'version' => 'v1',
            ]],
        );

        $artifact = $package->artifacts()->firstOrFail();

        $this->assertSame(SimulationContentPackage::STATUS_VALIDATED, $package->status);
        $this->assertSame($week->simulation_version_id, $package->simulation_version_id);
        $this->assertSame($week->id, $package->simulation_week_id);
        $this->assertSame($checksum, $artifact->checksum);
        $this->assertSame($checksum, $artifact->actual_checksum);
        $this->assertFalse($artifact->is_missing);
    }

    public function test_manifest_hash_is_deterministic(): void
    {
        $validator = app(ContentPackageValidator::class);

        $first = $validator->hash(['b' => 2, 'a' => ['d' => 4, 'c' => 3]]);
        $second = $validator->hash(['a' => ['c' => 3, 'd' => 4], 'b' => 2]);

        $this->assertSame($first, $second);
        $this->assertMatchesRegularExpression('/^[a-f0-9]{64}$/', $first);
    }

    public function test_missing_artifacts_are_recorded_as_invalid_package_state(): void
    {
        $week = $this->week();

        $package = app(SimulationContentPackageService::class)->register(
            $week,
            'reference_package',
            'missing-artifact-v1',
            ['week' => 6],
            [[
                'artifact_key' => 'missing-week6-cashflows',
                'artifact_type' => 'csv',
                'path_reference' => 'missing/week6_cashflows.csv',
                'version' => 'v1',
            ]],
        );

        $artifact = $package->artifacts()->firstOrFail();

        $this->assertSame(SimulationContentPackage::STATUS_INVALID, $package->status);
        $this->assertTrue($artifact->is_missing);
        $this->assertStringContainsString('missing-week6-cashflows', $package->validation_summary['errors'][0]);
    }

    public function test_package_versions_are_immutable(): void
    {
        $package = $this->validPackage('immutable-v1');

        $this->expectException(InvalidArgumentException::class);

        $package->update(['status' => 'draft']);
    }

    public function test_artifact_records_are_immutable(): void
    {
        $artifact = $this->validPackage('immutable-artifact-v1')->artifacts()->firstOrFail();

        $this->expectException(InvalidArgumentException::class);

        $artifact->update(['checksum' => str_repeat('0', 64)]);
    }

    public function test_duplicate_package_version_is_rejected(): void
    {
        $week = $this->week();
        $this->validPackage('duplicate-v1', $week);

        $this->expectException(QueryException::class);

        $this->validPackage('duplicate-v1', $week);
    }

    public function test_package_must_match_simulation_week_version(): void
    {
        $first = $this->week();
        $second = $this->week();

        $this->expectException(InvalidArgumentException::class);

        SimulationContentPackage::query()->create([
            'simulation_version_id' => $first->simulation_version_id,
            'simulation_week_id' => $second->id,
            'package_type' => 'reference_package',
            'version' => 'wrong-version-v1',
            'manifest' => ['week' => 6],
            'manifest_hash' => str_repeat('a', 64),
            'status' => SimulationContentPackage::STATUS_VALIDATED,
            'validation_summary' => ['valid' => true, 'errors' => []],
            'validated_at' => now(),
        ]);
    }

    public function test_content_packages_are_platform_scoped_not_tenant_scoped(): void
    {
        $package = $this->validPackage('platform-scope-v1');

        $this->assertFalse(Schema::hasColumn('simulation_content_packages', 'tenant_id'));
        $this->assertFalse(Schema::hasColumn('content_artifacts', 'tenant_id'));
        $this->assertSame($package->week->simulation_version_id, $package->simulation_version_id);
    }

    private function validPackage(string $version, ?SimulationWeek $week = null): SimulationContentPackage
    {
        $week ??= $this->week();
        $artifactPath = 'docs/BATCH10A_IMPLEMENTATION.md';

        return app(SimulationContentPackageService::class)->register(
            $week,
            'reference_package',
            $version,
            ['week' => $week->week_number, 'package' => $version],
            [[
                'artifact_key' => 'batch10a-doc',
                'artifact_type' => 'documentation',
                'path_reference' => $artifactPath,
                'checksum' => hash_file('sha256', base_path($artifactPath)),
                'version' => 'v1',
            ]],
        );
    }

    private function week(): SimulationWeek
    {
        $structure = $this->simulationStructure(6);

        /** @var SimulationWeek $week */
        $week = $structure['simulationWeeks']->firstWhere('week_number', 6);

        return $week;
    }
}
