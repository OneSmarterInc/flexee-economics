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

        return $this->packages->register(
            $week,
            $this->manifest->packageType($weekNumber),
            $resolvedVersion,
            $this->manifest->manifest($weekNumber, $resolvedVersion),
            $this->manifest->artifacts($weekNumber, $pathOverrides),
        );
    }
}
