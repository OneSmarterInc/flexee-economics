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

    public function test_tiny_differences_do_not_swing_the_score(): void
    {
        $base = ['profit_per_barrel' => 37.0, 'roace_pct' => 12.0, 'free_cash_flow' => 1800.0, 'refining_vs_industry' => 0.6,
            'shop_profit_per_station_k' => 41.58, 'debt_to_earnings' => 1.25, 'plant_condition' => 70.0];
        $scores = Scoring::composite(['a' => $base, 'b' => ['shop_profit_per_station_k' => 41.60] + $base]);
        $this->assertLessThan(1.0, abs($scores['a'] - $scores['b']));
        $this->assertSame(['a' => 1, 'b' => 1], Scoring::rank(['a' => 50.0, 'b' => 50.0]));
    }
}
