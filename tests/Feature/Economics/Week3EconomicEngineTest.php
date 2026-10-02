<?php

namespace Tests\Feature\Economics;

use App\Domain\Economics\Week3\Week3EconomicEngine;
use App\Domain\Economics\Week3\Week3ReferencePackage;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class Week3EconomicEngineTest extends TestCase
{
    use RefreshDatabase;

    public function test_week3_engine_matches_golden_shutdown_and_window1_outputs(): void
    {
        $package = Week3ReferencePackage::fromRepository();
        $this->assertTrue($package->isAvailable());

        $result = (new Week3EconomicEngine)->calculate($package->inputs());

        $this->assertSame('22.550000', $result->refineryResults['baton_rouge']->snapshot()['contribution']);
        $this->assertSame('20.350000', $result->refineryResults['baton_rouge']->snapshot()['net']);
        $this->assertSame('2.000000', $result->refineryResults['rotterdam']->snapshot()['contribution']);
        $this->assertSame('-0.300000', $result->refineryResults['rotterdam']->snapshot()['net']);
        $this->assertSame('-1.100000', (string) $result->rotterdamIdleDelta->toScale(6));
        $this->assertSame('3.250000', $result->windowResults['runs_hard']['crack']);
        $this->assertSame('4.600000', $result->windowResults['base']['crack']);
        $this->assertSame('5.950000', $result->windowResults['disciplines']['crack']);
    }

    public function test_week3_engine_is_deterministic(): void
    {
        $inputs = Week3ReferencePackage::fromRepository()->inputs();
        $engine = new Week3EconomicEngine;

        $first = $engine->calculate($inputs);
        $second = $engine->calculate($inputs);

        $this->assertSame($first->outputSnapshot, $second->outputSnapshot);
    }
}
