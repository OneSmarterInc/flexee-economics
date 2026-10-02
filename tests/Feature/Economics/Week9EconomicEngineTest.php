<?php

namespace Tests\Feature\Economics;

use App\Domain\Economics\Week9\Week9EconomicEngine;
use App\Domain\Economics\Week9\Week9EconomicInputs;
use App\Domain\Economics\Week9\Week9ReferencePackage;
use App\Models\CohortFeedbackEffect;
use Brick\Math\BigDecimal;
use Brick\Math\RoundingMode;
use Illuminate\Foundation\Testing\RefreshDatabase;
use InvalidArgumentException;
use Tests\TestCase;

class Week9EconomicEngineTest extends TestCase
{
    use RefreshDatabase;

    public function test_reference_package_loads_week9_markets_states_and_fixture_metadata(): void
    {
        $inputs = Week9ReferencePackage::fromRepository()->inputs();

        $this->assertSame(['LA_MS_core', 'gulf_secondary', 'southeast_edge'], array_keys($inputs->markets));
        $this->assertSame('strong', $inputs->market('LA_MS_core')->equity);
        $this->assertDecimalClose('-0.045', $inputs->market('LA_MS_core')->netPerFill());
        $this->assertDecimalClose('0.42', $inputs->nonfuelState('base'));
        $this->assertDecimalClose('0.38', $inputs->nonfuelState('price_war'));
        $this->assertDecimalClose('180000.0', $inputs->rebrandParameter('fills_per_site_year'));
        $this->assertSame('1.0.0-draft', $inputs->packageVersion);
        $this->assertArrayHasKey('fixtures/week9_golden.json', $inputs->sourceHashes);
    }

    public function test_week9_engine_matches_golden_fixture_results(): void
    {
        $inputs = Week9ReferencePackage::fromRepository()->inputs();
        $result = (new Week9EconomicEngine)->calculate($inputs);
        $golden = $inputs->golden['results'];

        $this->assertDecimalClose((string) $golden['cost_per_site'], $result->costPerSite);
        $this->assertDecimalClose((string) $golden['net_per_fill_LA_MS_core'], $result->marketResults['LA_MS_core']->netPerFill);
        $this->assertDecimalClose((string) $golden['gain_musd_LA_MS_core'], $result->marketResults['LA_MS_core']->baseGainMusd);
        $this->assertDecimalClose((string) $golden['cost_musd_LA_MS_core'], $result->marketResults['LA_MS_core']->costMusd);
        $this->assertDecimalClose((string) $golden['net_per_fill_gulf_secondary'], $result->marketResults['gulf_secondary']->netPerFill);
        $this->assertDecimalClose((string) $golden['gain_musd_gulf_secondary'], $result->marketResults['gulf_secondary']->baseGainMusd);
        $this->assertDecimalClose((string) $golden['cost_musd_gulf_secondary'], $result->marketResults['gulf_secondary']->costMusd);
        $this->assertDecimalClose((string) $golden['payback_years_gulf_secondary'], $result->marketResults['gulf_secondary']->paybackYears);
        $this->assertDecimalClose((string) $golden['net_per_fill_southeast_edge'], $result->marketResults['southeast_edge']->netPerFill);
        $this->assertDecimalClose((string) $golden['gain_musd_southeast_edge'], $result->marketResults['southeast_edge']->baseGainMusd);
        $this->assertDecimalClose((string) $golden['cost_musd_southeast_edge'], $result->marketResults['southeast_edge']->costMusd);
        $this->assertDecimalClose((string) $golden['payback_years_southeast_edge'], $result->marketResults['southeast_edge']->paybackYears);
        $this->assertDecimalClose((string) $golden['partial_gain_musd'], $result->partialGainMusd);
        $this->assertDecimalClose((string) $golden['partial_cost_musd'], $result->partialCostMusd);
        $this->assertDecimalClose((string) $golden['partial_payback_years'], $result->partialPaybackYears);
        $this->assertDecimalClose((string) $golden['full_net_gain_musd'], $result->fullNetGainMusd);
        $this->assertDecimalClose((string) $golden['pricewar_partial_payback_years'], $result->priceWarPartialPaybackYears);
    }

    public function test_worked_example_matches_package_reference_outputs(): void
    {
        $inputs = Week9ReferencePackage::fromRepository()->inputs();
        $workedExample = (new Week9EconomicEngine)->workedExample($inputs);
        $golden = $inputs->golden['worked_example'];

        $this->assertDecimalClose((string) $golden['market_a_gain_musd'], $workedExample['market_a_gain_musd']);
        $this->assertDecimalClose((string) $golden['market_b_gain_musd'], $workedExample['market_b_gain_musd']);
        $this->assertDecimalClose((string) $golden['market_b_cost_musd'], $workedExample['market_b_cost_musd']);
        $this->assertDecimalClose((string) $golden['market_b_payback_years'], $workedExample['market_b_payback_years']);
    }

    public function test_ordering_assertions_hold_without_forcing_a_decision(): void
    {
        $result = (new Week9EconomicEngine)->calculate(Week9ReferencePackage::fromRepository()->inputs());

        $this->assertTrue($result->marketResults['LA_MS_core']->netPerFill->isLessThan(BigDecimal::zero()));
        $this->assertTrue($result->marketResults['southeast_edge']->paybackYears?->isLessThan($result->marketResults['gulf_secondary']->paybackYears) ?? false);
        $this->assertTrue(
            $result->partialGainMusd->isGreaterThan($result->fullNetGainMusd->multipliedBy('2.9')),
            'Partial rebrand should earn about three times the full rebrand because core equity destruction offsets the full option.',
        );
        $this->assertTrue($result->priceWarPartialPaybackYears->isGreaterThan($result->partialPaybackYears));
    }

    public function test_fills_per_site_year_remains_a_package_calibration_lever(): void
    {
        $inputs = Week9ReferencePackage::fromRepository()->inputs();
        $base = (new Week9EconomicEngine)->calculate($inputs);
        $lowerThroughputInputs = new Week9EconomicInputs(
            markets: $inputs->markets,
            nonfuelStates: $inputs->nonfuelStates,
            rebrandParameters: array_merge($inputs->rebrandParameters, [
                'fills_per_site_year' => BigDecimal::of('90000'),
            ]),
            workedExampleParameters: $inputs->workedExampleParameters,
            golden: $inputs->golden,
            sourceHashes: $inputs->sourceHashes,
            packageVersion: $inputs->packageVersion,
        );

        $lowerThroughput = (new Week9EconomicEngine)->calculate($lowerThroughputInputs);

        $this->assertDecimalClose('2', $lowerThroughput->partialPaybackYears->dividedBy($base->partialPaybackYears, 12, RoundingMode::HalfUp));
    }

    public function test_unknown_nonfuel_state_is_rejected(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('non-fuel state');

        (new Week9EconomicEngine)->calculate(Week9ReferencePackage::fromRepository()->inputs(), 'invented_state');
    }

    public function test_week9_engine_does_not_create_or_mutate_cohort_feedback_effects(): void
    {
        $this->assertSame(0, CohortFeedbackEffect::query()->count());

        (new Week9EconomicEngine)->calculate(Week9ReferencePackage::fromRepository()->inputs());

        $this->assertSame(0, CohortFeedbackEffect::query()->count());
    }

    private function assertDecimalClose(string $expected, BigDecimal $actual): void
    {
        $expectedDecimal = BigDecimal::of($expected);
        $difference = $actual->minus($expectedDecimal);

        if ($difference->isLessThan(BigDecimal::zero())) {
            $difference = $difference->negated();
        }

        $relativeTolerance = $this->absolute($expectedDecimal)->multipliedBy('0.001');
        $absoluteTolerance = BigDecimal::of('0.00001');
        $tolerance = $relativeTolerance->isGreaterThan($absoluteTolerance)
            ? $relativeTolerance
            : $absoluteTolerance;

        $this->assertTrue(
            $difference->isLessThanOrEqualTo($tolerance),
            "Expected {$actual} to be within {$tolerance} of {$expectedDecimal}; difference {$difference}.",
        );
    }

    private function absolute(BigDecimal $value): BigDecimal
    {
        return $value->isLessThan(BigDecimal::zero()) ? $value->negated() : $value;
    }
}
