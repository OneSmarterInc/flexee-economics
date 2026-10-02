<?php

namespace Tests\Feature\Economics;

use App\Domain\Economics\Week7\Week7EconomicEngine;
use App\Domain\Economics\Week7\Week7ReferencePackage;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class Week7EconomicEngineTest extends TestCase
{
    use RefreshDatabase;

    public function test_week7_engine_matches_golden_competitive_response_and_window3_outputs(): void
    {
        $package = Week7ReferencePackage::fromRepository();
        $this->assertTrue($package->isAvailable());

        $result = (new Week7EconomicEngine)->calculate($package->inputs());

        $urban = $result->clusterResults['urban_high_comp']->snapshot();
        $this->assertSame('-0.206250', $urban['at_risk_pct']);
        $this->assertSame('66.000000', $urban['match_cost_musd']);
        $this->assertSame('0.328969', $urban['ignore_cost_musd']);
        $this->assertSame('ignore', $urban['retail_decision']);
        $this->assertSame('-28.000000', (string) $result->evHoldMusd->toScale(6));
        $this->assertSame('-80.000000', (string) $result->evMatchMusd->toScale(6));
        $this->assertSame('0.375000', (string) $result->breakevenBuildProbability->toScale(6));
        $this->assertSame('hold', $result->capacityDecision);
        $this->assertSame('0.380000', $result->windowResults['price_war']['nonfuel_margin']);
        $this->assertSame('0.420000', $result->windowResults['base']['nonfuel_margin']);
        $this->assertSame('0.450000', $result->windowResults['disciplined']['nonfuel_margin']);
    }

    public function test_week7_engine_is_deterministic(): void
    {
        $inputs = Week7ReferencePackage::fromRepository()->inputs();
        $engine = new Week7EconomicEngine;

        $first = $engine->calculate($inputs);
        $second = $engine->calculate($inputs);

        $this->assertSame($first->outputSnapshot, $second->outputSnapshot);
    }
}
