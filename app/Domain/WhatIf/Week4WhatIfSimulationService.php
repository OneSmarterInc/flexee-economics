<?php

namespace App\Domain\WhatIf;

use App\Domain\Economics\Week4\Week4EconomicEngine;
use App\Domain\Economics\Week4\Week4EconomicResult;
use App\Domain\Economics\Week4\Week4GenevaArbitrageResult;
use App\Domain\Economics\Week4\Week4ResolutionInputMapper;
use App\Models\EconomicResolution;
use App\Models\User;
use App\Models\WhatIfRun;
use Brick\Math\BigDecimal;
use Illuminate\Support\Carbon;
use InvalidArgumentException;

final class Week4WhatIfSimulationService
{
    public const SCENARIO_TYPE = 'week4_transfer_price';

    public function __construct(
        private readonly Week4EconomicEngine $engine,
        private readonly Week4ResolutionInputMapper $mapper,
    ) {}

    public function runTransferPriceScenario(EconomicResolution $sourceResolution, string $counterfactualTransferPrice, User $actor): WhatIfRun
    {
        $this->assertCanRun($actor, $sourceResolution);
        $sourceResolution->loadMissing(['decisionSubmission', 'runtimeWeek.definition']);

        if ($sourceResolution->runtimeWeek->definition->week_number !== 4) {
            throw new InvalidArgumentException('Only Week 4 economic resolutions can run Week 4 what-if scenarios.');
        }

        $mapped = $this->mapper->map($sourceResolution->decisionSubmission);
        $transferPrice = BigDecimal::of($counterfactualTransferPrice);
        $result = $this->engine->calculate($mapped->inputs, $transferPrice);
        $geneva = $this->engine->genevaArbitrageAtMidpoint($mapped->inputs);

        return WhatIfRun::query()->create([
            'tenant_id' => $sourceResolution->tenant_id,
            'section_simulation_id' => $sourceResolution->section_simulation_id,
            'section_simulation_week_id' => $sourceResolution->section_simulation_week_id,
            'team_simulation_id' => $sourceResolution->team_simulation_id,
            'team_id' => $sourceResolution->team_id,
            'source_economic_resolution_id' => $sourceResolution->id,
            'requested_by_user_id' => $actor->id,
            'scenario_type' => self::SCENARIO_TYPE,
            'counterfactual' => true,
            'scenario_inputs' => [
                'counterfactual' => true,
                'source_resolution_id' => $sourceResolution->id,
                'original_transfer_price' => $this->sourceDecimal($sourceResolution, 'transfer_price'),
                'counterfactual_transfer_price' => (string) $transferPrice,
                'source_input_snapshot' => $mapped->inputSnapshot,
            ],
            'calculated_outputs' => $this->outputSnapshot($sourceResolution, $result, $geneva),
            'engine_identifier' => Week4EconomicEngine::ENGINE_IDENTIFIER,
            'engine_version' => Week4EconomicEngine::ENGINE_VERSION,
            'requested_at' => Carbon::now(),
        ]);
    }

    public function assertCanRun(User $actor, EconomicResolution $sourceResolution): void
    {
        if ($actor->tenant_id !== $sourceResolution->tenant_id) {
            throw new InvalidArgumentException('Actor cannot run what-if scenarios for another tenant.');
        }

        if ($actor->isAdministrator()) {
            return;
        }

        if ($actor->isFaculty()) {
            $assigned = $actor->facultySections()
                ->wherePivot('tenant_id', $sourceResolution->tenant_id)
                ->whereHas('sectionSimulations', fn ($query) => $query->whereKey($sourceResolution->section_simulation_id))
                ->exists();

            if ($assigned) {
                return;
            }
        }

        throw new InvalidArgumentException('Only authorized faculty can run what-if scenarios.');
    }

    /**
     * @return array<string, mixed>
     */
    private function outputSnapshot(EconomicResolution $sourceResolution, Week4EconomicResult $result, Week4GenevaArbitrageResult $geneva): array
    {
        return [
            'counterfactual' => true,
            'source_resolution_id' => $sourceResolution->id,
            'segment_result' => $result->toPackageSegmentArray(),
            'geneva_arbitrage' => $geneva->toPackageArray(),
            'deltas' => [
                'transfer_price' => $this->difference((string) $result->transferPrice, $this->sourceDecimal($sourceResolution, 'transfer_price')),
                'integrated_margin' => $this->difference((string) $result->integratedMargin, $this->sourceDecimal($sourceResolution, 'integrated_margin')),
                'upstream_margin' => $this->difference((string) $result->upstreamMargin, $this->sourceDecimal($sourceResolution, 'upstream_margin')),
                'refining_margin' => $this->difference((string) $result->refiningMargin, $this->sourceDecimal($sourceResolution, 'refining_margin')),
                'geneva_capture_per_bbl' => $this->difference((string) $geneva->capturePerBbl, $this->sourceDecimal($sourceResolution, 'geneva_capture_per_bbl')),
            ],
        ];
    }

    private function sourceDecimal(EconomicResolution $sourceResolution, string $key): string
    {
        $value = $sourceResolution->getRawOriginal($key);

        if (is_string($value) || is_int($value) || is_float($value)) {
            return (string) BigDecimal::of((string) $value)->toScale(3);
        }

        throw new InvalidArgumentException("Source resolution decimal [{$key}] is unavailable.");
    }

    private function difference(string $counterfactual, string $original): string
    {
        return (string) BigDecimal::of($counterfactual)->minus($original);
    }
}
