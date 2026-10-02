<?php

namespace App\Domain\CohortFeedback;

use App\Domain\Economics\Week3\Week3ReferencePackage;
use App\Models\CohortResponseFunction;
use Brick\Math\RoundingMode;
use InvalidArgumentException;

final readonly class Window1CohortResponseFunctionCatalog
{
    public const FUNCTION_KEY = 'week3_european_run_rate_to_week5_nwe_crack_window1';

    public const EFFECT_KEY = 'week3_european_run_rate_to_week5_nwe_crack';

    public const RESPONSE_VERSION = 'window1_cohort_response_v1';

    public function __construct(
        private Week3ReferencePackage $package,
    ) {}

    public static function fromRepository(?string $packageRoot = null): self
    {
        return new self(Week3ReferencePackage::fromRepository($packageRoot));
    }

    public function register(bool $active = true): CohortResponseFunction
    {
        if (! $this->package->isAvailable()) {
            throw new InvalidArgumentException($this->package->unavailableReason() ?? Week3ReferencePackage::MISSING_REASON);
        }

        $inputs = $this->package->inputs();
        $baseUtil = $inputs->windowParameter('base_util');
        $baseCrack = $inputs->windowParameter('base_crack');
        $slope = $inputs->windowParameter('slope');
        $floor = $inputs->windowParameter('shutdown_floor');
        $intercept = $baseCrack->plus($slope->multipliedBy($baseUtil));

        $function = CohortResponseFunction::query()->firstOrNew([
            'key' => self::FUNCTION_KEY,
            'version' => self::RESPONSE_VERSION,
        ]);

        $function->forceFill([
            'name' => 'Week 3 to Week 5 European run-rate cohort response',
            'description' => 'Authoritative Window 1 response: average European utilization changes the Week 5 NWE refining crack.',
            'source_week_number' => 3,
            'target_week_number' => 5,
            'input_definition' => [
                'source' => 'decision_submission',
                'decision_field' => 'european_utilization',
                'metric' => 'average_european_utilization',
                'unit' => 'share',
            ],
            'output_definition' => [
                'key' => self::EFFECT_KEY,
                'channel' => 'nwe_refining_crack',
                'unit' => 'usd_bbl',
                'target' => 'week5_nwe_crack',
            ],
            'bounds' => [
                'min' => (string) $floor->toScale(6, RoundingMode::HalfUp),
            ],
            'parameters' => [
                'aggregate' => 'average',
                'response' => 'linear_response_v1',
                'intercept' => (string) $intercept->toScale(6, RoundingMode::HalfUp),
                'slope' => (string) $slope->negated()->toScale(6, RoundingMode::HalfUp),
                'base_util' => (string) $baseUtil->toScale(6, RoundingMode::HalfUp),
                'base_crack' => (string) $baseCrack->toScale(6, RoundingMode::HalfUp),
                'parallel_universe_baseline' => (string) $baseCrack->toScale(6, RoundingMode::HalfUp),
                'shutdown_floor' => (string) $floor->toScale(6, RoundingMode::HalfUp),
                'hidden_through_week_number' => 4,
                'reveal_week_number' => 5,
                'scope' => 'section',
                'source_package' => Week3ReferencePackage::DEFAULT_ROOT,
                'source_package_version' => $inputs->packageVersion,
                'excluded_variant_duration_weeks' => [7],
                'seven_week_variant' => 'excluded',
            ],
            'is_active' => $active,
        ])->save();

        return $function->refresh();
    }
}
