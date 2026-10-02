<?php

namespace App\Domain\CohortFeedback;

use App\Domain\Economics\Week7\Week7ReferencePackage;
use App\Models\CohortResponseFunction;
use Brick\Math\RoundingMode;
use InvalidArgumentException;

final readonly class Window3CohortResponseFunctionCatalog
{
    public const FUNCTION_KEY = 'week7_retail_aggression_to_week9_nonfuel_margin_window3';

    public const EFFECT_KEY = 'week7_retail_aggression_to_week9_nonfuel_margin';

    public const RESPONSE_VERSION = 'window3_cohort_response_v1';

    public function __construct(
        private Week7ReferencePackage $package,
    ) {}

    public static function fromRepository(?string $packageRoot = null): self
    {
        return new self(Week7ReferencePackage::fromRepository($packageRoot));
    }

    public function register(bool $active = true): CohortResponseFunction
    {
        if (! $this->package->isAvailable()) {
            throw new InvalidArgumentException($this->package->unavailableReason() ?? Week7ReferencePackage::MISSING_REASON);
        }

        $inputs = $this->package->inputs();
        $base = $inputs->windowParameter('base_nonfuel');
        $slope = $inputs->windowParameter('slope');
        $pivot = $inputs->windowParameter('pivot');
        $intercept = $base->plus($slope->multipliedBy($pivot));

        $function = CohortResponseFunction::query()->firstOrNew([
            'key' => self::FUNCTION_KEY,
            'version' => self::RESPONSE_VERSION,
        ]);

        $function->forceFill([
            'name' => 'Week 7 to Week 9 retail-aggression cohort response',
            'description' => 'Authoritative Window 3 response: average retail pricing aggression changes Week 9 Cordell non-fuel margin.',
            'source_week_number' => 7,
            'target_week_number' => 9,
            'input_definition' => [
                'source' => 'decision_submission',
                'decision_field' => 'retail_pricing_aggression',
                'metric' => 'average_retail_pricing_aggression',
                'unit' => 'share',
            ],
            'output_definition' => [
                'key' => self::EFFECT_KEY,
                'channel' => 'cordell_nonfuel_margin',
                'unit' => 'usd_fill',
                'target' => 'week9_nonfuel_margin',
            ],
            'bounds' => [
                'min' => (string) $base->multipliedBy('0.85')->toScale(6, RoundingMode::HalfUp),
                'max' => (string) $base->multipliedBy('1.15')->toScale(6, RoundingMode::HalfUp),
            ],
            'parameters' => [
                'aggregate' => 'average',
                'response' => 'linear_response_v1',
                'intercept' => (string) $intercept->toScale(6, RoundingMode::HalfUp),
                'slope' => (string) $slope->negated()->toScale(6, RoundingMode::HalfUp),
                'base_nonfuel' => (string) $base->toScale(6, RoundingMode::HalfUp),
                'pivot' => (string) $pivot->toScale(6, RoundingMode::HalfUp),
                'parallel_universe_baseline' => (string) $base->toScale(6, RoundingMode::HalfUp),
                'hidden_through_week_number' => 8,
                'reveal_week_number' => 9,
                'scope' => 'section',
                'source_package' => Week7ReferencePackage::DEFAULT_ROOT,
                'source_package_version' => $inputs->packageVersion,
                'excluded_variant_duration_weeks' => [7],
                'seven_week_variant' => 'excluded',
            ],
            'is_active' => $active,
        ])->save();

        return $function->refresh();
    }
}
