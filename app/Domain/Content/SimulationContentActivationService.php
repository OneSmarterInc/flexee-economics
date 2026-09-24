<?php

namespace App\Domain\Content;

use App\Models\SimulationContentActivation;
use App\Models\SimulationContentPackage;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

final class SimulationContentActivationService
{
    public function activate(SimulationContentPackage $package): SimulationContentActivation
    {
        if ($package->status !== SimulationContentPackage::STATUS_VALIDATED) {
            throw new InvalidArgumentException('Only validated simulation content packages can be activated.');
        }

        return DB::transaction(function () use ($package): SimulationContentActivation {
            $existing = SimulationContentActivation::query()
                ->where('simulation_week_id', $package->simulation_week_id)
                ->where('package_type', $package->package_type)
                ->where('status', SimulationContentActivation::STATUS_ACTIVE)
                ->lockForUpdate()
                ->first();

            if ($existing instanceof SimulationContentActivation) {
                throw new InvalidArgumentException('An active content package already exists for this simulation week and package type.');
            }

            return SimulationContentActivation::query()->create([
                'simulation_version_id' => $package->simulation_version_id,
                'simulation_week_id' => $package->simulation_week_id,
                'simulation_content_package_id' => $package->id,
                'package_type' => $package->package_type,
                'package_version' => $package->version,
                'status' => SimulationContentActivation::STATUS_ACTIVE,
                'activated_at' => Carbon::now(),
            ]);
        });
    }
}
