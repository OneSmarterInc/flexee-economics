<?php

namespace App\Domain\Capital\Week6;

use App\Models\CapitalAllocationDecision;

final class Week6CapitalEconomicsEngine
{
    public const ENGINE_IDENTIFIER = 'week6_capital_economics_v1_framework';

    public const ENGINE_VERSION = 'week6_capital_economics_v1';

    public function evaluate(
        CapitalAllocationDecision $decision,
        ?Week6CapitalReferencePackage $package = null,
    ): Week6CapitalEconomicsResult {
        $package ??= Week6CapitalReferencePackage::missing();

        if (! $package->isAvailable()) {
            $reason = $package->unavailableReason() ?? Week6CapitalReferencePackage::MISSING_REASON;

            return new Week6CapitalEconomicsResult(
                status: 'unavailable_reference_package',
                inputSnapshot: $this->inputSnapshot($decision, $package),
                outputSnapshot: [
                    'status' => 'unavailable_reference_package',
                    'reason' => $reason,
                    'npv_musd' => null,
                    'irr_percent' => null,
                    'capital_required_musd' => null,
                    'capital_envelope_feasible' => null,
                ],
                portfolioNpvMusd: null,
                portfolioIrrPercent: null,
                capitalRequiredMusd: null,
                capitalEnvelopeFeasible: null,
                unavailableReason: $reason,
            );
        }

        return new Week6CapitalEconomicsResult(
            status: 'unavailable_reference_package',
            inputSnapshot: $this->inputSnapshot($decision, $package),
            outputSnapshot: [
                'status' => 'unavailable_reference_package',
                'reason' => 'Week 6 formulas require the authoritative cash-flow and expected-output package before implementation.',
                'npv_musd' => null,
                'irr_percent' => null,
                'capital_required_musd' => null,
                'capital_envelope_feasible' => null,
            ],
            portfolioNpvMusd: null,
            portfolioIrrPercent: null,
            capitalRequiredMusd: null,
            capitalEnvelopeFeasible: null,
            unavailableReason: 'Week 6 formulas require the authoritative cash-flow and expected-output package before implementation.',
        );
    }

    /**
     * @return array<string, mixed>
     */
    private function inputSnapshot(CapitalAllocationDecision $decision, Week6CapitalReferencePackage $package): array
    {
        return [
            'capital_allocation_decision_id' => $decision->id,
            'section_simulation_week_id' => $decision->section_simulation_week_id,
            'team_simulation_id' => $decision->team_simulation_id,
            'selected_projects' => $decision->selected_projects,
            'rejected_projects' => $decision->rejected_projects,
            'capital_context' => $decision->context_snapshot,
            'reference_package' => [
                'available' => $package->isAvailable(),
                'version' => $package->version(),
                'unavailable_reason' => $package->unavailableReason(),
            ],
        ];
    }
}
