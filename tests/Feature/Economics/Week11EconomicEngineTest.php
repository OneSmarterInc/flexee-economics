<?php

namespace Tests\Feature\Economics;

use App\Domain\Economics\Week11\Week11EconomicEngine;
use App\Domain\Economics\Week11\Week11EconomicInputs;
use App\Domain\Economics\Week11\Week11ReferencePackage;
use App\Models\CohortFeedbackEffect;
use App\Models\ConsequenceLink;
use App\Models\KpiSnapshot;
use App\Models\RankingSnapshot;
use App\Models\StandingState;
use Brick\Math\BigDecimal;
use Brick\Math\RoundingMode;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class Week11EconomicEngineTest extends TestCase
{
    use RefreshDatabase;

    public function test_reference_package_loads_week11_artifacts_and_validates_hashes(): void
    {
        $inputs = Week11ReferencePackage::fromRepository()->inputs();

        $this->assertSame('1.0.0-draft', $inputs->packageVersion);
        $this->assertSame(['current', 'mid', 'demanded', 'harsh'], array_keys($inputs->takeGrid));
        $this->assertSame(['Deepwater A', 'Deepwater B', 'Shelf C', 'Frontier D', 'Mature E', 'Deepwater F'], array_keys($inputs->comparableTerms));
        $this->assertDecimalClose('78.5', $inputs->pscTerm('brent'));
        $this->assertDecimalClose('340.0', $inputs->reserve('remaining_mbbl'));
        $this->assertDecimalClose('2800.0', $inputs->exitAndSunk('sunk_capital_musd_reference_only'));
        $this->assertArrayHasKey('fixtures/week11_golden.json', $inputs->sourceHashes);

        foreach ($inputs->sourceHashes as $relativePath => $expectedHash) {
            $path = base_path('halden-week11-data-package'.DIRECTORY_SEPARATOR.str_replace('/', DIRECTORY_SEPARATOR, $relativePath));
            $this->assertFileExists($path);
            $this->assertSame($expectedHash, hash_file('sha256', $path), "Hash mismatch for {$relativePath}.");
        }
    }

    public function test_week11_engine_matches_golden_fixture_results(): void
    {
        $inputs = Week11ReferencePackage::fromRepository()->inputs();
        $result = (new Week11EconomicEngine)->calculate($inputs);
        $golden = $inputs->golden['results'];

        $this->assertDecimalClose((string) $golden['profit_oil'], $result->profitOil);
        $this->assertDecimalClose((string) $golden['annual_mbbl'], $result->annualMbbl);
        $this->assertDecimalClose((string) $golden['annuity_factor'], $result->annuityFactor);
        $this->assertDecimalClose((string) $golden['margin_current'], $result->takeResult('current')->companyMarginPerBbl);
        $this->assertDecimalClose((string) $golden['pv_stay_current'], $result->takeResult('current')->pvStayMusd);
        $this->assertDecimalClose((string) $golden['margin_mid'], $result->takeResult('mid')->companyMarginPerBbl);
        $this->assertDecimalClose((string) $golden['pv_stay_mid'], $result->takeResult('mid')->pvStayMusd);
        $this->assertDecimalClose((string) $golden['margin_demanded'], $result->takeResult('demanded')->companyMarginPerBbl);
        $this->assertDecimalClose((string) $golden['pv_stay_demanded'], $result->takeResult('demanded')->pvStayMusd);
        $this->assertDecimalClose((string) $golden['margin_harsh'], $result->takeResult('harsh')->companyMarginPerBbl);
        $this->assertDecimalClose((string) $golden['pv_stay_harsh'], $result->takeResult('harsh')->pvStayMusd);
        $this->assertDecimalClose((string) $golden['exit_value'], $result->exitValueMusd);
        $this->assertDecimalClose((string) $golden['stay_minus_exit_demanded'], $result->takeResult('demanded')->stayMinusExitMusd);
        $this->assertDecimalClose((string) $golden['indifference_take'], $result->indifferenceTake);
        $this->assertDecimalClose((string) $golden['comparables_min'], $result->comparablesMin);
        $this->assertDecimalClose((string) $golden['comparables_max'], $result->comparablesMax);
        $this->assertDecimalClose((string) $golden['demanded_take'], $result->demandedTake);
        $this->assertTrue($result->sunkInvariant);
    }

    public function test_worked_example_matches_package_reference_outputs(): void
    {
        $inputs = Week11ReferencePackage::fromRepository()->inputs();
        $workedExample = (new Week11EconomicEngine)->workedExample($inputs);
        $golden = $inputs->golden['worked_example'];

        $this->assertDecimalClose((string) $golden['annuity_factor'], $workedExample['annuity_factor']);
        $this->assertDecimalClose((string) $golden['pv_before'], $workedExample['pv_before']);
        $this->assertDecimalClose((string) $golden['pv_after'], $workedExample['pv_after']);
        $this->assertDecimalClose((string) $golden['indifference_take'], $workedExample['indifference_take']);
    }

    public function test_ordering_assertions_hold_without_forcing_a_negotiation_outcome(): void
    {
        $result = (new Week11EconomicEngine)->calculate(Week11ReferencePackage::fromRepository()->inputs());

        $this->assertTrue($result->stayingBeatsExitAcrossTakeGrid);
        $this->assertTrue($result->stayValueFallsAsTakeRises);
        $this->assertTrue($result->takeResult('harsh')->stayMinusExitMusd->isGreaterThan(BigDecimal::zero()));
        $this->assertTrue($result->indifferenceTake->isGreaterThan('0.95'));
        $this->assertTrue($result->demandedTakeInsideComparables);
        $this->assertTrue($result->takeResult('current')->companyMarginPerBbl->isGreaterThan($result->takeResult('demanded')->companyMarginPerBbl));
    }

    public function test_sunk_capital_is_not_a_forward_decision_input(): void
    {
        $inputs = Week11ReferencePackage::fromRepository()->inputs();
        $base = (new Week11EconomicEngine)->calculate($inputs);
        $changedSunkInputs = new Week11EconomicInputs(
            pscTerms: $inputs->pscTerms,
            reserves: $inputs->reserves,
            exitAndSunk: array_merge($inputs->exitAndSunk, [
                'sunk_capital_musd_reference_only' => BigDecimal::of('9999.0'),
            ]),
            takeGrid: $inputs->takeGrid,
            comparableTerms: $inputs->comparableTerms,
            workedExample: $inputs->workedExample,
            golden: $inputs->golden,
            sourceHashes: $inputs->sourceHashes,
            packageVersion: $inputs->packageVersion,
        );
        $changed = (new Week11EconomicEngine)->calculate($changedSunkInputs);

        $this->assertDecimalClose((string) $base->takeResult('demanded')->pvStayMusd, $changed->takeResult('demanded')->pvStayMusd);
        $this->assertDecimalClose((string) $base->indifferenceTake, $changed->indifferenceTake);
        $this->assertTrue($changed->sunkInvariant);
    }

    public function test_week11_engine_is_deterministic_and_decimal_safe(): void
    {
        $inputs = Week11ReferencePackage::fromRepository()->inputs();
        $first = (new Week11EconomicEngine)->calculate($inputs);
        $second = (new Week11EconomicEngine)->calculate($inputs);

        $this->assertSame($first->outputSnapshot, $second->outputSnapshot);
        $this->assertSame('5.216116', $first->annuityFactorDisplay());
        $this->assertSame('0.984851', $first->indifferenceTakeDisplay());
        $this->assertSame('4515.278348', (string) $first->takeResult('current')->pvStayMusd->toScale(6, RoundingMode::HalfUp));
    }

    public function test_week11_engine_does_not_create_downstream_state(): void
    {
        (new Week11EconomicEngine)->calculate(Week11ReferencePackage::fromRepository()->inputs());

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
