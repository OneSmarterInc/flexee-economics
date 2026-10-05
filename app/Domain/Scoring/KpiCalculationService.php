<?php

namespace App\Domain\Scoring;

use App\Enums\KpiDefinitionStatus;
use App\Enums\KpiSnapshotStatus;
use App\Models\KpiDefinition;
use Brick\Math\BigDecimal;
use Brick\Math\RoundingMode;

final class KpiCalculationService
{
    public const CALCULATION_VERSION = 'kpi_consequence_v1_0_1';

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
        return match ($definition->key) {
            'integrated_margin_per_boe' => $this->availableStateValue($context, $definition, 'integrated_margin_per_boe', 'usd_boe', 4),
            'roace' => $this->roace($context, $definition),
            'free_cash_flow' => $this->freeCashFlow($context, $definition),
            'refining_net_margin_vs_benchmark' => $this->availableStateValue($context, $definition, 'refining_vs_benchmark', 'usd_bbl', 4),
            'retail_non_fuel_margin_per_site' => $this->availableStateValue($context, $definition, 'nonfuel_per_site_k', 'usd_k_per_site_year', 4),
            'net_debt_to_ebitda' => $this->netDebtToEbitda($context, $definition),
            'asset_health_index' => $this->assetHealth($context, $definition),
            default => $this->unavailable($context, $definition, 'calculation source is not supported by this KPI engine version'),
        };
    }

    private function availableStateValue(
        KpiCalculationContext $context,
        KpiDefinition $definition,
        string $stateKey,
        string $unit,
        int $precision,
    ): KpiCalculationResult {
        if (! array_key_exists($stateKey, $context->availableInputs) || $context->availableInputs[$stateKey] === null || $context->availableInputs[$stateKey] === '') {
            if ($definition->key === 'integrated_margin_per_boe' && array_key_exists('integrated_margin_per_boe', $context->availableInputs)) {
                return $this->available($context, $definition, BigDecimal::of((string) $context->availableInputs['integrated_margin_per_boe']), $unit, $precision);
            }

            return $this->unavailable($context, $definition, "requires {$stateKey} state");
        }

        return new KpiCalculationResult(
            definition: $definition,
            status: KpiSnapshotStatus::Available,
            value: BigDecimal::of((string) $context->availableInputs[$stateKey]),
            unit: $unit,
            precision: $precision,
            calculationVersion: self::CALCULATION_VERSION,
            inputSnapshot: $this->resultInputSnapshot($context, $definition),
        );
    }

    private function roace(KpiCalculationContext $context, KpiDefinition $definition): KpiCalculationResult
    {
        foreach (['ebitda', 'da_rate', 'capital_employed', 'tax_rate'] as $key) {
            if (! $this->hasState($context, $key)) {
                return $this->unavailable($context, $definition, "requires {$key} state");
            }
        }

        $capitalEmployed = BigDecimal::of((string) $context->availableInputs['capital_employed']);
        if ($capitalEmployed->isEqualTo('0')) {
            return $this->unavailable($context, $definition, 'capital employed is zero');
        }

        $ebitda = BigDecimal::of((string) $context->availableInputs['ebitda']);
        $da = BigDecimal::of((string) $context->availableInputs['da_rate'])->multipliedBy($capitalEmployed);
        $taxFactor = BigDecimal::one()->minus((string) $context->availableInputs['tax_rate']);
        $value = $ebitda->minus($da)->multipliedBy($taxFactor)->dividedBy($capitalEmployed, 8, RoundingMode::HalfUp);

        return $this->available($context, $definition, $value, 'ratio', 6);
    }

    private function freeCashFlow(KpiCalculationContext $context, KpiDefinition $definition): KpiCalculationResult
    {
        foreach (['ebitda', 'da_rate', 'capital_employed', 'tax_rate', 'sustaining_capex'] as $key) {
            if (! $this->hasState($context, $key)) {
                return $this->unavailable($context, $definition, "requires {$key} state");
            }
        }

        $ebitda = BigDecimal::of((string) $context->availableInputs['ebitda']);
        $da = BigDecimal::of((string) $context->availableInputs['da_rate'])->multipliedBy((string) $context->availableInputs['capital_employed']);
        $tax = BigDecimal::of((string) $context->availableInputs['tax_rate'])->multipliedBy($ebitda->minus($da));
        $value = $ebitda->minus($tax)->minus((string) $context->availableInputs['sustaining_capex']);

        return $this->available($context, $definition, $value, 'usd_m', 4);
    }

    private function netDebtToEbitda(KpiCalculationContext $context, KpiDefinition $definition): KpiCalculationResult
    {
        foreach (['net_debt', 'ebitda'] as $key) {
            if (! $this->hasState($context, $key)) {
                return $this->unavailable($context, $definition, "requires {$key} state");
            }
        }

        $ebitda = BigDecimal::of((string) $context->availableInputs['ebitda']);
        if ($ebitda->isEqualTo('0')) {
            return $this->unavailable($context, $definition, 'EBITDA is zero');
        }

        return $this->available(
            $context,
            $definition,
            BigDecimal::of((string) $context->availableInputs['net_debt'])->dividedBy($ebitda, 8, RoundingMode::HalfUp),
            'ratio',
            6,
        );
    }

    private function assetHealth(KpiCalculationContext $context, KpiDefinition $definition): KpiCalculationResult
    {
        if (! $this->hasState($context, 'asset_health')) {
            return $this->unavailable($context, $definition, 'requires asset_health state');
        }

        $value = BigDecimal::of((string) $context->availableInputs['asset_health']);
        if ($value->isLessThan('0')) {
            $value = BigDecimal::zero();
        }
        if ($value->isGreaterThan('100')) {
            $value = BigDecimal::of('100');
        }

        return $this->available($context, $definition, $value, 'index_0_100', 4);
    }

    private function available(
        KpiCalculationContext $context,
        KpiDefinition $definition,
        BigDecimal $value,
        string $unit,
        int $precision,
    ): KpiCalculationResult {
        return new KpiCalculationResult(
            definition: $definition,
            status: KpiSnapshotStatus::Available,
            value: $value,
            unit: $unit,
            precision: $precision,
            calculationVersion: self::CALCULATION_VERSION,
            inputSnapshot: $this->resultInputSnapshot($context, $definition),
        );
    }

    private function hasState(KpiCalculationContext $context, string $key): bool
    {
        return array_key_exists($key, $context->availableInputs)
            && $context->availableInputs[$key] !== null
            && $context->availableInputs[$key] !== '';
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
