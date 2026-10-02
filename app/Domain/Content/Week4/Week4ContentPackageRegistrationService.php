<?php

namespace App\Domain\Content\Week4;

use App\Domain\Content\SimulationContentActivationService;
use App\Domain\Content\SimulationContentPackageService;
use App\Models\SimulationContentActivation;
use App\Models\SimulationContentPackage;
use App\Models\SimulationWeek;
use InvalidArgumentException;
use JsonException;

final readonly class Week4ContentPackageRegistrationService
{
    public const PACKAGE_TYPE = 'reference_package';

    public const PACKAGE_VERSION = 'week4_reference_package_v1';

    private const PACKAGE_ROOT = 'halden-week4-data-package';

    public function __construct(
        private SimulationContentPackageService $packages,
        private SimulationContentActivationService $activations,
    ) {}

    /**
     * @throws JsonException
     */
    public function ensurePackage(SimulationWeek $week): SimulationContentPackage
    {
        $this->assertWeek4($week);

        $existing = SimulationContentPackage::query()
            ->where('simulation_version_id', $week->simulation_version_id)
            ->where('simulation_week_id', $week->id)
            ->where('package_type', self::PACKAGE_TYPE)
            ->where('version', self::PACKAGE_VERSION)
            ->first();

        if ($existing instanceof SimulationContentPackage) {
            return $existing;
        }

        return $this->packages->register(
            $week,
            self::PACKAGE_TYPE,
            self::PACKAGE_VERSION,
            $this->manifest($week),
            $this->artifacts(),
        );
    }

    /**
     * @throws JsonException
     */
    public function ensureActivated(SimulationWeek $week): SimulationContentActivation
    {
        $package = $this->ensurePackage($week);

        $existing = SimulationContentActivation::query()
            ->where('simulation_version_id', $week->simulation_version_id)
            ->where('simulation_week_id', $week->id)
            ->where('package_type', self::PACKAGE_TYPE)
            ->where('status', SimulationContentActivation::STATUS_ACTIVE)
            ->first();

        if ($existing instanceof SimulationContentActivation) {
            if ($existing->simulation_content_package_id !== $package->id) {
                throw new InvalidArgumentException('A different Week 4 content package is already active for this simulation week.');
            }

            return $existing;
        }

        return $this->activations->activate($package);
    }

    private function assertWeek4(SimulationWeek $week): void
    {
        if ($week->week_number !== 4) {
            throw new InvalidArgumentException('The Week 4 reference package can only be registered for simulation week 4.');
        }
    }

    /**
     * @return array<string, mixed>
     */
    private function manifest(SimulationWeek $week): array
    {
        return [
            'package_root' => self::PACKAGE_ROOT,
            'package_type' => self::PACKAGE_TYPE,
            'version' => self::PACKAGE_VERSION,
            'simulation_week_number' => $week->week_number,
            'source' => 'halden-week4-data-package',
            'expected_outputs' => [
                self::PACKAGE_ROOT.'/expected/worked_example.json',
                self::PACKAGE_ROOT.'/expected/week4_reference.json',
            ],
        ];
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function artifacts(): array
    {
        $paths = [
            self::PACKAGE_ROOT.'/MANIFEST.md',
            self::PACKAGE_ROOT.'/README.md',
            self::PACKAGE_ROOT.'/requirements.txt',
            self::PACKAGE_ROOT.'/data/permian_lifting.csv',
            self::PACKAGE_ROOT.'/data/cost_constants.csv',
            self::PACKAGE_ROOT.'/data/segment_comp.csv',
            self::PACKAGE_ROOT.'/data/worked_example_prior.csv',
            self::PACKAGE_ROOT.'/student/halden_week4.xlsx',
            self::PACKAGE_ROOT.'/student/halden_week4_analysis.ipynb',
            self::PACKAGE_ROOT.'/faculty/halden_week4_solution.xlsx',
            self::PACKAGE_ROOT.'/faculty/halden_week4_solution.ipynb',
            self::PACKAGE_ROOT.'/expected/worked_example.json',
            self::PACKAGE_ROOT.'/expected/week4_reference.json',
        ];

        return array_map(fn (string $path): array => [
            'artifact_key' => $this->artifactKey($path),
            'artifact_type' => $this->artifactType($path),
            'visibility' => $this->visibility($path),
            'path_reference' => $path,
            'checksum' => is_file(base_path($path)) ? hash_file('sha256', base_path($path)) : null,
            'version' => self::PACKAGE_VERSION,
            'metadata' => [
                'package_root' => self::PACKAGE_ROOT,
            ],
        ], $paths);
    }

    private function artifactKey(string $path): string
    {
        return str_replace(['/', '.', '_'], '-', $path);
    }

    private function artifactType(string $path): string
    {
        return match (pathinfo($path, PATHINFO_EXTENSION)) {
            'csv' => 'dataset',
            'ipynb' => 'notebook',
            'json' => 'expected_output',
            'md' => 'documentation',
            'txt' => 'requirements',
            'xlsx' => 'workbook',
            default => 'artifact',
        };
    }

    private function visibility(string $path): string
    {
        if (str_contains($path, '/student/')) {
            return 'student';
        }

        if (str_contains($path, '/faculty/') || str_contains($path, '/expected/')) {
            return 'solution';
        }

        return 'shared';
    }
}
