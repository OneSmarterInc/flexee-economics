<?php

namespace Tests\Feature\Economics;

use App\Domain\Economics\Week10\Week10ConvergenceEconomicEngine;
use App\Domain\Economics\Week10\Week10EconomicResult;
use App\Domain\Economics\Week10\Week10HistoricalDependency;
use App\Domain\Economics\Week10\Week10InheritedState;
use App\Domain\Economics\Week10\Week10ReferencePackage;
use App\Models\CohortFeedbackEffect;
use App\Models\ConsequenceLink;
use App\Models\KpiSnapshot;
use App\Models\RankingSnapshot;
use App\Models\StandingEvent;
use Brick\Math\BigDecimal;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class Week10EconomicEngineTest extends TestCase
{
    use RefreshDatabase;

    public function test_week10_engine_matches_golden_demand_outputs(): void
    {
        $inputs = Week10ReferencePackage::fromRepository()->inputs();
        $result = $this->engine()->calculate($inputs, Week10InheritedState::fromReferenceFixture($inputs, 'reference_disciplined'));
        $golden = $inputs->golden['results'];

        $this->assertSame(Week10EconomicResult::STATUS_CALCULATED, $result->status);
        $this->assertDecimalClose((string) $golden['demand_hit_gasoline'], $result->demandHit('gasoline'));
        $this->assertDecimalClose((string) $golden['demand_hit_diesel'], $result->demandHit('diesel'));
        $this->assertDecimalClose((string) $golden['demand_hit_jet'], $result->demandHit('jet'));
        $this->assertDecimalClose((string) $golden['blended_demand_hit'], $result->blendedDemandHit);
    }

    public function test_week10_engine_matches_golden_refinery_outputs(): void
    {
        $inputs = Week10ReferencePackage::fromRepository()->inputs();
        $result = $this->engine()->calculate($inputs, Week10InheritedState::fromReferenceFixture($inputs, 'reference_disciplined'));
        $golden = $inputs->golden['results'];

        $this->assertDecimalClose((string) $golden['refinery_hit_br'], $result->refineryHit('Baton Rouge'));
        $this->assertDecimalClose((string) $golden['refinery_hit_rot'], $result->refineryHit('Rotterdam'));
        $this->assertDecimalClose((string) $golden['refinery_hit_sing'], $result->refineryHit('Singapore'));
        $this->assertSame($golden['hardest_hit_refinery'], $result->hardestHitRefinery);
    }

    public function test_binding_constraints_match_disciplined_and_constrained_reference_cases(): void
    {
        $inputs = Week10ReferencePackage::fromRepository()->inputs();
        $disciplined = $this->engine()->calculate($inputs, Week10InheritedState::fromReferenceFixture($inputs, 'reference_disciplined'));
        $constrained = $this->engine()->calculate($inputs, Week10InheritedState::fromReferenceFixture($inputs, 'reference_constrained'));
        $golden = $inputs->golden['results'];

        $this->assertSame($golden['disciplined_binding_count'], $disciplined->bindingCount);
        $this->assertFalse($disciplined->bindingStatus('capex'));
        $this->assertFalse($disciplined->bindingStatus('hedge'));
        $this->assertFalse($disciplined->bindingStatus('delacroix_cover'));
        $this->assertFalse($disciplined->bindingStatus('straits_pacific'));
        $this->assertFalse($disciplined->bindingStatus('cash'));

        $this->assertSame($golden['constrained_binding_count'], $constrained->bindingCount);
        $this->assertTrue($constrained->bindingStatus('capex'));
        $this->assertTrue($constrained->bindingStatus('hedge'));
        $this->assertTrue($constrained->bindingStatus('delacroix_cover'));
        $this->assertTrue($constrained->bindingStatus('straits_pacific'));
        $this->assertTrue($constrained->bindingStatus('cash'));
    }

    public function test_historical_dependencies_are_represented_with_source_metadata(): void
    {
        $inputs = Week10ReferencePackage::fromRepository()->inputs();
        $result = $this->engine()->calculate($inputs, Week10InheritedState::fromReferenceFixture($inputs, 'reference_constrained'));
        $dependencies = $result->inputSnapshot['inherited_state']['dependencies'];

        $this->assertSame('fixture:week6', $dependencies['cancellable_capex_musd']['source_week']);
        $this->assertSame('team_prior_state.csv', $dependencies['cancellable_capex_musd']['source_entity']);
        $this->assertSame('reference_constrained', $dependencies['cancellable_capex_musd']['source_id']);
        $this->assertSame('120.0', $dependencies['cancellable_capex_musd']['source_value']);
        $this->assertSame('fixture:week4', $dependencies['br_reported_margin_strong']['source_week']);
        $this->assertSame('true', $dependencies['br_reported_margin_strong']['source_value']);
        $this->assertSame('fixture:standing_history', $dependencies['straits_pacific_standing']['source_week']);
        $this->assertSame('strained', $dependencies['straits_pacific_standing']['source_value']);
        $this->assertSame('fixture:week8', $dependencies['cash_cushion_musd']['source_week']);
        $this->assertSame('85.0', $dependencies['cash_cushion_musd']['source_value']);
    }

    public function test_unresolved_dependency_returns_explicit_status_without_defaulting(): void
    {
        $inputs = Week10ReferencePackage::fromRepository()->inputs();
        $state = new Week10InheritedState(
            cancellableCapexMusd: null,
            crudeHedgeCoverage: BigDecimal::of('0.7'),
            batonRougeReportedMarginStrong: false,
            straitsPacificStanding: 'cooperative',
            cashCushionMusd: BigDecimal::of('240.0'),
            dependencies: [
                'cancellable_capex_musd' => Week10HistoricalDependency::unresolved(
                    key: 'cancellable_capex_musd',
                    sourceWeek: '6',
                    sourceEntity: 'capital_allocation_evaluation',
                    reason: 'Week 6 capital evaluation not available.',
                ),
                'crude_hedge_coverage' => Week10HistoricalDependency::available('crude_hedge_coverage', '5', 'hedge_position', '0.7'),
                'br_reported_margin_strong' => Week10HistoricalDependency::available('br_reported_margin_strong', '4', 'economic_resolution', 'false'),
                'straits_pacific_standing' => Week10HistoricalDependency::available('straits_pacific_standing', 'standing_history', 'standing_state', 'cooperative'),
                'cash_cushion_musd' => Week10HistoricalDependency::available('cash_cushion_musd', '8', 'week8_economic_evaluation', '240.0'),
            ],
        );

        $result = $this->engine()->calculate($inputs, $state);

        $this->assertSame(Week10EconomicResult::STATUS_UNRESOLVED_DEPENDENCY, $result->status);
        $this->assertSame(['cancellable_capex_musd'], $result->unresolvedDependencies);
        $this->assertSame([], $result->bindingConstraints);
        $this->assertSame(0, $result->bindingCount);
        $this->assertDecimalClose((string) $inputs->golden['results']['demand_hit_gasoline'], $result->demandHit('gasoline'));
    }

    public function test_engine_is_deterministic_for_same_inputs(): void
    {
        $inputs = Week10ReferencePackage::fromRepository()->inputs();
        $state = Week10InheritedState::fromReferenceFixture($inputs, 'reference_constrained');

        $first = $this->engine()->calculate($inputs, $state);
        $second = $this->engine()->calculate($inputs, $state);

        $this->assertSame($first->inputSnapshot, $second->inputSnapshot);
        $this->assertSame($first->outputSnapshot, $second->outputSnapshot);
    }

    public function test_worked_example_matches_authoritative_fixture(): void
    {
        $inputs = Week10ReferencePackage::fromRepository()->inputs();
        $workedExample = $this->engine()->workedExample($inputs);

        $this->assertDecimalClose((string) $inputs->golden['worked_example']['br_hit'], $workedExample['br_hit']);
        $this->assertSame($inputs->golden['worked_example']['disciplined_binding_count'], $workedExample['disciplined_binding_count']);
    }

    public function test_decimal_precision_uses_golden_tolerance_without_float_drift(): void
    {
        $inputs = Week10ReferencePackage::fromRepository()->inputs();
        $result = $this->engine()->calculate($inputs, Week10InheritedState::fromReferenceFixture($inputs, 'reference_disciplined'));

        $this->assertSame('-0.010500', (string) $result->demandHit('gasoline')->toScale(6));
        $this->assertSame('-0.019920', (string) $result->refineryHit('Baton Rouge')->toScale(6));
        $this->assertDecimalClose('-0.02622', $result->refineryHit('Singapore'));
    }

    public function test_engine_does_not_mutate_downstream_simulation_state(): void
    {
        $inputs = Week10ReferencePackage::fromRepository()->inputs();

        $this->assertSame(0, KpiSnapshot::query()->count());
        $this->assertSame(0, RankingSnapshot::query()->count());
        $this->assertSame(0, StandingEvent::query()->count());
        $this->assertSame(0, ConsequenceLink::query()->count());
        $this->assertSame(0, CohortFeedbackEffect::query()->count());

        $this->engine()->calculate($inputs, Week10InheritedState::fromReferenceFixture($inputs, 'reference_disciplined'));

        $this->assertSame(0, KpiSnapshot::query()->count());
        $this->assertSame(0, RankingSnapshot::query()->count());
        $this->assertSame(0, StandingEvent::query()->count());
        $this->assertSame(0, ConsequenceLink::query()->count());
        $this->assertSame(0, CohortFeedbackEffect::query()->count());
    }

    private function engine(): Week10ConvergenceEconomicEngine
    {
        return new Week10ConvergenceEconomicEngine;
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
