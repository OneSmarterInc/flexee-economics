<?php

namespace Tests\Feature\Economics;

use App\Domain\Economics\Week13\Week13EconomicEngine;
use App\Domain\Economics\Week13\Week13ReferencePackage;
use App\Models\CohortFeedbackEffect;
use App\Models\ConsequenceLink;
use App\Models\KpiSnapshot;
use App\Models\RankingSnapshot;
use App\Models\StandingState;
use Brick\Math\BigDecimal;
use Brick\Math\RoundingMode;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class Week13EconomicEngineTest extends TestCase
{
    use RefreshDatabase;

    public function test_reference_package_loads_week13_artifacts_and_validates_hashes(): void
    {
        $inputs = Week13ReferencePackage::fromRepository()->inputs();

        $this->assertSame('1.0.0-draft', $inputs->packageVersion);
        $this->assertSame(['wage_bill_musd', 'union_demand_pct', 'tax_rate'], array_keys($inputs->norwayUnion));
        $this->assertSame(['bbl_per_marginal_worker', 'margin_per_bbl', 'market_wage_k'], array_keys($inputs->permianLabor));
        $this->assertSame(
            ['labor_cost_base_musd', 'peak_multiplier', 'outage_probability_if_delayed', 'outage_cost_musd', 'asset_health_penalty_pts'],
            array_keys($inputs->turnaround),
        );
        $this->assertSame(['Norwegian offshore', 'Permian field', 'Gulf Coast contractor'], array_keys($inputs->wageBenchmarks));
        $this->assertDecimalClose('420.0', $inputs->norwayParameter('wage_bill_musd'));
        $this->assertDecimalClose('145.0', $inputs->wageBenchmark('Permian field')['benchmark_wage_k']);
        $this->assertArrayHasKey('fixtures/week13_golden.json', $inputs->sourceHashes);

        foreach ($inputs->sourceHashes as $relativePath => $expectedHash) {
            $path = base_path('halden-week13-data-package'.DIRECTORY_SEPARATOR.str_replace('/', DIRECTORY_SEPARATOR, $relativePath));
            $this->assertFileExists($path);
            $this->assertSame($expectedHash, hash_file('sha256', $path), "Hash mismatch for {$relativePath}.");
        }
    }

    public function test_week13_engine_matches_golden_fixture_results(): void
    {
        $inputs = Week13ReferencePackage::fromRepository()->inputs();
        $result = (new Week13EconomicEngine)->calculate($inputs);
        $golden = $inputs->golden['results'];

        $this->assertDecimalClose((string) $golden['norway_gross_cost_musd'], $result->norwayGrossCostMusd);
        $this->assertDecimalClose((string) $golden['norway_after_tax_cost_musd'], $result->norwayAfterTaxCostMusd);
        $this->assertDecimalClose((string) $golden['norway_after_tax_share'], $result->norwayAfterTaxShare);
        $this->assertDecimalClose((string) $golden['permian_mrp_k'], $result->permianMrpK);
        $this->assertDecimalClose((string) $golden['mrp_to_wage'], $result->mrpToWage);
        $this->assertDecimalClose((string) $golden['turnaround_peak_cost_musd'], $result->turnaroundPeakCostMusd);
        $this->assertDecimalClose((string) $golden['delay_expected_cost_musd'], $result->delayExpectedCostMusd);
        $this->assertDecimalClose((string) $golden['delay_saving_musd'], $result->delaySavingMusd);
        $this->assertDecimalClose((string) $golden['delay_saving_pct'], $result->delaySavingPct);
        $this->assertDecimalClose((string) $golden['asset_health_penalty_pts'], $result->assetHealthPenaltyPts);
        $this->assertSame(Week13EconomicEngine::ENGINE_IDENTIFIER, $result->engineIdentifier);
        $this->assertSame(Week13EconomicEngine::ENGINE_VERSION, $result->engineVersion);
    }

    public function test_worked_example_matches_package_reference_outputs(): void
    {
        $inputs = Week13ReferencePackage::fromRepository()->inputs();
        $workedExample = (new Week13EconomicEngine)->workedExample($inputs);
        $golden = $inputs->golden['worked_example'];

        $this->assertDecimalClose((string) $golden['mrp'], $workedExample['mrp']);
        $this->assertDecimalClose((string) $golden['mrp_minus_wage'], $workedExample['mrp_minus_wage']);
        $this->assertDecimalClose((string) $golden['after_tax_wage_increase'], $workedExample['after_tax_wage_increase']);
    }

    public function test_factor_market_relationships_remain_package_outputs_only(): void
    {
        $result = (new Week13EconomicEngine)->calculate(Week13ReferencePackage::fromRepository()->inputs());

        $this->assertSame('0.220000', $result->norwayAfterTaxShareDisplay());
        $this->assertTrue($result->permianMrpK->isGreaterThan($result->wageBenchmarks['Permian field']['benchmark_wage_k']));
        $this->assertDecimalClose('0.35', $result->turnaroundPeakCostMusd->minus('60.0')->dividedBy('60.0', 18, RoundingMode::HalfUp));
        $this->assertTrue($result->delaySavingPct->isLessThanOrEqualTo('0.05'));
        $this->assertDecimalClose('3.0', $result->assetHealthPenaltyPts);

        $this->assertSame([
            'norway_concession_costs_twenty_two_pct_of_face' => true,
            'permian_mrp_covers_market_wage' => true,
            'contractor_peak_is_thirty_five_pct_above_base' => true,
            'turnaround_expected_costs_within_five_pct' => true,
            'delaying_carries_asset_health_penalty' => true,
        ], $result->orderingAssertions);
    }

    public function test_week13_engine_is_deterministic_and_decimal_safe(): void
    {
        $inputs = Week13ReferencePackage::fromRepository()->inputs();
        $first = (new Week13EconomicEngine)->calculate($inputs);
        $second = (new Week13EconomicEngine)->calculate($inputs);

        $this->assertSame($first->outputSnapshot, $second->outputSnapshot);
        $this->assertSame('3.500690', $first->mrpToWageDisplay());
        $this->assertSame('0.037037', $first->delaySavingPctDisplay());
        $this->assertSame('7.392000', (string) $first->norwayAfterTaxCostMusd->toScale(6, RoundingMode::HalfUp));
    }

    public function test_week13_engine_does_not_create_downstream_state(): void
    {
        (new Week13EconomicEngine)->calculate(Week13ReferencePackage::fromRepository()->inputs());

        $this->assertSame(0, KpiSnapshot::query()->count());
        $this->assertSame(0, RankingSnapshot::query()->count());
        $this->assertSame(0, StandingState::query()->count());
        $this->assertSame(0, ConsequenceLink::query()->count());
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
