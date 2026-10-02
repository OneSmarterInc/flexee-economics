<?php

namespace Tests\Feature\Economics;

use App\Domain\Economics\Week2\Week2EconomicEngine;
use App\Domain\Economics\Week2\Week2ReferencePackage;
use Brick\Math\BigDecimal;
use Brick\Math\RoundingMode;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class Week2EconomicEngineTest extends TestCase
{
    use RefreshDatabase;

    public function test_week2_engine_matches_golden_elasticity_outputs(): void
    {
        $package = Week2ReferencePackage::fromRepository();
        $this->assertTrue($package->isAvailable());

        $inputs = $package->inputs();
        $result = (new Week2EconomicEngine)->calculate($inputs);
        $golden = $inputs->golden['results'];

        foreach ($result->clusterResults as $clusterKey => $clusterResult) {
            $this->assertWithinPackageTolerance($golden['est_'.$clusterKey], $clusterResult->estimatedElasticity);
            $this->assertWithinPackageTolerance($golden['design_'.$clusterKey], $clusterResult->designElasticity);
        }

        $this->assertWithinPackageTolerance($golden['cordell_weighted_est'], $result->cordellWeightedEstimate);
        $this->assertWithinPackageTolerance($golden['europe_weighted_est'], $result->europeWeightedEstimate);
        $this->assertWithinPackageTolerance($golden['cordell_weighted_passthrough'], $result->cordellWeightedPassthrough);
        $this->assertWithinPackageTolerance($golden['vol_response_pct_urban_high_comp'], $result->clusterResults['urban_high_comp']->volumeResponsePct);
        $this->assertWithinPackageTolerance($golden['vol_response_pct_rural_low_comp'], $result->clusterResults['rural_low_comp']->volumeResponsePct);
        $this->assertWithinPackageTolerance($inputs->golden['worked_example']['worked_elasticity'], $result->workedExample['worked_elasticity']);
        $this->assertWithinPackageTolerance($inputs->golden['worked_example']['worked_vol_response_pct'], $result->workedExample['worked_vol_response_pct']);
    }

    public function test_week2_engine_is_deterministic(): void
    {
        $inputs = Week2ReferencePackage::fromRepository()->inputs();
        $engine = new Week2EconomicEngine;

        $first = $engine->calculate($inputs);
        $second = $engine->calculate($inputs);

        $this->assertSame($first->outputSnapshot, $second->outputSnapshot);
    }

    private function assertWithinPackageTolerance(string|int|float $expected, BigDecimal $actual): void
    {
        $expectedDecimal = BigDecimal::of((string) $expected);
        $difference = $actual->minus($expectedDecimal);

        if ($difference->isLessThan(BigDecimal::zero())) {
            $difference = $difference->negated();
        }

        $absoluteTolerance = BigDecimal::of('0.00001');
        $relativeTolerance = $expectedDecimal->abs()->multipliedBy('0.001');
        $tolerance = $relativeTolerance->isGreaterThan($absoluteTolerance) ? $relativeTolerance : $absoluteTolerance;

        $this->assertTrue(
            $difference->isLessThanOrEqualTo($tolerance),
            'Expected '.$this->display($actual).' to be within '.$this->display($tolerance).' of '.$this->display($expectedDecimal),
        );
    }

    private function display(BigDecimal $value): string
    {
        return (string) $value->toScale(8, RoundingMode::HalfUp);
    }
}
