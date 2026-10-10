<?php

namespace App\Halden\Ai;

use App\Halden\Game\DecisionBook;
use App\Halden\OperatingModel\ModelData;
use App\Halden\OperatingModel\OperatingModel;
use App\Models\Quarter;
use App\Models\TeamQuarter;

/**
 * Turns a team's closed quarter into plain sentences for the faculty drafting tools: what the team set
 * (the operating sheet) and what the model found. Never flag names, always words.
 */
final class Findings
{
    public function __construct(private readonly OperatingModel $model, private readonly ModelData $data, private readonly DecisionBook $book) {}

    /** @return list<string> what the team set this quarter, one line per decision */
    public function sheet(TeamQuarter $tq): array
    {
        $d = $tq->effective_decisions ?? [];
        $q = $tq->quarter;
        $lines = [];
        $prev = $this->previousDecisions($tq);
        $was = function (string $k, string $suffix = '') use ($prev, $d): string {
            if (! isset($prev[$k], $d[$k]) || ! is_numeric($prev[$k]) || ! is_numeric($d[$k])) {
                return '';
            }
            $before = (float) $prev[$k];
            $now = (float) $d[$k];

            return match (true) {
                $now > $before => " (up from {$prev[$k]}$suffix the quarter before)",
                $now < $before => " (down from {$prev[$k]}$suffix the quarter before)",
                default => ' (same as the quarter before)',
            };
        };
        $lines[] = 'Drilling rigs in Texas: '.($d['rigs'] ?? '?').$was('rigs');
        $lines[] = 'Norway fields Halden runs: '.(($d['norway'] ?? 'run') === 'cut' ? 'pump 10% less' : 'keep pumping as planned');
        $lines[] = 'Baton Rouge run rate: '.($d['br_run'] ?? '?').'%'.$was('br_run', '%');
        $lines[] = match ($d['rot_posture'] ?? 'run') {
            'idle' => 'Rotterdam: paused',
            'close' => 'Rotterdam: closed for good',
            default => 'Rotterdam: running at '.($d['rot_run'] ?? '?').'%',
        };
        if ($q->number >= 3) {
            $lines[] = 'Asked Straits Pacific to run Singapore at '.($d['sg_request'] ?? '?').'%';
        }
        if ($q->number >= 2) {
            $names = ['off_urban' => 'Cordell cities', 'off_suburban' => 'Cordell suburbs', 'off_rural' => 'Cordell small towns',
                'off_interstate' => 'Cordell highways', 'off_nl' => 'Netherlands', 'off_be' => 'Belgium', 'off_de' => 'Germany border'];
            $base = $this->data->baseOffsets();
            $parts = [];
            foreach ($names as $k => $label) {
                $now = (float) ($d[$k] ?? 0);
                $was = (float) ($base[substr($k, 4)] ?? 0);
                $parts[] = sprintf('%s %+.1f cents a gallon (the old presidents had %+.1f)', $label, $now, $was);
            }
            $lines[] = 'Station prices against the going rate: '.implode('; ', $parts);
        }
        if ($q->number >= 4) {
            $r = $tq->results ?? [];
            $lines[] = match ($d['tp_method'] ?? 'market') {
                'cost' => sprintf('Baton Rouge pays what Texas crude costs to pump and ship: $%.2f a barrel', (float) ($r['ops.tp'] ?? 0)),
                'other' => sprintf('Baton Rouge pays the team\'s own price for Texas crude: $%.2f a barrel', (float) ($d['tp_value'] ?? 0)),
                default => sprintf('Baton Rouge pays the market price for Texas crude: $%.2f a barrel', (float) ($r['ops.tp'] ?? 0)),
            };
        }

        if ($q->number >= 7) {
            $held = [];
            $matched = [];
            foreach (['off_urban' => 'cities', 'off_suburban' => 'suburbs', 'off_rural' => 'small towns', 'off_interstate' => 'highways'] as $k => $label) {
                $rk = 'resp_'.substr($k, 4);
                if (($d[$rk] ?? 'ignore') === 'match') {
                    $matched[] = $label;
                } else {
                    $held[] = $label;
                }
            }
            $lines[] = 'Pelican\'s 6-cent price cut: matched in '.($matched === [] ? 'no market' : implode(', ', $matched)).'; held the price in '.($held === [] ? 'no market' : implode(', ', $held)).'.';
            $lines[] = 'Pelican\'s announced Gulf Coast unit: '.(($d['capacity_response'] ?? 'hold') === 'match' ? 'Halden is building a unit of its own' : 'Halden is not building');
        }

        if ($q->number >= 8 && isset($d['opec_case'])) {
            $lines[] = 'What Geneva planned for at OPEC+: '.match ((string) $d['opec_case']) {
                'full' => 'the cut holding (30 days of crude bought ahead)',
                'partial' => 'a partial cut (15 days bought ahead)',
                default => 'the cut failing (nothing bought ahead)',
            };
        }

        if ($q->number >= 9) {
            $painted = [];
            foreach ($this->data->rebrand as $key => $m) {
                if (($d["rebrand_$key"] ?? 'keep') === 'rebrand') {
                    $painted[] = $m['label'];
                }
            }
            $lines[] = 'The Cordell name: Halden signs on '.($painted === [] ? 'no region (Cordell kept everywhere)' : implode(', ', $painted)).'.';
        }

        return $lines;
    }

    /**
     * What ran the quarter before: the team's own, or the old presidents' settings before Quarter 1.
     *
     * @return array<string, mixed>
     */
    private function previousDecisions(TeamQuarter $tq): array
    {
        $prevQuarter = $tq->quarter->previous();
        if ($prevQuarter === null) {
            return $this->book->historyDefaults();
        }

        return TeamQuarter::query()->where('team_id', $tq->team_id)->where('quarter_id', $prevQuarter->id)->first()->effective_decisions ?? [];
    }

    /**
     * What the model found, in plain sentences, with the figures that back each one.
     *
     * @param  list<string>  $advisorsConsulted
     * @param  list<string>  $keyAdvisors
     * @param  array<string, string>  $advisorNames
     * @return list<string>
     */
    public function found(TeamQuarter $tq, array $advisorsConsulted, array $keyAdvisors, array $advisorNames): array
    {
        $d = $tq->effective_decisions ?? [];
        $r = $tq->results ?? [];
        $q = $tq->quarter;
        $m = $this->data->quarter($q->company_quarter);
        $out = [];

        $rigs = (int) ($d['rigs'] ?? 0);
        if ($rigs > 0) {
            $netback = (float) $m['wti'] - $this->data->c('permian_wellhead_discount') - $this->data->c('permian_lifting_avg') - $this->data->c('permian_gathering');
            $life = $this->data->c('days_per_quarter') / $this->data->c('permian_decline_qtr');
            $cost = $this->data->c('rig_capex_per_qtr');
            $value = fn (int $k): float => ($this->model->permianAdds($k) - $this->model->permianAdds($k - 1)) * $life * $netback / 1e6;
            $out[] = sprintf(
                'Rigs: the team ran %d. At this quarter\'s oil price, the last of those rigs adds oil worth about $%.0fM over the life of its wells, against $%.0fM to run it for the quarter. One more rig would have added about $%.0fM.',
                $rigs, $value($rigs), $cost, $value($rigs + 1),
            );
        }
        if ((float) ($d['br_run'] ?? 0) > $this->data->c('br_wear_threshold')) {
            $out[] = sprintf('Baton Rouge ran above %.0f%%, so the plant wore faster. Plant condition is now %.1f out of 100.', $this->data->c('br_wear_threshold'), (float) ($r['kpi.plant_condition'] ?? 0));
        }
        $rotEarns = (float) ($r['ops.nwe'] ?? $m['nwe']) + $this->data->c('rot_complexity');
        $rotVar = $this->data->c('rot_variable_opex');
        $out[] = match ((string) ($r['ops.rot_status'] ?? 'running')) {
            'idle' => sprintf('Rotterdam was paused. It would have earned about $%.2f a barrel against $%.2f of per-barrel costs. Its result this quarter was %s.', $rotEarns, $rotVar, $this->money((float) ($r['line.rotterdam'] ?? 0))),
            'closed' => sprintf('Rotterdam is closed. Its result this quarter, including any one-time cost, was %s.', $this->money((float) ($r['line.rotterdam'] ?? 0) + (float) ($r['line.rotterdam_one_time'] ?? 0))),
            default => sprintf('Rotterdam ran. It earned about $%.2f a barrel against $%.2f of per-barrel costs, so each barrel %s its running costs. Its result after fixed costs was %s.', $rotEarns, $rotVar, $rotEarns >= $rotVar ? 'covered' : 'did not cover', $this->money((float) ($r['line.rotterdam'] ?? 0))),
        };
        if (abs((float) ($r['line.norway_cutback_effect'] ?? 0)) > 0.05) {
            $out[] = sprintf('Pumping 10%% less in Norway changed profit by %s before tax; the Norwegian state shares 78%% of that either way.', $this->money((float) $r['line.norway_cutback_effect']));
        }
        if ($q->number >= 4) {
            $gap = (float) ($r['line.geneva_gap_trading'] ?? 0);
            $out[] = $gap > 0.05
                ? sprintf('Geneva made %s by trading on the gap between Halden\'s own crude price and the market. That money came out of the refinery; Halden\'s total didn\'t change because of it.', $this->money($gap))
                : 'Geneva made nothing from the internal crude price this quarter, because the price was too close to cost or to market for the gap to be worth trading.';
        }
        if ($q->number >= 7 && (((float) ($r['ops.rival_match_cost'] ?? 0)) > 0 || ((float) ($r['ops.rival_ignore_cost'] ?? 0)) > 0)) {
            $out[] = sprintf('Pelican\'s price cut: matching gave up %s of fuel margin this quarter; in the markets where the team held its price, drivers drifting to Pelican cost about %s. Matching costs six cents on every gallon; holding costs a fraction of a percent of volume.',
                $this->money((float) ($r['ops.rival_match_cost'] ?? 0)), $this->money((float) ($r['ops.rival_ignore_cost'] ?? 0)));
        }
        if (abs((float) ($r['ops.wti_shock'] ?? 0)) > 0.05) {
            $out[] = sprintf('OPEC+: oil moved $%s to $%s. Crude bought ahead made %s, less %s of interest. Baton Rouge\'s margin ended at $%s a barrel.',
                number_format((float) $r['ops.wti_shock'], 2), number_format((float) $r['ops.wti'], 2), $this->money((float) ($r['line.crude_bought_ahead'] ?? 0)),
                $this->money(abs((float) ($r['line.inventory_carry'] ?? 0))), number_format((float) ($r['ops.gc'] ?? 0), 2));
        }
        if ($q->number >= 9) {
            $margin = (float) ($r['ops.nonfuel_per_gal'] ?? 0);
            $out[] = sprintf('The shop margin this year is %s cents a gallon (the usual is %s), set by how the class answered Pelican. A rebrand pays back faster or slower in step with it.',
                rtrim(rtrim(number_format($margin * 100, 1), '0'), '.'), rtrim(rtrim(number_format($this->data->c('window3_base_nonfuel') * 100, 1), '0'), '.'));
            if ((float) ($r['ops.rebrand_outlay'] ?? 0) > 0) {
                $out[] = sprintf('The team spent %s repainting stations this quarter. The heartland loses money under the Halden name; the Gulf Coast beyond the core pays back in about 15 years at the usual margin and the Southeast edge in about 5.5.', $this->money((float) $r['ops.rebrand_outlay']));
            }
        }
        if ((float) ($r['history.delacroix_cover'] ?? 0) > 0) {
            $out[] = sprintf('Recession: the team asked for Baton Rouge at %s%% and got %s%%, because its Q4 2027 crude price gave Marcus Delacroix cover to resist the cut.', (string) ($d['br_run'] ?? '?'), number_format((float) ($r['ops.br_run'] ?? 0), 1));
        }
        if ((float) ($r['history.sg_cut_by_partner'] ?? 0) > 0) {
            $out[] = sprintf('Recession: Straits Pacific ran Singapore at 80%% against a request of %s%%, because the team asked for more than they allow in two or more of the last four quarters.', (string) ($d['sg_request'] ?? '?'));
        }
        if (abs((float) ($r['line.capacity_game'] ?? 0)) > 0.05) {
            $out[] = sprintf('Pelican built its Gulf Coast unit. Halden\'s answer (%s) is costing %s a quarter under Refineries.',
                ($d['capacity_response'] ?? 'hold') === 'match' ? 'building too' : 'not building', $this->money(abs((float) $r['line.capacity_game'])));
        }
        if ($q->number >= 2) {
            $out[] = sprintf('Cordell fuel earned %s and Cordell shops %s this quarter.', $this->money((float) ($r['line.cordell_fuel'] ?? 0)), $this->money((float) ($r['line.cordell_shop'] ?? 0)));
        }
        $missed = array_values(array_diff($keyAdvisors, $advisorsConsulted));
        $out[] = $advisorsConsulted === []
            ? 'The team did not ask any advisor anything this quarter.'
            : 'Advisors the team asked: '.implode(', ', array_map(fn ($k) => $advisorNames[$k] ?? $k, $advisorsConsulted)).'.'
                .($missed === [] ? '' : ' They did not ask '.implode(' or ', array_map(fn ($k) => $advisorNames[$k] ?? $k, $missed)).', whose area this quarter\'s question turns on.');

        return $out;
    }

    private function money(float $m): string
    {
        return ($m < 0 ? '-' : '').'$'.number_format(abs($m), abs($m) < 10 ? 1 : 0).'M';
    }
}
