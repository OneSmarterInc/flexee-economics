<?php

namespace App\Domain\Scoring;

use App\Models\EconomicResolution;
use App\Models\KpiSnapshot;
use App\Models\TeamMember;
use App\Models\User;
use Brick\Math\BigDecimal;
use Brick\Math\RoundingMode;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

final class KpiSnapshotService
{
    /**
     * @param  list<KpiCalculationResult>  $results
     * @return list<KpiSnapshot>
     */
    public function storeSnapshots(KpiCalculationContext $context, array $results): array
    {
        return DB::transaction(function () use ($context, $results): array {
            $snapshots = [];
            $sourceId = $context->sourceType === EconomicResolution::class ? $context->sourceId : null;

            foreach ($results as $result) {
                $snapshots[] = KpiSnapshot::query()->create([
                    'tenant_id' => $context->tenantId,
                    'section_simulation_id' => $context->sectionSimulationId,
                    'section_simulation_week_id' => $context->sectionSimulationWeekId,
                    'team_simulation_id' => $context->teamSimulationId,
                    'team_id' => $context->teamId,
                    'kpi_definition_id' => $result->definition->id,
                    'economic_resolution_id' => $sourceId,
                    'status' => $result->status->value,
                    'value' => $result->value instanceof BigDecimal ? $this->databaseDecimal($result->value) : null,
                    'unit' => $result->unit,
                    'precision' => $result->precision,
                    'calculation_version' => $result->calculationVersion,
                    'input_snapshot' => $result->inputSnapshot,
                    'unavailable_reason' => $result->unavailableReason,
                    'calculated_at' => Carbon::now(),
                ]);
            }

            return $snapshots;
        });
    }

    public function assertCanView(User $actor, KpiSnapshot $snapshot): void
    {
        if ($actor->tenant_id !== $snapshot->tenant_id) {
            throw new InvalidArgumentException('Actor cannot access KPI snapshots for another tenant.');
        }

        if ($actor->isAdministrator()) {
            return;
        }

        if ($actor->isFaculty()) {
            $assigned = $actor->facultySections()
                ->wherePivot('tenant_id', $snapshot->tenant_id)
                ->whereHas('sectionSimulations', fn ($query) => $query->whereKey($snapshot->section_simulation_id))
                ->exists();

            if ($assigned) {
                return;
            }
        }

        if ($actor->isStudent()) {
            $member = TeamMember::query()
                ->where('tenant_id', $snapshot->tenant_id)
                ->where('team_id', $snapshot->team_id)
                ->where('user_id', $actor->id)
                ->exists();

            if ($member) {
                return;
            }
        }

        throw new InvalidArgumentException('Actor cannot access this team KPI snapshot.');
    }

    private function databaseDecimal(BigDecimal $value): string
    {
        return (string) $value->toScale(4, RoundingMode::HalfUp);
    }
}
