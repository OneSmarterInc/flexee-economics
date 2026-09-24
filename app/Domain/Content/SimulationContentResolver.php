<?php

namespace App\Domain\Content;

use App\Models\ContentArtifact;
use App\Models\SectionSimulationWeek;
use App\Models\SimulationContentActivation;
use App\Models\SimulationContentPackage;
use App\Models\User;
use Illuminate\Database\Eloquent\Collection;
use InvalidArgumentException;

final class SimulationContentResolver
{
    public function activePackageFor(SectionSimulationWeek $runtimeWeek, string $packageType = 'reference_package'): SimulationContentPackage
    {
        $activation = SimulationContentActivation::query()
            ->where('simulation_version_id', $runtimeWeek->simulation_version_id)
            ->where('simulation_week_id', $runtimeWeek->simulation_week_id)
            ->where('package_type', $packageType)
            ->where('status', SimulationContentActivation::STATUS_ACTIVE)
            ->with('package')
            ->first();

        if (! $activation instanceof SimulationContentActivation || ! $activation->package instanceof SimulationContentPackage) {
            throw new InvalidArgumentException('No active content package exists for this runtime week.');
        }

        if ($activation->package->status !== SimulationContentPackage::STATUS_VALIDATED) {
            throw new InvalidArgumentException('Active content package is not validated.');
        }

        return $activation->package;
    }

    /**
     * @return Collection<int, ContentArtifact>
     */
    public function authorizedArtifactsFor(User $actor, SectionSimulationWeek $runtimeWeek, string $packageType = 'reference_package'): Collection
    {
        $this->assertCanViewRuntimeWeek($actor, $runtimeWeek);
        $package = $this->activePackageFor($runtimeWeek, $packageType);

        return $package->artifacts()
            ->whereIn('visibility', $this->visibleArtifactScopes($actor))
            ->where('is_missing', false)
            ->orderBy('artifact_type')
            ->orderBy('artifact_key')
            ->get();
    }

    private function assertCanViewRuntimeWeek(User $actor, SectionSimulationWeek $runtimeWeek): void
    {
        if ($actor->tenant_id !== $runtimeWeek->tenant_id) {
            throw new InvalidArgumentException('Actor cannot resolve content for another tenant.');
        }

        $sectionSimulation = $runtimeWeek->sectionSimulation;

        if ($actor->isAdministrator()) {
            return;
        }

        if ($actor->isFaculty()) {
            $assigned = $actor->facultySections()
                ->wherePivot('tenant_id', $runtimeWeek->tenant_id)
                ->whereKey($sectionSimulation->section_id)
                ->exists();

            if ($assigned) {
                return;
            }
        }

        if ($actor->isStudent()) {
            $enrolled = $actor->enrollments()
                ->where('section_id', $sectionSimulation->section_id)
                ->where('status', 'active')
                ->exists();

            if ($enrolled) {
                return;
            }
        }

        throw new InvalidArgumentException('Actor is not authorized to resolve content for this runtime week.');
    }

    /**
     * @return list<string|null>
     */
    private function visibleArtifactScopes(User $actor): array
    {
        if ($actor->isStudent()) {
            return ['student', 'shared', null];
        }

        return ['student', 'faculty', 'solution', 'shared', null];
    }
}
