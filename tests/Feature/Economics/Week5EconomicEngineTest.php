<?php

namespace Tests\Feature\Economics;

use App\Domain\Economics\Week5\Week5EconomicEngine;
use App\Domain\Economics\Week5\Week5EconomicInputs;
use App\Domain\Economics\Week5\Week5Hedge;
use App\Domain\Economics\Week5\Week5ReferencePackage;
use App\Models\EconomicResolution;
use App\Models\Week8EconomicEvaluation;
use App\Models\Week9EconomicEvaluation;
use Brick\Math\BigDecimal;
use Illuminate\Foundation\Testing\RefreshDatabase;
use InvalidArgumentException;
use Tests\TestCase;

class Week5EconomicEngineTest extends TestCase
{
    use RefreshDatabase;

    public function test_reference_package_loads_week5_currency_inputs_and_fixture_metadata(): void
    {
        $inputs = Week5ReferencePackage::fromRepository()->inputs();

        $this->assertSame(['EURUSD', 'USDNOK', 'USDSGD'], array_keys($inputs->fxRates));
        $this->assertDecimalClose('1.085', $inputs->fxRate('EURUSD')->pre);
        $this->assertDecimalClose('1.015', $inputs->fxRate('EURUSD')->post);
        $this->assertSame('Norwegian upstream', $inputs->entityFlow('norway_costs')->entity);
        $this->assertSame('NOK', $inputs->entityFlow('norway_costs')->currency);
        $this->assertDecimalClose('1100', $inputs->entityFlow('norway_costs')->annualMusdPre);
        $this->assertSame('sell_EUR', $inputs->hedge('eur_forward_sell')->direction);
        $this->assertDecimalClose('300', $inputs->hedge('eur_forward_sell')->notionalMusd);
        $this->assertDecimalClose('1.031', $inputs->forwardRates['EURUSD']);
        $this->assertDecimalClose('0.012', $inputs->collarPremiums['EURUSD']);
        $this->assertSame('1.0.0-draft', $inputs->packageVersion);
        $this->assertArrayHasKey('fixtures/week5_golden.json', $inputs->sourceHashes);
    }

    public function test_week5_engine_matches_golden_fixture_results(): void
    {
        $inputs = Week5ReferencePackage::fromRepository()->inputs();
        $result = (new Week5EconomicEngine)->calculate($inputs);
        $golden = $inputs->golden['results'];

        $this->assertDecimalClose((string) $golden['eur_change'], $result->eurChange);
        $this->assertDecimalClose((string) $golden['nok_usd_value_change'], $result->nokUsdValueChange);
        $this->assertDecimalClose((string) $golden['sgd_usd_value_change'], $result->sgdUsdValueChange);
        $this->assertDecimalClose((string) $golden['norway_benefit_musd'], $result->norwayBenefitMusd);
        $this->assertDecimalClose((string) $golden['norway_lifting_post'], $result->norwayLiftingPost);
        $this->assertDecimalClose((string) $golden['euro_retail_translation_musd'], $result->euroRetailTranslationMusd);
        $this->assertDecimalClose((string) $golden['existing_hedge_gain_musd'], $result->existingHedgeGainMusd);
        $this->assertDecimalClose((string) $golden['rot_net_eur_musd'], $result->rotNetEurMusd);
        $this->assertDecimalClose((string) $golden['rot_natural_hedge_ratio'], $result->rotNaturalHedgeRatio);
        $this->assertDecimalClose((string) $golden['rot_net_impact_musd'], $result->rotNetImpactMusd);
        $this->assertDecimalClose((string) $golden['rot_overhedge_loss_musd'], $result->rotOverhedgeLossMusd);
        $this->assertDecimalClose((string) $golden['sing_impact_musd'], $result->singImpactMusd);
    }

    public function test_worked_example_matches_package_reference_outputs(): void
    {
        $inputs = Week5ReferencePackage::fromRepository()->inputs();
        $workedExample = (new Week5EconomicEngine)->workedExample($inputs);
        $golden = $inputs->golden['worked_example'];

        $this->assertDecimalClose((string) $golden['fx_change'], $workedExample['fx_change']);
        $this->assertDecimalClose((string) $golden['net_exposure'], $workedExample['net_exposure']);
        $this->assertDecimalClose((string) $golden['net_impact'], $workedExample['net_impact']);
        $this->assertDecimalClose((string) $golden['wrong_hedge_on_gross_costs'], $workedExample['wrong_hedge_on_gross_costs']);
    }

    public function test_week5_ordering_assertions_hold_from_package_calculations(): void
    {
        $result = (new Week5EconomicEngine)->calculate(Week5ReferencePackage::fromRepository()->inputs());

        $this->assertTrue($result->norwayBenefitMusd->isGreaterThan(BigDecimal::zero()));
        $this->assertTrue($result->norwayLiftingPost->isLessThan(BigDecimal::of('28')));
        $this->assertTrue($result->euroRetailTranslationMusd->isLessThan(BigDecimal::zero()));
        $this->assertTrue($result->rotNaturalHedgeRatio->isGreaterThanOrEqualTo(BigDecimal::of('0.9')));
        $this->assertTrue($result->rotNaturalHedgeRatio->isLessThanOrEqualTo(BigDecimal::one()));
        $this->assertDecimalClose('9', $result->rotOverhedgeLossMusd->dividedBy($result->rotNetImpactMusd, 12));
        $this->assertTrue($result->singImpactMusd->abs()->isLessThan(BigDecimal::of('3')));
    }

    public function test_week5_engine_uses_package_data_as_calibration_not_application_constants(): void
    {
        $inputs = Week5ReferencePackage::fromRepository()->inputs();
        $base = (new Week5EconomicEngine)->calculate($inputs);
        $largerHedgeInputs = new Week5EconomicInputs(
            fxRates: $inputs->fxRates,
            entityFlows: $inputs->entityFlows,
            norwayUnitCosts: $inputs->norwayUnitCosts,
            existingHedges: array_merge($inputs->existingHedges, [
                'eur_forward_sell' => new Week5Hedge(
                    hedgeId: 'eur_forward_sell',
                    pair: 'EURUSD',
                    direction: 'sell_EUR',
                    notionalMusd: BigDecimal::of('600'),
                    maturityMonths: BigDecimal::of('6'),
                ),
            ]),
            forwardRates: $inputs->forwardRates,
            collarPremiums: $inputs->collarPremiums,
            workedExampleParameters: $inputs->workedExampleParameters,
            golden: $inputs->golden,
            sourceHashes: $inputs->sourceHashes,
            packageVersion: $inputs->packageVersion,
        );

        $largerHedge = (new Week5EconomicEngine)->calculate($largerHedgeInputs);

        $this->assertDecimalClose('2', $largerHedge->existingHedgeGainMusd->dividedBy($base->existingHedgeGainMusd, 12));
    }

    public function test_unknown_reference_key_is_rejected(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('FX rate');

        Week5ReferencePackage::fromRepository()->inputs()->fxRate('GBPUSD');
    }

    public function test_week5_engine_does_not_create_or_mutate_runtime_evaluations(): void
    {
        $this->assertSame(0, EconomicResolution::query()->count());
        $this->assertSame(0, Week8EconomicEvaluation::query()->count());
        $this->assertSame(0, Week9EconomicEvaluation::query()->count());

        (new Week5EconomicEngine)->calculate(Week5ReferencePackage::fromRepository()->inputs());

        $this->assertSame(0, EconomicResolution::query()->count());
        $this->assertSame(0, Week8EconomicEvaluation::query()->count());
        $this->assertSame(0, Week9EconomicEvaluation::query()->count());
    }

    private function assertDecimalClose(string $expected, BigDecimal $actual): void
    {
        $expectedDecimal = BigDecimal::of($expected);
        $difference = $actual->minus($expectedDecimal)->abs();
        $relativeTolerance = $expectedDecimal->abs()->multipliedBy('0.001');
        $absoluteTolerance = BigDecimal::of('0.00001');
        $tolerance = $relativeTolerance->isGreaterThan($absoluteTolerance)
            ? $relativeTolerance
            : $absoluteTolerance;

        $this->assertTrue(
            $difference->isLessThanOrEqualTo($tolerance),
            "Expected {$actual} to be within {$tolerance} of {$expectedDecimal}; difference {$difference}.",
        );
    }
}
