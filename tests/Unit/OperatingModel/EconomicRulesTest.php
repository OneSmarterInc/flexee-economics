<?php

namespace Tests\Unit\OperatingModel;

use App\Halden\OperatingModel\CompanyState;
use App\Halden\OperatingModel\Decisions;
use App\Halden\OperatingModel\ModelData;
use App\Halden\OperatingModel\OperatingModel;
use App\Halden\OperatingModel\QuarterResult;
use App\Halden\OperatingModel\Scoring;
use Tests\TestCase;

/**
 * The economic lessons the model must always teach. These mirror packages/operating-model/model/validate.py.
 */
class EconomicRulesTest extends TestCase
{
    private ModelData $data;

    private OperatingModel $model;

    private CompanyState $start;

    protected function setUp(): void
    {
        parent::setUp();
        $this->data = new ModelData;
        $this->model = new OperatingModel($this->data);
        [$this->start] = $this->model->runHistory();
    }

    private function play(array $args, string $quarter = '2027Q4', ?CompanyState $state = null): QuarterResult
    {
        $args['offsets'] ??= $this->data->baseOffsets();

        return $this->model->step(clone ($state ?? $this->start), new Decisions(...$args), $this->data->quarter($quarter));
    }

    public function test_the_internal_crude_price_never_changes_halden_total(): void
    {
        $cost = $this->play(['tpMethod' => 'cost']);
        $market = $this->play(['tpMethod' => 'market']);
        $between = $this->play(['tpMethod' => 'other', 'tpValue' => 46.20]);

        $this->assertEqualsWithDelta($market->money['ebitda'], $cost->money['ebitda'], 1e-9);
        $this->assertEqualsWithDelta($market->money['ebitda'], $between->money['ebitda'], 1e-9);
        $this->assertEqualsWithDelta((73.70 - 18.70) * 120000 * 91.25 / 1e6,
            $cost->lines['internal_crude_shift'] - $market->lines['internal_crude_shift'], 1e-6);
    }

    public function test_geneva_follows_the_week_4_rule(): void
    {
        [$market, $cost] = $this->model->transferPrices(74.0);
        $this->assertEqualsWithDelta(73.70, $market, 1e-9);
        $this->assertEqualsWithDelta(18.70, $cost, 1e-9);
        foreach ([18.70, 20.0, 67.0, 73.70, 80.0] as $tp) {
            $this->assertSame(0.0, $this->model->genevaCapturePerBbl($tp, $market, $cost), "no capture at $tp");
        }
        $this->assertEqualsWithDelta(0.35 * 27.5, $this->model->genevaCapturePerBbl(46.20, $market, $cost), 1e-9);
    }

    public function test_rotterdam_runs_above_the_shutdown_point_and_pauses_below_it(): void
    {
        $this->assertGreaterThan($this->play(['rotPosture' => 'idle'])->lines['rotterdam'], $this->play(['rotPosture' => 'run'])->lines['rotterdam']);

        $low = $this->data->quarter('2027Q4');
        $low['nwe'] = 2.0;
        $run = $this->model->step(clone $this->start, new Decisions(offsets: $this->data->baseOffsets()), $low);
        $idle = $this->model->step(clone $this->start, new Decisions(rotPosture: 'idle', offsets: $this->data->baseOffsets()), $low);
        $this->assertGreaterThan($run->lines['rotterdam'], $idle->lines['rotterdam']);
    }

    public function test_restarting_costs_85m_once_and_closing_is_permanent(): void
    {
        $paused = $this->play(['rotPosture' => 'idle'], '2027Q1');
        $restarted = $this->play(['rotPosture' => 'run'], '2027Q2', $paused->state);
        $this->assertEqualsWithDelta(-85.0, $restarted->lines['rotterdam_one_time'], 1e-9);

        $closed = $this->play(['rotPosture' => 'close'], '2027Q1');
        $after = $this->play(['rotPosture' => 'run'], '2027Q2', $closed->state);
        $this->assertSame('closed', $after->ops['rot_status']);
        $this->assertSame(0.0, (float) $after->ops['rot_throughput']);
    }

    public function test_pumping_less_in_norway_always_costs_money(): void
    {
        $this->assertLessThan($this->play(['norway' => 'run'])->money['ebitda'], $this->play(['norway' => 'cut'])->money['ebitda']);
    }

    public function test_more_rigs_always_add_oil_but_the_twelfth_does_not_pay(): void
    {
        for ($r = 1; $r < 40; $r++) {
            $this->assertGreaterThan($this->model->permianAdds($r), $this->model->permianAdds($r + 1));
        }
        $wti = $this->data->quarter('2027Q1')['wti'];
        $value = fn (int $r): float => ($this->model->permianAdds($r + 1) - $this->model->permianAdds($r)) * 91.25 * ($wti - 15.5) / 0.08 / 1e6;
        $this->assertGreaterThan(40, $value(10));
        $this->assertLessThan(40, $value(11));

        $now = $this->play(['rigs' => 30], '2027Q1');
        $base = $this->play(['rigs' => 14], '2027Q1');
        $this->assertEqualsWithDelta($base->ops['permian_prod'], $now->ops['permian_prod'], 1e-9);
        $this->assertGreaterThan($base->state->permianProd, $now->state->permianProd);
    }

    public function test_straits_pacific_holds_singapore_between_80_and_95(): void
    {
        $this->assertSame(95.0, (float) $this->play(['sgRequest' => 100])->ops['sg_accepted']);
        $this->assertSame(80.0, (float) $this->play(['sgRequest' => 60])->ops['sg_accepted']);
    }

    public function test_kessana_valuation_matches_the_week_11_package_and_sunk_capital_never_enters(): void
    {
        $this->assertEqualsWithDelta(67.0, $this->model->kessanaProfitOil(), 1e-9);
        $this->assertEqualsWithDelta(5.216116, $this->model->kessanaAnnuityFactor(), 1e-6);
        $pv = array_map(fn (float $t) => $this->model->kessanaPvStay($t), $this->data->kessanaTakes);
        $this->assertEqualsWithDelta(4515.278348, $pv['current'], 1e-5);
        $this->assertEqualsWithDelta(3802.339662, $pv['mid'], 1e-5);
        $this->assertEqualsWithDelta(3089.400975, $pv['demanded'], 1e-5);
        $this->assertEqualsWithDelta(2376.462288, $pv['harsh'], 1e-5);
        $this->assertGreaterThan($this->data->c('kessana_exit_value'), min($pv), 'staying beats leaving at every take on the grid');
        $this->assertEqualsWithDelta(0.984851, $this->model->kessanaIndifferenceTake(), 1e-6);
        $this->assertTrue(min($this->data->kessanaComparables) <= 0.74 && max($this->data->kessanaComparables) >= 0.74);
    }

    public function test_the_kessana_position_acts_once_and_the_settled_take_carries(): void
    {
        $q = '2029Q3';
        $none = $this->play(['kessanaPosition' => 'none'], $q);
        $accept = $this->play(['kessanaPosition' => 'accept'], $q);
        $counter = $this->play(['kessanaPosition' => 'counter'], $q);
        $threaten = $this->play(['kessanaPosition' => 'threaten'], $q);
        $exit = $this->play(['kessanaPosition' => 'exit'], $q);
        $spotProfitOil = $this->data->quarter($q)['wti'] + $this->data->c('brent_spread') - $this->data->c('kessana_discount_to_brent') - $this->data->c('kessana_lifting');
        $volume = $this->data->c('kessana_volume') * 91.25 / 1e6;
        $this->assertEqualsWithDelta(-0.12 * $spotProfitOil * $volume, $accept->lines['kessana_take_change'], 1e-6);
        $this->assertEqualsWithDelta(-0.06 * $spotProfitOil * $volume, $counter->lines['kessana_take_change'], 1e-6);
        $this->assertEqualsWithDelta(-0.18 * $spotProfitOil * $volume, $threaten->lines['kessana_take_change'], 1e-6);
        $this->assertSame(0.0, $none->lines['kessana_take_change']);
        $this->assertSame(0.0, $exit->lines['kessana']);
        $this->assertEqualsWithDelta($none->money['ebitda'] - $none->lines['kessana'], $exit->money['ebitda'], 1e-6, 'the write-down never touches EBITDA');
        $this->assertEqualsWithDelta(2300.0, $none->money['capital_employed_end'] - $exit->money['capital_employed_end'], 1e-6);
        $this->assertEqualsWithDelta(180.0, $exit->ops['kessana_exit_proceeds'], 1e-9);
        $this->assertTrue($exit->state->kessanaExited);
        // The settled take carries into later quarters; the position does nothing outside the quarter the government asks.
        $later = $this->play(['kessanaPosition' => 'none'], '2029Q2', $counter->state);
        $this->assertEqualsWithDelta(0.68, $later->ops['kessana_take'], 1e-12);
        $this->assertEqualsWithDelta(0.62, $this->play(['kessanaPosition' => 'accept'], '2029Q2')->ops['kessana_take'], 1e-12);
        $this->assertSame(0.0, $this->play(['kessanaPosition' => 'exit'], '2029Q2', $exit->state)->lines['kessana']);
    }

    public function test_the_portfolio_matches_the_week_12_package_and_every_project_swings_sign(): void
    {
        $pkg = ['helix_rotterdam' => [-180, 308], 'permian_expansion' => [-126, 220], 'biofuel_conversion' => [-40, 136], 'offshore_wind' => [-90, 120], 'euro_retail_divest' => [-60, 102]];
        foreach ($pkg as $key => [$lo, $hi]) {
            $values = [];
            foreach ($this->data->scenarios['carbon'] as $c) {
                foreach ($this->data->scenarios['demand'] as $d) {
                    $values[] = $this->model->portfolioNpv($key, $c['value'], $d['value']);
                }
            }
            $this->assertEqualsWithDelta($lo, min($values), 1e-9, $key);
            $this->assertEqualsWithDelta($hi, max($values), 1e-9, $key);
            $this->assertTrue(min($values) < 0 && max($values) > 0, "$key swings sign");
        }
        $this->assertEqualsWithDelta(1200.0, $this->model->portfolioDiscretionary(), 1e-9);
        $feasible = $this->model->portfolioFeasibleSets();
        $this->assertCount(17, $feasible);
        $this->assertCount(3, array_filter($feasible, fn (array $f) => in_array('helix_rotterdam', $f, true)));
        $this->assertSame(['envelope'], $this->model->portfolioCheck(['helix_rotterdam', 'offshore_wind']));
        $this->assertSame([], $this->model->portfolioCheck(['helix_rotterdam', 'offshore_wind', 'euro_retail_divest']));
        $this->assertContains('bucket:adjacent', $this->model->portfolioCheck(['helix_rotterdam', 'biofuel_conversion', 'euro_retail_divest']));
        $this->assertSame(['rotterdam_closed'], $this->model->portfolioCheck(['biofuel_conversion'], true));
    }

    public function test_the_portfolio_spends_over_five_years_and_the_sale_ends_the_european_line(): void
    {
        $go = $this->play(['portfolio' => ['helix_rotterdam' => 'go', 'euro_retail_divest' => 'go']], '2029Q4');
        $hold = $this->play([], '2029Q4');
        $this->assertEqualsWithDelta(550.0, $go->ops['divest_proceeds'], 1e-9);
        $this->assertEqualsWithDelta($hold->money['ebitda'], $go->money['ebitda'], 1e-9, 'the sale is not in EBITDA');
        $this->assertEqualsWithDelta(550.0, $hold->money['net_debt_end'] - $go->money['net_debt_end'], 1e-9);
        $this->assertSame(0.0, $go->ops['portfolio_capex'], 'nothing goes out in the go-ahead quarter');
        $nextGo = $this->play([], '2029Q3', $go->state);
        $nextHold = $this->play([], '2029Q3', $hold->state);
        $this->assertSame(0.0, $nextGo->lines['europe_stations']);
        $this->assertGreaterThan(50, $nextHold->lines['europe_stations']);
        $this->assertEqualsWithDelta(60.0, $nextGo->ops['portfolio_capex'], 1e-9, '$1,200M over twenty quarters');
        $this->assertEqualsWithDelta($nextGo->money['fcf'] + 60.0, $nextGo->kpi['free_cash_flow'], 1e-9, 'added back in the score');
        $this->assertSame(0.0, $this->play(['portfolio' => ['helix_rotterdam' => 'go']], '2029Q3')->ops['portfolio_capex'], 'outside Q4 2029 the page does nothing');
    }

    public function test_the_norwegian_tax_shield_makes_accepting_the_union_cheapest(): void
    {
        $this->assertEqualsWithDelta(7.392, $this->model->norwayConcessionAfterTax(33.6), 1e-9, 'the Week 13 package');
        $this->assertEqualsWithDelta(507.6, $this->model->permianMarginalRevenueProductK(), 1e-9);
        $q = '2030Q1';
        $none = $this->play([], $q);
        $accept = $this->play(['norwayWage' => 'accept'], $q);
        $half = $this->play(['norwayWage' => 'half'], $q);
        $refuse = $this->play(['norwayWage' => 'refuse'], $q);
        $this->assertEqualsWithDelta(-8.4, $accept->lines['norway_wages'], 1e-9);
        $this->assertEqualsWithDelta(8.4 * 0.22, ($none->money['ebitda'] - $none->money['tax']) - ($accept->money['ebitda'] - $accept->money['tax']), 1e-9, 'after tax the raise costs 22% of face');
        $this->assertSame(2.0, $refuse->ops['norway_stoppage_weeks']);
        $this->assertSame(1.0, $half->ops['norway_stoppage_weeks']);
        $this->assertEqualsWithDelta(0.08, $refuse->ops['norway_wage_uplift'], 1e-12, 'arbitration gives the union its rate');
        $afterTax = fn ($r) => $r->money['ebitda'] - $r->money['tax'];
        $this->assertTrue($afterTax($refuse) < $afterTax($half) && $afterTax($half) < $afterTax($accept));
        $later = $this->play([], '2029Q4', $accept->state);
        $this->assertEqualsWithDelta(-8.4, $later->lines['norway_wages'], 1e-9, 'the raise stays');
        $this->assertSame(0.0, $this->play(['norwayWage' => 'refuse'], '2029Q4')->lines['norway_stoppage'], 'outside Q1 2030 nothing happens');
    }

    public function test_the_turnaround_costs_81m_now_or_60m_later_with_a_breakdown_risk_and_three_points_of_condition(): void
    {
        $this->assertEqualsWithDelta(81.0, $this->model->turnaroundPeakCost(), 1e-9);
        $this->assertEqualsWithDelta(78.0, $this->model->turnaroundDelayExpectedCost(), 1e-9);
        $now = $this->play(['turnaround' => 'now'], '2030Q1');
        $wait = $this->play(['turnaround' => 'wait'], '2030Q1');
        $this->assertEqualsWithDelta(-81.0, $now->lines['turnaround'], 1e-9);
        $this->assertSame(0.0, $wait->lines['turnaround']);
        $this->assertEqualsWithDelta(3.0, $now->kpi['plant_condition'] - $wait->kpi['plant_condition'], 1e-9);
        $this->assertTrue($wait->state->turnaroundPending);
        $later = $this->play([], '2029Q4', $wait->state);
        $this->assertEqualsWithDelta(-60.0, $later->lines['turnaround'], 1e-9);
        $this->assertSame(0.0, $later->lines['turnaround_outage']);
        $this->assertFalse($later->state->turnaroundPending);
        $market = $this->data->quarter('2029Q4') + ['outage' => true];
        $broken = $this->model->step(clone $wait->state, new Decisions(offsets: $this->data->baseOffsets()), $market);
        $this->assertEqualsWithDelta(-150.0, $broken->lines['turnaround_outage'], 1e-9);
    }

    public function test_tiny_differences_do_not_swing_the_score(): void
    {
        $base = ['profit_per_barrel' => 37.0, 'roace_pct' => 12.0, 'free_cash_flow' => 1800.0, 'refining_vs_industry' => 0.6,
            'shop_profit_per_station_k' => 41.58, 'debt_to_earnings' => 1.25, 'plant_condition' => 70.0];
        $scores = Scoring::composite(['a' => $base, 'b' => ['shop_profit_per_station_k' => 41.60] + $base]);
        $this->assertLessThan(1.0, abs($scores['a'] - $scores['b']));
        $this->assertSame(['a' => 1, 'b' => 1], Scoring::rank(['a' => 50.0, 'b' => 50.0]));
    }
}
