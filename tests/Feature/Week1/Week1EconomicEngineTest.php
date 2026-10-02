<?php

namespace Tests\Feature\Week1;

use App\Domain\Economics\Week1\Week1EconomicEngine;
use App\Domain\Economics\Week1\Week1ReferencePackage;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class Week1EconomicEngineTest extends TestCase
{
    use RefreshDatabase;

    public function test_week1_engine_matches_golden_asset_register_outputs(): void
    {
        $package = Week1ReferencePackage::fromRepository();
        $this->assertTrue($package->isAvailable());

        $inputs = $package->inputs();
        $result = app(Week1EconomicEngine::class)->calculate($inputs);
        $golden = $inputs->golden['results'];

        $this->assertSame((string) $golden['economic_rank'], $result->economicRank);
        $this->assertSame((string) $golden['reported_rank'], $result->reportedRank);
        $this->assertSame((string) $golden['top_economic_asset'], $result->topEconomicAsset);
        $this->assertSame('70.500000', $result->outputSnapshot['permian_realized']);
        $this->assertSame('56.400000', $result->outputSnapshot['permian_margin']);
        $this->assertSame('47.000000', $result->outputSnapshot['norway_pretax']);
        $this->assertSame('10.340000', $result->outputSnapshot['norway_posttax']);
        $this->assertSame('-0.440000', $result->outputSnapshot['norway_2usd_loss_posttax']);
        $this->assertSame('25.460000', $result->outputSnapshot['kessana_company']);
        $this->assertSame('20.350000', $result->outputSnapshot['br_net']);
        $this->assertSame('2.000000', $result->outputSnapshot['rot_contribution']);
        $this->assertSame('-0.300000', $result->outputSnapshot['rot_net']);
        $this->assertSame('2.600000', $result->outputSnapshot['rot_shutdown_crack']);
        $this->assertSame('4.600000', $result->outputSnapshot['rot_current_crack']);
        $this->assertSame('3.440000', $result->outputSnapshot['sing_halden_share']);

        foreach ($result->orderingAssertions as $assertion) {
            $this->assertTrue($assertion);
        }
    }

    public function test_week1_engine_matches_worked_example_and_is_deterministic(): void
    {
        $inputs = Week1ReferencePackage::fromRepository()->inputs();
        $engine = app(Week1EconomicEngine::class);

        $first = $engine->calculate($inputs);
        $second = $engine->calculate($inputs);

        $this->assertSame($first->outputSnapshot, $second->outputSnapshot);
        $this->assertSame('40.000000', $first->workedExample['x_reported_gross']);
        $this->assertSame('12.000000', $first->workedExample['x_posttax']);
        $this->assertSame('1.500000', $first->workedExample['y_contribution']);
        $this->assertSame('-0.500000', $first->workedExample['y_net']);
    }
}
