<?php

namespace App\Domain\Content;

use App\Models\ContentArtifact;
use App\Models\SimulationContentPackage;
use App\Models\SimulationWeek;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use JsonException;

final readonly class SimulationContentPackageService
{
    public function __construct(
        private ContentPackageValidator $validator,
    ) {}

    /**
     * @param  array<string, mixed>  $manifest
     * @param  list<array<string, mixed>>  $artifacts
     *
     * @throws JsonException
     */
    public function register(
        SimulationWeek $week,
        string $packageType,
        string $version,
        array $manifest,
        array $artifacts,
    ): SimulationContentPackage {
        $result = $this->validator->validate($manifest, $artifacts);

        return DB::transaction(function () use ($week, $packageType, $version, $manifest, $result): SimulationContentPackage {
            $package = SimulationContentPackage::query()->create([
                'simulation_version_id' => $week->simulation_version_id,
                'simulation_week_id' => $week->id,
                'package_type' => $packageType,
                'version' => $version,
                'manifest' => $manifest,
                'manifest_hash' => $result->manifestHash,
                'status' => $result->valid ? SimulationContentPackage::STATUS_VALIDATED : SimulationContentPackage::STATUS_INVALID,
                'validation_summary' => [
                    'valid' => $result->valid,
                    'errors' => $result->errors,
                ],
                'validated_at' => Carbon::now(),
            ]);

            foreach ($result->artifacts as $artifact) {
                ContentArtifact::query()->create([
                    'simulation_content_package_id' => $package->id,
                    'artifact_key' => $artifact['artifact_key'],
                    'artifact_type' => (string) ($artifact['artifact_type'] ?? 'unknown'),
                    'visibility' => $artifact['visibility'] ?? null,
                    'path_reference' => $artifact['path_reference'],
                    'checksum_algorithm' => $artifact['checksum_algorithm'],
                    'checksum' => $artifact['checksum'],
                    'actual_checksum' => $artifact['actual_checksum'],
                    'version' => $artifact['version'] ?? null,
                    'is_missing' => $artifact['is_missing'],
                    'metadata' => $artifact['metadata'] ?? [],
                ]);
            }

            return $package;
        });
    }
}
