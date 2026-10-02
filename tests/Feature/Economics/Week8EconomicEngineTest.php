<?php

namespace Tests\Feature\Economics;

use App\Domain\Economics\Week8\Week8EconomicEngine;
use App\Domain\Economics\Week8\Week8ReferencePackage;
use App\Models\CohortFeedbackEffect;
use Brick\Math\BigDecimal;
use Brick\Math\RoundingMode;
use Illuminate\Foundation\Testing\RefreshDatabase;
use InvalidArgumentException;
use Tests\TestCase;

class Week8EconomicEngineTest extends TestCase
{
    use RefreshDatabase;

    public function test_reference_package_loads_week8_scenarios_coefficients_and_fixture_metadata(): void
    {
        $inputs = Week8ReferencePackage::fromRepository()->inputs();

        $this->assertSame(['holds_full', 'holds_partial', 'fails'], array_keys($inputs->scenarios));
        $this->assertSame('0.350000', (string) $inputs->scenario('holds_full')->probability->toScale(6, RoundingMode::HalfUp));
        $this->assertSame('88.00', (string) $inputs->scenario('holds_full')->wtiResolved->toScale(2, RoundingMode::HalfUp));
        $this->assertSame('-0.3500', (string) $inputs->coefficient('refining_crack')->toScale(4, RoundingMode::HalfUp));
        $this->assertSame('21.50', (string) $inputs->baseline('crack_base')->toScale(2, RoundingMode::HalfUp));
        $this->assertSame('1.0.0-draft', $inputs->packageVersion);
        $this->assertArrayHasKey('fixtures/week8_golden.json', $inputs->sourceHashes);
    }

    public function test_scenario_probabilities_sum_to_one_and_expected_wti_matches_golden_fixture(): void
    {
        $result = (new Week8EconomicEngine)->calculate(Week8ReferencePackage::fromRepository()->inputs());

        $this->assertSame([
            'holds_full' => '0.350000',
            'holds_partial' => '0.400000',
            'fails' => '0.250000',
        ], $result->probabilityDistribution);
        $this->assertSame('80.70', $result->expectedWtiMoney());
        $this->assertSame('6.70', $result->expectedUpstreamMoney());
        $this->assertSame('19.16', $result->expectedCrackMoney());
    }

    public function test_invalid_prediction_probability_distribution_is_rejected(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('probabilities must sum to 1.000000');

        (new Week8EconomicEngine)->calculate(
            Week8ReferencePackage::fromRepository()->inputs(),
            predictionDistribution: [
                'holds_full' => '0.60',
                'holds_partial' => '0.30',
                'fails' => '0.30',
            ],
        );
    }

    public function test_each_scenario_output_matches_the_golden_fixture(): void
    {
        $inputs = Week8ReferencePackage::fromRepository()->inputs();
        $result = (new Week8EconomicEngine)->calculate($inputs);

        foreach ($inputs->golden['per_scenario'] as $scenarioKey => $fixture) {
            $scenario = $result->scenarioResults[$scenarioKey];

            $this->assertDecimalClose((string) $fixture['upstream_per_bbl'], $scenario->upstreamImpactPerBbl, 2, "upstream {$scenarioKey}");
            $this->assertDecimalClose((string) $fixture['crack'], $scenario->refiningCrack, 2, "crack {$scenarioKey}");
            $this->assertDecimalClose((string) $fixture['retail_vol_pct'], $scenario->retailVolumePercent, 3, "retail {$scenarioKey}");
            $this->assertDecimalClose((string) $fixture['sim_probability'], $scenario->probability, 6, "probability {$scenarioKey}");
        }
    }

    public function test_worked_example_matches_package_reference_outputs(): void
    {
        $workedExample = (new Week8EconomicEngine)->workedExample(Week8ReferencePackage::fromRepository()->inputs());

        $this->assertSame(
            '3.40',
            (string) $workedExample['expected_upstream_impact_per_bbl']->toScale(2, RoundingMode::HalfUp),
        );
        $this->assertSame(
            '20.31',
            (string) $workedExample['expected_refining_crack']->toScale(2, RoundingMode::HalfUp),
        );
    }

    public function test_prediction_and_realized_outcome_remain_separate(): void
    {
        $result = (new Week8EconomicEngine)->calculate(
            Week8ReferencePackage::fromRepository()->inputs(),
            predictionDistribution: [
                'holds_full' => '0.10',
                'holds_partial' => '0.20',
                'fails' => '0.70',
            ],
            realizedScenarioKey: 'holds_full',
        );

        $this->assertSame([
            'holds_full' => '0.100000',
            'holds_partial' => '0.200000',
            'fails' => '0.700000',
        ], $result->predictionDistribution);
        $this->assertSame('74.00', (string) $result->predictionExpectedWti?->toScale(2, RoundingMode::HalfUp));
        $this->assertSame('holds_full', $result->realizedScenarioResult?->scenarioKey);
        $this->assertSame('88.00', (string) $result->realizedScenarioResult?->wtiResolved->toScale(2, RoundingMode::HalfUp));
        $this->assertNotSame(
            $result->predictionDistribution,
            $result->probabilityDistribution,
            'Student prediction must not be overwritten by the package simulation probabilities.',
        );
    }

    public function test_decimal_precision_matches_week8_display_rules_without_binary_float_drift(): void
    {
        $result = (new Week8EconomicEngine)->calculate(Week8ReferencePackage::fromRepository()->inputs());
        $fullHold = $result->scenarioResults['holds_full'];

        $this->assertSame('14.00', (string) $fullHold->upstreamImpactPerBbl->toScale(2, RoundingMode::HalfUp));
        $this->assertSame('16.60', (string) $fullHold->refiningCrack->toScale(2, RoundingMode::HalfUp));
        $this->assertSame('-0.312', (string) $fullHold->retailVolumePercent->toScale(3, RoundingMode::HalfEven));
        $this->assertStringNotContainsString('000000000000', (string) $fullHold->retailVolumePercent);
    }

    public function test_opec_engine_does_not_create_or_mutate_cohort_feedback_effects(): void
    {
        $this->assertSame(0, CohortFeedbackEffect::query()->count());

        (new Week8EconomicEngine)->calculate(Week8ReferencePackage::fromRepository()->inputs());

        $this->assertSame(0, CohortFeedbackEffect::query()->count());
    }

    private function assertDecimalClose(string $expected, BigDecimal $actual, int $scale, string $message): void
    {
        $expectedDecimal = BigDecimal::of($expected)->toScale($scale, RoundingMode::HalfEven);
        $actualDecimal = $actual->toScale($scale, RoundingMode::HalfEven);

        $this->assertTrue(
            $actualDecimal->isEqualTo($expectedDecimal),
            "{$message}: expected {$expectedDecimal}, got {$actualDecimal}",
        );
    }
}
