<?php

namespace Tests\Feature\Capital;

use App\Domain\Capital\Week6\Week6CapitalEconomicsEngine;
use App\Domain\Capital\Week6\Week6CapitalReferencePackage;
use Brick\Math\BigDecimal;
use Brick\Math\RoundingMode;
use Tests\TestCase;

class Week6CapitalEconomicsEngineTest extends TestCase
{
    public function test_reference_package_loads_project_cash_flows_and_fixture_metadata(): void
    {
        $inputs = Week6CapitalReferencePackage::fromRepository()->inputs();

        $this->assertSame(['baton_rouge', 'rotterdam', 'helix'], array_keys($inputs->projects));
        $this->assertSame('-640.000', $inputs->project('baton_rouge')->snapshot()[0]);
        $this->assertSame('345.000', $inputs->project('helix')->snapshot()[10]);
        $this->assertSame('1.0.0-draft', $inputs->packageVersion);
        $this->assertArrayHasKey('fixtures/week6_golden.json', $inputs->sourceHashes);
    }

    public function test_week6_npv_matches_golden_fixture_for_each_cohort(): void
    {
        $engine = new Week6CapitalEconomicsEngine;
        $inputs = Week6CapitalReferencePackage::fromRepository()->inputs();

        foreach ($inputs->golden['npv_by_cohort'] as $cohort => $fixture) {
            $rate = BigDecimal::of((string) $fixture['rate']);

            foreach ($fixture['npv'] as $project => $expected) {
                $actual = $engine->npv($rate, $inputs->project($project));

                $this->assertSame(
                    (string) BigDecimal::of((string) $expected)->toScale(2, RoundingMode::HalfUp),
                    (string) $actual->toScale(2, RoundingMode::HalfUp),
                    "NPV mismatch for {$cohort}/{$project}.",
                );
            }
        }
    }

    public function test_week6_irr_matches_golden_fixture(): void
    {
        $engine = new Week6CapitalEconomicsEngine;
        $inputs = Week6CapitalReferencePackage::fromRepository()->inputs();

        foreach ($inputs->golden['irr'] as $project => $expected) {
            $actual = $engine->irr($inputs->project($project));

            $this->assertSame(
                (string) BigDecimal::of((string) $expected)->toScale(4, RoundingMode::HalfUp),
                (string) $actual->toScale(4, RoundingMode::HalfUp),
                "IRR mismatch for {$project}.",
            );
        }
    }
}
