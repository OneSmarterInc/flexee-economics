<?php

namespace App\Domain\CohortFeedback;

use App\Models\CohortResponseFunction;
use Brick\Math\RoundingMode;
use InvalidArgumentException;

final readonly class Window2CohortResponseFunctionCatalog
{
    public const FUNCTION_KEY = 'week6_gulf_coast_capacity_to_week8_margin_window2';

    public const EFFECT_KEY = 'week6_gulf_coast_capacity_to_week8_margin';

    public const RESPONSE_VERSION = 'window2_cohort_response_v1';

    public function __construct(
        private Window2CohortPackage $package,
    ) {}

    public static function fromRepository(?string $packageRoot = null): self
    {
        return new self(Window2CohortPackage::fromRepository($packageRoot));
    }

    public function register(bool $active = true): CohortResponseFunction
    {
        $mismatches = $this->package->validateProvenance();

        if ($mismatches !== []) {
            throw new InvalidArgumentException('Window 2 cohort addendum package provenance does not match.');
        }

        $parameters = $this->package->parameters();
        $margin = $this->package->batonRougeMarginInputs();
        $link = $this->package->week8Link();

        $function = CohortResponseFunction::query()->firstOrNew([
            'key' => self::FUNCTION_KEY,
            'version' => self::RESPONSE_VERSION,
        ]);

        $function->forceFill([
            'name' => 'Week 6 to Week 8 Gulf Coast capacity cohort response',
            'description' => 'Authoritative Window 2 response: share of teams funding Baton Rouge capacity shifts the Week 8 Gulf Coast refining crack.',
            'source_week_number' => 6,
            'target_week_number' => 8,
            'input_definition' => [
                'source' => 'capital_allocation',
                'project_keys' => ['baton_rouge'],
                'project_metric' => 'selected_indicator',
                'metric' => 'share_of_teams_funding_baton_rouge_crude_flexibility_upgrade',
                'unit' => 'share',
            ],
            'output_definition' => [
                'key' => self::EFFECT_KEY,
                'channel' => 'gulf_coast_refining_crack',
                'unit' => 'usd_bbl',
                'target' => 'week8_refining_crack',
            ],
            'bounds' => [
                'min' => (string) $parameters['bound_pct']->negated()->multipliedBy($margin['gc_crack_base'])->toScale(2, RoundingMode::HalfUp),
                'max' => (string) $parameters['bound_pct']->multipliedBy($margin['gc_crack_base'])->toScale(2, RoundingMode::HalfUp),
            ],
            'parameters' => [
                'aggregate' => 'average',
                'response' => 'window2_pivoted_share_v1',
                'pivot_share' => (string) $parameters['pivot_share']->toScale(6, RoundingMode::HalfUp),
                'overbuild_slope' => (string) $parameters['down_slope']->negated()->toScale(6, RoundingMode::HalfUp),
                'restraint_slope' => (string) $parameters['up_slope']->toScale(6, RoundingMode::HalfUp),
                'parallel_universe_baseline' => (string) $margin['gc_crack_base']->toScale(2, RoundingMode::HalfUp),
                'baton_rouge_complexity' => (string) $margin['complexity']->toScale(2, RoundingMode::HalfUp),
                'baton_rouge_opex' => (string) $margin['opex']->toScale(2, RoundingMode::HalfUp),
                'opec_crack_coefficient' => (string) $link['crack_coefficient']->toScale(6, RoundingMode::HalfUp),
                'hidden_through_week_number' => 7,
                'reveal_week_number' => 8,
                'scope' => 'section',
                'source_package' => Window2CohortPackage::DEFAULT_ROOT,
                'source_package_version' => $this->package->version(),
                'excluded_variant_duration_weeks' => [7],
                'seven_week_variant' => 'excluded',
                'week7_compounding' => 'deferred',
            ],
            'is_active' => $active,
        ])->save();

        return $function->refresh();
    }
}
