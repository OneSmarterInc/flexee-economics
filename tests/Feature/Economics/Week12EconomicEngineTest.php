<?php

namespace Tests\Feature\Economics;

use App\Domain\Economics\Week12\Week12EconomicEngine;
use App\Domain\Economics\Week12\Week12ReferencePackage;
use App\Models\CohortFeedbackEffect;
use App\Models\ConsequenceLink;
use App\Models\KpiSnapshot;
use App\Models\RankingSnapshot;
use App\Models\StandingState;
use Brick\Math\BigDecimal;
use Brick\Math\RoundingMode;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class Week12EconomicEngineTest extends TestCase
{
    use RefreshDatabase;

    public function test_reference_package_loads_week12_artifacts_and_validates_hashes(): void
    {
        $inputs = Week12ReferencePackage::fromRepository()->inputs();

        $this->assertSame('1.0.0-draft', $inputs->packageVersion);
        $this->assertSame(['total_envelope', 'sustaining_floor'], array_keys($inputs->envelope));
        $this->assertSame(['sustaining', 'growth', 'adjacent', 'renewable'], array_keys($inputs->buckets));
        $this->assertSame(
            ['helix_rotterdam', 'permian_expansion', 'biofuel_conversion', 'offshore_wind', 'euro_retail_divest'],
            array_keys($inputs->projects),
        );
        $this->assertSame(['low', 'mid', 'high'], array_keys($inputs->carbonScenarios));
        $this->assertSame(['slow_decline', 'fast_decline', 'collapse'], array_keys($inputs->demandScenarios));
        $this->assertArrayHasKey('fixtures/week12_golden.json', $inputs->sourceHashes);

        foreach ($inputs->sourceHashes as $relativePath => $expectedHash) {
            $path = base_path('halden-week12-data-package'.DIRECTORY_SEPARATOR.str_replace('/', DIRECTORY_SEPARATOR, $relativePath));
            $this->assertFileExists($path);
            $this->assertSame($expectedHash, hash_file('sha256', $path), "Hash mismatch for {$relativePath}.");
        }
    }

    public function test_week12_engine_matches_golden_fixture_results(): void
    {
        $inputs = Week12ReferencePackage::fromRepository()->inputs();
        $result = (new Week12EconomicEngine)->calculate($inputs);
        $golden = $inputs->golden['results'];

        $this->assertDecimalClose((string) $golden['discretionary'], $result->discretionaryEnvelopeMusd);
        $this->assertDecimalClose((string) $golden['helix_rotterdam_cost'], $result->helixRotterdamCostMusd);
        $this->assertDecimalClose((string) $golden['adjacent_ceiling'], $result->adjacentCeilingMusd);
        $this->assertDecimalClose((string) $golden['envelope_with_divest'], $result->envelopeWithDivestmentMusd);
        $this->assertDecimalClose((string) $golden['hr_plus_wind_cost'], $result->hrPlusWindCostMusd);
        $this->assertTrue($result->hrPlusWindNeedsDivestment);
        $this->assertSame($golden['feasible_portfolios'], $result->feasiblePortfolioCount);
        $this->assertSame($golden['feasible_with_helix_rotterdam'], $result->feasibleWithHelixRotterdamCount);
        $this->assertSame($golden['portfolios_unlocked_by_divest'], $result->portfoliosUnlockedByDivestmentCount);
    }

    public function test_project_scenario_npvs_match_golden_fixture_ranges(): void
    {
        $inputs = Week12ReferencePackage::fromRepository()->inputs();
        $result = (new Week12EconomicEngine)->calculate($inputs);
        $golden = $inputs->golden['results'];

        foreach (array_keys($inputs->projects) as $projectKey) {
            $this->assertDecimalClose(
                (string) $golden['npv_min_'.$projectKey],
                $result->projectNpvRanges[$projectKey]['min'],
            );
            $this->assertDecimalClose(
                (string) $golden['npv_max_'.$projectKey],
                $result->projectNpvRanges[$projectKey]['max'],
            );
            $this->assertDecimalClose(
                (string) $golden['npv_lowcarbon_slow_'.$projectKey],
                $result->projectScenarioNpvs[$projectKey]['low_slow_decline'],
            );
            $this->assertDecimalClose(
                (string) $golden['npv_highcarbon_collapse_'.$projectKey],
                $result->projectScenarioNpvs[$projectKey]['high_collapse'],
            );
        }
    }

    public function test_portfolio_constraints_preserve_helix_and_divestment_mechanics(): void
    {
        $result = (new Week12EconomicEngine)->calculate(Week12ReferencePackage::fromRepository()->inputs());

        $helix = $result->portfolioContaining('helix_rotterdam');
        $helixWithWind = $result->portfolioContaining('helix_rotterdam', 'offshore_wind');
        $helixWithWindAndDivestment = $result->portfolioContaining('helix_rotterdam', 'offshore_wind', 'euro_retail_divest');
        $helixWithBiofuel = $result->portfolioContaining('helix_rotterdam', 'biofuel_conversion');

        $this->assertNotNull($helix);
        $this->assertTrue($helix->feasible);
        $this->assertDecimalClose('1200.0', $helix->capitalRequiredMusd);
        $this->assertDecimalClose('1200.0', $helix->bucketSpend['adjacent']);

        $this->assertNotNull($helixWithWind);
        $this->assertFalse($helixWithWind->feasible);
        $this->assertContains('capital_envelope_exceeded', $helixWithWind->constraintFailures);

        $this->assertNotNull($helixWithWindAndDivestment);
        $this->assertTrue($helixWithWindAndDivestment->feasible);
        $this->assertTrue($helixWithWindAndDivestment->unlockedByDivestment);
        $this->assertDecimalClose('1750.0', $helixWithWindAndDivestment->capitalRequiredMusd);
        $this->assertDecimalClose('550.0', $helixWithWindAndDivestment->divestmentProceedsMusd);

        $this->assertNotNull($helixWithBiofuel);
        $this->assertFalse($helixWithBiofuel->feasible);
        $this->assertContains('bucket_ceiling_exceeded:adjacent', $helixWithBiofuel->constraintFailures);
    }

    public function test_worked_example_matches_package_reference_outputs(): void
    {
        $inputs = Week12ReferencePackage::fromRepository()->inputs();
        $workedExample = (new Week12EconomicEngine)->workedExample($inputs);
        $golden = $inputs->golden['worked_example'];

        $this->assertDecimalClose((string) $golden['p1_low'], $workedExample['p1_low']);
        $this->assertDecimalClose((string) $golden['p1_high'], $workedExample['p1_high']);
        $this->assertDecimalClose((string) $golden['p2_low'], $workedExample['p2_low']);
        $this->assertDecimalClose((string) $golden['p2_high'], $workedExample['p2_high']);
    }

    public function test_week12_engine_is_deterministic_and_decimal_safe(): void
    {
        $inputs = Week12ReferencePackage::fromRepository()->inputs();
        $first = (new Week12EconomicEngine)->calculate($inputs);
        $second = (new Week12EconomicEngine)->calculate($inputs);

        $this->assertSame($first->outputSnapshot, $second->outputSnapshot);
        $this->assertSame('1200.0000', $first->discretionaryDisplay());
        $this->assertSame('308.000000', (string) $first->projectNpvRanges['helix_rotterdam']['max']->toScale(6, RoundingMode::HalfUp));
        $this->assertSame('17', (string) BigDecimal::of($first->feasiblePortfolioCount));
    }

    public function test_week12_engine_does_not_create_downstream_state(): void
    {
        (new Week12EconomicEngine)->calculate(Week12ReferencePackage::fromRepository()->inputs());

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
