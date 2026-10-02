<?php

namespace App\Domain\Content\Week8;

use App\Domain\Content\SimulationContentPackageService;
use App\Models\SimulationContentPackage;
use App\Models\SimulationWeek;
use InvalidArgumentException;
use JsonException;

final readonly class Week8ContentPackageRegistrationService
{
    public function __construct(
        private SimulationContentPackageService $packages,
        private Week8ContentPackageManifest $manifest,
    ) {}

    /**
     * @param  array<string, string>  $pathOverrides
     *
     * @throws JsonException
     */
    public function register(SimulationWeek $week, string $version, array $pathOverrides = []): SimulationContentPackage
    {
        if ($week->week_number !== 8) {
            throw new InvalidArgumentException('Week 8 content packages can only be registered against simulation week 8.');
        }

        return $this->packages->register(
            $week,
            Week8ContentPackageManifest::PACKAGE_TYPE,
            $version,
            $this->manifest->manifest($version),
            $this->manifest->artifacts($pathOverrides),
        );
    }
}
