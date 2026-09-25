<?php

namespace App\Domain\Content\AuthoritativePackages;

use Illuminate\Support\Facades\File;
use InvalidArgumentException;

final class AuthoritativeContentPackageManifest
{
    public const PACKAGE_TYPE_PREFIX = 'authoritative_week';

    public const PACKAGE_VERSION = '1.0.0-draft';

    public const GOLDEN_RELATIVE_TOLERANCE = '1e-3';

    public const GOLDEN_ABSOLUTE_TOLERANCE = '1e-5';

    /**
     * @var list<int>
     */
    public const REGISTRABLE_WEEKS = [1, 2, 3, 5, 6, 7, 8, 9, 11, 13];

    /**
     * @var array<int, string>
     */
    public const EXCLUDED_WEEKS = [
        4 => 'Week 4 remains the stable golden baseline and is not migrated by the bulk package batch.',
        10 => 'Week 10 predates the 16A package standard and needs an upgrade before ingestion.',
        12 => 'Week 12 is quarantined for this batch pending explicit design acceptance of the Helix Rotterdam bucket change.',
        14 => 'Week 14 is a board-defense assessment week with no computational package by design.',
    ];

    public function packageType(int $weekNumber): string
    {
        return self::PACKAGE_TYPE_PREFIX.$weekNumber.'_reference_package';
    }

    public function packageRoot(int $weekNumber): string
    {
        $this->assertRegistrableWeek($weekNumber);

        return 'halden-week'.$weekNumber.'-data-package';
    }

    /**
     * @return array<string, mixed>
     */
    public function manifest(int $weekNumber, string $version = self::PACKAGE_VERSION): array
    {
        $root = $this->packageRoot($weekNumber);
        $provenance = $this->provenance($root);

        return [
            'package_type' => $this->packageType($weekNumber),
            'version' => $version,
            'week_number' => $weekNumber,
            'title' => 'Halden Week '.$weekNumber.' Authoritative Reference Package',
            'status' => 'authoritative_package_available',
            'package_root' => $root,
            'source_package' => [
                'manifest' => $root.'/MANIFEST.md',
                'validation_report' => $root.'/VALIDATION_16A.md',
                'provenance' => $root.'/fixtures/provenance.json',
                'golden_fixture' => $root.'/fixtures/week'.$weekNumber.'_golden.json',
            ],
            'package_version' => $this->packageVersion($provenance, $version),
            'golden_tolerance' => [
                'relative' => self::GOLDEN_RELATIVE_TOLERANCE,
                'absolute' => self::GOLDEN_ABSOLUTE_TOLERANCE,
            ],
            'validated_scope' => [
                'package_inventory',
                'provenance_hashes',
                'canonical_csvs',
                'student_workbook',
                'student_notebook',
                'faculty_solution_materials',
                'golden_fixture',
                'student_faculty_separation',
            ],
            'deferred_runtime_capabilities' => [
                'week_specific_economic_engine',
                'week_execution_integration',
                'kpi_or_ranking_effects',
                'consequence_mapping',
                'what_if_support',
            ],
        ];
    }

    /**
     * @param  array<string, string>  $pathOverrides
     * @return list<array<string, mixed>>
     */
    public function artifacts(int $weekNumber, array $pathOverrides = []): array
    {
        $root = $this->packageRoot($weekNumber);
        $provenance = $this->provenance($root);
        $hashes = $this->artifactHashes($provenance);
        $versions = $this->artifactVersions($provenance);
        $relativePaths = array_values(array_unique([
            ...array_keys($hashes),
            'fixtures/provenance.json',
        ]));

        sort($relativePaths);

        return array_map(function (string $relativePath) use ($root, $weekNumber, $pathOverrides, $hashes, $versions): array {
            $artifactKey = $this->artifactKey($weekNumber, $relativePath);
            $pathReference = $pathOverrides[$artifactKey] ?? $root.'/'.$relativePath;
            $hash = $hashes[$relativePath] ?? (is_file(base_path($pathReference)) ? hash_file('sha256', base_path($pathReference)) : null);

            return [
                'artifact_key' => $artifactKey,
                'artifact_type' => $this->artifactType($relativePath),
                'visibility' => $this->visibility($relativePath),
                'path_reference' => $pathReference,
                'checksum' => is_string($hash) ? $hash : null,
                'version' => $versions[$relativePath] ?? self::PACKAGE_VERSION,
                'metadata' => [
                    'package_root' => $root,
                    'relative_path' => $relativePath,
                    'source_status' => 'authoritative_package_available',
                    'provenance_path' => $root.'/fixtures/provenance.json',
                    'golden_tolerance' => [
                        'relative' => self::GOLDEN_RELATIVE_TOLERANCE,
                        'absolute' => self::GOLDEN_ABSOLUTE_TOLERANCE,
                    ],
                ],
            ];
        }, $relativePaths);
    }

    public function assertRegistrableWeek(int $weekNumber): void
    {
        if (in_array($weekNumber, self::REGISTRABLE_WEEKS, true)) {
            return;
        }

        $reason = self::EXCLUDED_WEEKS[$weekNumber] ?? 'No authoritative package is approved for ingestion.';

        throw new InvalidArgumentException($reason);
    }

    /**
     * @return list<int>
     */
    public function registrableWeeks(): array
    {
        return self::REGISTRABLE_WEEKS;
    }

    /**
     * @return array<int, string>
     */
    public function excludedWeeks(): array
    {
        return self::EXCLUDED_WEEKS;
    }

    /**
     * @return array<string, mixed>
     */
    private function provenance(string $root): array
    {
        $path = base_path($root.'/fixtures/provenance.json');

        if (! File::isFile($path)) {
            return [];
        }

        $decoded = json_decode((string) file_get_contents($path), true);

        return is_array($decoded) ? $decoded : [];
    }

    /**
     * @param  array<string, mixed>  $provenance
     */
    private function packageVersion(array $provenance, string $fallback): string
    {
        $version = $provenance['package_version'] ?? null;

        return is_string($version) && $version !== '' ? $version : $fallback;
    }

    /**
     * @param  array<string, mixed>  $provenance
     * @return array<string, string>
     */
    private function artifactHashes(array $provenance): array
    {
        $artifacts = $provenance['artifacts'] ?? [];

        if (! is_array($artifacts)) {
            return [];
        }

        return array_filter($artifacts, fn (mixed $value): bool => is_string($value));
    }

    /**
     * @param  array<string, mixed>  $provenance
     * @return array<string, string>
     */
    private function artifactVersions(array $provenance): array
    {
        $versions = $provenance['artifact_versions'] ?? [];

        if (! is_array($versions)) {
            return [];
        }

        return array_filter($versions, fn (mixed $value): bool => is_string($value));
    }

    private function artifactKey(int $weekNumber, string $relativePath): string
    {
        $key = preg_replace('/[^a-zA-Z0-9]+/', '_', $relativePath);
        $key = strtolower((string) $key);

        return 'week'.$weekNumber.'_'.trim($key, '_');
    }

    private function artifactType(string $relativePath): string
    {
        if ($relativePath === 'MANIFEST.md') {
            return 'manifest';
        }

        if ($relativePath === 'VALIDATION_16A.md') {
            return 'validation_report';
        }

        if (str_starts_with($relativePath, 'data/')) {
            return 'dataset';
        }

        if (str_ends_with($relativePath, '_golden.json')) {
            return 'expected_outputs';
        }

        if ($relativePath === 'fixtures/provenance.json') {
            return 'provenance';
        }

        if (str_ends_with($relativePath, '.ipynb')) {
            return 'notebook';
        }

        if (str_ends_with($relativePath, '.xlsx')) {
            return 'workbook';
        }

        return 'artifact';
    }

    private function visibility(string $relativePath): string
    {
        if (str_starts_with($relativePath, 'faculty/') || str_starts_with($relativePath, 'fixtures/')) {
            return 'solution';
        }

        if (str_starts_with($relativePath, 'data/') || $relativePath === 'MANIFEST.md' || $relativePath === 'VALIDATION_16A.md') {
            return 'shared';
        }

        return 'student';
    }
}
