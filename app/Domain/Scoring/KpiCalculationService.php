<?php

namespace App\Domain\Scoring;

use App\Enums\KpiDefinitionStatus;
use App\Enums\KpiSnapshotStatus;
use App\Models\KpiDefinition;
use Brick\Math\BigDecimal;

final class KpiCalculationService
{
    public const CALCULATION_VERSION = 'kpi_framework_v1';

    /**
     * @param  iterable<KpiDefinition>  $definitions
     * @return list<KpiCalculationResult>
     */
    public function calculate(KpiCalculationContext $context, iterable $definitions): array
    {
        $results = [];

        foreach ($definitions as $definition) {
            if ($definition->statusEnum() !== KpiDefinitionStatus::Published) {
                continue;
            }

            $results[] = $this->calculateDefinition($context, $definition);
        }

        return $results;
    }

    private function calculateDefinition(KpiCalculationContext $context, KpiDefinition $definition): KpiCalculationResult
    {
        return match ($definition->calculation_source) {
            'economic_resolution.integrated_margin_per_boe' => $this->availableFromInput(
                context: $context,
                definition: $definition,
                inputKey: 'integrated_margin_per_boe',
                unit: 'usd_boe',
                precision: 2,
            ),
            'economic_resolution.refining_net_margin_vs_benchmark' => $this->availableFromInput(
                context: $context,
                definition: $definition,
                inputKey: 'refining_net_margin_vs_benchmark',
                unit: 'usd_bbl',
                precision: 2,
                unavailableReason: 'requires refining benchmark state',
            ),
            'standing.roace' => $this->unavailable($context, $definition, 'requires capital base state'),
            'cash_flow.free_cash_flow' => $this->unavailable($context, $definition, 'requires cash flow state'),
            'retail.non_fuel_margin_per_site' => $this->unavailable($context, $definition, 'requires retail site margin state'),
            'debt.net_debt_to_ebitda' => $this->unavailable($context, $definition, 'requires debt and EBITDA state'),
            'asset_health.index' => $this->unavailable($context, $definition, 'requires asset health model'),
            default => $this->unavailable($context, $definition, 'calculation source is not supported by this KPI engine version'),
        };
    }

    private function availableFromInput(
        KpiCalculationContext $context,
        KpiDefinition $definition,
        string $inputKey,
        string $unit,
        int $precision,
        ?string $unavailableReason = null,
    ): KpiCalculationResult {
        if (! array_key_exists($inputKey, $context->availableInputs) || $context->availableInputs[$inputKey] === null || $context->availableInputs[$inputKey] === '') {
            return $this->unavailable($context, $definition, $unavailableReason ?? "requires {$inputKey} input");
        }

        return new KpiCalculationResult(
            definition: $definition,
            status: KpiSnapshotStatus::Available,
            value: BigDecimal::of((string) $context->availableInputs[$inputKey]),
            unit: $unit,
            precision: $precision,
            calculationVersion: self::CALCULATION_VERSION,
            inputSnapshot: $this->resultInputSnapshot($context, $definition),
        );
    }

    private function unavailable(KpiCalculationContext $context, KpiDefinition $definition, string $reason): KpiCalculationResult
    {
        return new KpiCalculationResult(
            definition: $definition,
            status: KpiSnapshotStatus::Unavailable,
            value: null,
            unit: null,
            precision: 2,
            calculationVersion: self::CALCULATION_VERSION,
            inputSnapshot: $this->resultInputSnapshot($context, $definition),
            unavailableReason: $reason,
        );
    }

    /**
     * @return array<string, mixed>
     */
    private function resultInputSnapshot(KpiCalculationContext $context, KpiDefinition $definition): array
    {
        return [
            'source_type' => $context->sourceType,
            'source_id' => $context->sourceId,
            'available_inputs' => $context->availableInputs,
            'source_snapshot' => $context->inputSnapshot,
            'kpi_definition' => [
                'key' => $definition->key,
                'version' => $definition->version,
                'weight' => $definition->weight,
                'calculation_source' => $definition->calculation_source,
            ],
        ];
    }
}
