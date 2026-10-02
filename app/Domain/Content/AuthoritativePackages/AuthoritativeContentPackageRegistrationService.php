<?php

namespace App\Domain\Content\AuthoritativePackages;

use App\Domain\Content\SimulationContentPackageService;
use App\Models\SimulationContentPackage;
use App\Models\SimulationWeek;
use JsonException;

final readonly class AuthoritativeContentPackageRegistrationService
{
    public function __construct(
        private SimulationContentPackageService $packages,
        private AuthoritativeContentPackageManifest $manifest,
    ) {}

    /**
     * @param  array<string, string>  $pathOverrides
     *
     * @throws JsonException
     */
    public function register(SimulationWeek $week, ?string $version = null, array $pathOverrides = []): SimulationContentPackage
    {
        $weekNumber = $week->week_number;

        $this->manifest->assertRegistrableWeek($weekNumber);
        $resolvedVersion = $version ?? AuthoritativeContentPackageManifest::PACKAGE_VERSION;
        $packageType = $this->manifest->packageType($weekNumber);

        $existing = SimulationContentPackage::query()
            ->where('simulation_week_id', $week->id)
            ->where('package_type', $packageType)
            ->where('version', $resolvedVersion)
            ->first();

        if ($existing instanceof SimulationContentPackage) {
            return $existing;
        }

        return $this->packages->register(
            $week,
            $packageType,
            $resolvedVersion,
            $this->manifest->manifest($weekNumber, $resolvedVersion),
            $this->manifest->artifacts($weekNumber, $pathOverrides),
        );
    }
}
