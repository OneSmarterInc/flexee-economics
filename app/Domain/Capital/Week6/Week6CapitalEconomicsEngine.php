<?php

namespace App\Domain\Capital\Week6;

use App\Models\CapitalAllocationDecision;
use App\Models\CapitalAllocationEvaluation;
use Brick\Math\BigDecimal;
use Brick\Math\RoundingMode;
use InvalidArgumentException;
use JsonException;

final class Week6CapitalEconomicsEngine
{
    public const ENGINE_IDENTIFIER = 'week6_capital_economics';

    public const ENGINE_VERSION = 'week6_capital_economics_v1';

    /**
     * @throws JsonException
     */
    public function evaluate(
        CapitalAllocationDecision $decision,
        ?Week6CapitalReferencePackage $package = null,
    ): Week6CapitalEconomicsResult {
        $package ??= Week6CapitalReferencePackage::fromRepository();

        if (! $package->isAvailable()) {
            $reason = $package->unavailableReason() ?? Week6CapitalReferencePackage::MISSING_REASON;

            return new Week6CapitalEconomicsResult(
                status: CapitalAllocationEvaluation::STATUS_UNAVAILABLE_REFERENCE_PACKAGE,
                inputSnapshot: $this->inputSnapshot($decision, $package),
                outputSnapshot: [
                    'status' => CapitalAllocationEvaluation::STATUS_UNAVAILABLE_REFERENCE_PACKAGE,
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

        $inputs = $package->inputs();
        $context = $decision->contextSnapshot();

        try {
            $discountRate = $inputs->discountRateForContext($context);
            $capitalEnvelope = $inputs->capitalEnvelopeForContext($context);
        } catch (InvalidArgumentException $exception) {
            return new Week6CapitalEconomicsResult(
                status: CapitalAllocationEvaluation::STATUS_UNAVAILABLE_DISCOUNT_RATE_CONTEXT,
                inputSnapshot: $this->inputSnapshot($decision, $package, $inputs),
                outputSnapshot: [
                    'status' => CapitalAllocationEvaluation::STATUS_UNAVAILABLE_DISCOUNT_RATE_CONTEXT,
                    'reason' => $exception->getMessage(),
                    'npv_musd' => null,
                    'irr_percent' => null,
                    'capital_required_musd' => null,
                    'capital_envelope_feasible' => null,
                ],
                portfolioNpvMusd: null,
                portfolioIrrPercent: null,
                capitalRequiredMusd: null,
                capitalEnvelopeFeasible: null,
                unavailableReason: $exception->getMessage(),
            );
        }

        $selectedKeys = $this->projectKeys($decision->selectedProjectSnapshots());
        $selectedProjects = array_map(fn (string $key): Week6ProjectCashFlows => $inputs->project($key), $selectedKeys);
        $projectResults = $this->projectResults($inputs, $discountRate);
        $portfolioFlows = $selectedProjects === []
            ? [BigDecimal::zero()]
            : $this->portfolioFlows($selectedProjects);
        $portfolioNpv = $this->npvForFlows($discountRate, $portfolioFlows);
        $portfolioIrr = $selectedProjects === []
            ? BigDecimal::zero()
            : $this->irrForFlows($portfolioFlows);
        $capitalRequired = $this->capitalRequired($selectedProjects);
        $capitalEnvelopeFeasible = $capitalRequired->isLessThanOrEqualTo($capitalEnvelope);

        return new Week6CapitalEconomicsResult(
            status: CapitalAllocationEvaluation::STATUS_CALCULATED,
            inputSnapshot: $this->inputSnapshot($decision, $package, $inputs),
            outputSnapshot: [
                'status' => CapitalAllocationEvaluation::STATUS_CALCULATED,
                'discount_rate' => $this->rate($discountRate),
                'capital_envelope_musd' => $this->money($capitalEnvelope),
                'selected_project_keys' => $selectedKeys,
                'project_results' => $projectResults,
                'portfolio' => [
                    'npv_musd' => $this->money($portfolioNpv),
                    'irr_percent' => $this->percent($portfolioIrr),
                    'capital_required_musd' => $this->money($capitalRequired),
                    'capital_envelope_feasible' => $capitalEnvelopeFeasible,
                ],
                'golden_reference' => [
                    'source' => 'halden-week6-data-package/fixtures/week6_golden.json',
                    'fixture_keys' => array_keys($inputs->golden),
                ],
            ],
            portfolioNpvMusd: $this->databaseMoney($portfolioNpv),
            portfolioIrrPercent: $this->databasePercent($portfolioIrr),
            capitalRequiredMusd: $this->databaseMoney($capitalRequired),
            capitalEnvelopeFeasible: $capitalEnvelopeFeasible,
            unavailableReason: null,
        );
    }

    public function npv(BigDecimal $discountRate, Week6ProjectCashFlows $project): BigDecimal
    {
        return $this->npvForFlows($discountRate, $project->flows);
    }

    public function irr(Week6ProjectCashFlows $project): BigDecimal
    {
        return $this->irrForFlows($project->flows);
    }

    /**
     * @return array<string, mixed>
     */
    private function inputSnapshot(
        CapitalAllocationDecision $decision,
        Week6CapitalReferencePackage $package,
        ?Week6CapitalEconomicInputs $inputs = null,
    ): array {
        return [
            'capital_allocation_decision_id' => $decision->id,
            'section_simulation_week_id' => $decision->section_simulation_week_id,
            'team_simulation_id' => $decision->team_simulation_id,
            'selected_projects' => $decision->selectedProjectSnapshots(),
            'rejected_projects' => $decision->rejectedProjectSnapshots(),
            'capital_context' => $decision->contextSnapshot(),
            'reference_package' => [
                'available' => $package->isAvailable(),
                'version' => $package->version(),
                'unavailable_reason' => $package->unavailableReason(),
                'source_hashes' => $inputs?->sourceHashes,
            ],
        ];
    }

    /**
     * @return array<string, array<string, string>>
     */
    private function projectResults(Week6CapitalEconomicInputs $inputs, BigDecimal $discountRate): array
    {
        $results = [];

        foreach ($inputs->projects as $key => $project) {
            $projectIrr = $this->irr($project);
            $rawNpv = $this->npv($discountRate, $project);
            $projectType = $key === 'helix' ? 'new_business' : 'refining';
            $haircut = $inputs->haircuts[$projectType] ?? BigDecimal::one();

            $results[$key] = [
                'npv_musd' => $this->money($rawNpv),
                'irr_percent' => $this->percent($projectIrr),
                'outlay_musd' => $this->money($project->outlayMusd()),
                'forecast_haircut' => $this->rate($haircut),
                'haircut_adjusted_npv_musd' => $this->money($rawNpv->multipliedBy($haircut)),
            ];
        }

        return $results;
    }

    /**
     * @param  list<array<string, mixed>>|null  $projects
     * @return list<string>
     */
    private function projectKeys(?array $projects): array
    {
        if ($projects === null || $projects === []) {
            return [];
        }

        return array_map(function (array $project): string {
            if (! is_string($project['key'] ?? null) || $project['key'] === '') {
                throw new InvalidArgumentException('Week 6 selected project snapshot is missing a project key.');
            }

            return $project['key'];
        }, $projects);
    }

    /**
     * @param  list<Week6ProjectCashFlows>  $projects
     * @return list<BigDecimal>
     */
    private function portfolioFlows(array $projects): array
    {
        $length = count($projects[0]->flows);
        $flows = array_fill(0, $length, BigDecimal::zero());

        foreach ($projects as $project) {
            foreach ($project->flows as $index => $flow) {
                $flows[$index] = $flows[$index]->plus($flow);
            }
        }

        return array_values($flows);
    }

    /**
     * @param  list<Week6ProjectCashFlows>  $projects
     */
    private function capitalRequired(array $projects): BigDecimal
    {
        return array_reduce(
            $projects,
            fn (BigDecimal $sum, Week6ProjectCashFlows $project): BigDecimal => $sum->plus($project->outlayMusd()),
            BigDecimal::zero(),
        );
    }

    /**
     * @param  list<BigDecimal>  $flows
     */
    private function npvForFlows(BigDecimal $discountRate, array $flows): BigDecimal
    {
        $sum = BigDecimal::zero();
        $factor = BigDecimal::one();
        $step = BigDecimal::one()->plus($discountRate);

        foreach ($flows as $year => $flow) {
            if ($year > 0) {
                $factor = $factor->multipliedBy($step);
            }

            $sum = $sum->plus($flow->dividedBy($factor, 12, RoundingMode::HalfUp));
        }

        return $sum;
    }

    /**
     * @param  list<BigDecimal>  $flows
     */
    private function irrForFlows(array $flows): BigDecimal
    {
        $low = BigDecimal::of('-0.9');
        $high = BigDecimal::of('3.0');

        for ($i = 0; $i < 300; $i++) {
            $mid = $low->plus($high)->dividedBy('2', 18, RoundingMode::HalfUp);

            if ($this->npvForFlows($mid, $flows)->isGreaterThan(BigDecimal::zero())) {
                $low = $mid;
            } else {
                $high = $mid;
            }
        }

        return $low->plus($high)->dividedBy('2', 18, RoundingMode::HalfUp);
    }

    private function money(BigDecimal $value): string
    {
        return (string) $value->toScale(2, RoundingMode::HalfUp);
    }

    private function databaseMoney(BigDecimal $value): string
    {
        return (string) $value->toScale(3, RoundingMode::HalfUp);
    }

    private function percent(BigDecimal $value): string
    {
        return (string) $value->multipliedBy('100')->toScale(2, RoundingMode::HalfUp);
    }

    private function databasePercent(BigDecimal $value): string
    {
        return (string) $value->multipliedBy('100')->toScale(4, RoundingMode::HalfUp);
    }

    private function rate(BigDecimal $value): string
    {
        return (string) $value->toScale(6, RoundingMode::HalfUp);
    }
}
