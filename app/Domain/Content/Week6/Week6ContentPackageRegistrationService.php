<?php

namespace App\Domain\Content\Week6;

use App\Domain\Content\SimulationContentPackageService;
use App\Models\SimulationContentPackage;
use App\Models\SimulationWeek;
use InvalidArgumentException;
use JsonException;

final readonly class Week6ContentPackageRegistrationService
{
    public function __construct(
        private SimulationContentPackageService $packages,
        private Week6ContentPackageManifest $manifest,
    ) {}

    /**
     * @param  array<string, string>  $pathOverrides
     *
     * @throws JsonException
     */
    public function register(SimulationWeek $week, string $version, array $pathOverrides = []): SimulationContentPackage
    {
        if ($week->week_number !== 6) {
            throw new InvalidArgumentException('Week 6 content packages can only be registered against simulation week 6.');
        }

        return $this->packages->register(
            $week,
            Week6ContentPackageManifest::PACKAGE_TYPE,
            $version,
            $this->manifest->manifest($version),
            $this->manifest->artifacts($pathOverrides),
        );
    }
}
