<?php

namespace App\Halden\OperatingModel;

/**
 * The quarterly operating model. A line-for-line port of packages/operating-model/model/halden_model.py.
 * Any change here must be made in the Python reference first and the fixtures rebuilt;
 * tests/Unit/OperatingModel/GoldenQuartersTest keeps the two in step.
 *
 * Money is USD millions per quarter; volumes are barrels (or boe) per day.
 */
final class OperatingModel
{
    private float $days;

    public function __construct(public readonly ModelData $data)
    {
        $this->days = $data->c('days_per_quarter');
    }

    public function rigProductivity(int $k): float
    {
        $extra = max(0.0, $k - $this->data->c('permian_productivity_free_rigs'));

        return max($this->data->c('permian_productivity_floor'), 1.0 - $this->data->c('permian_productivity_slope') * $extra);
    }

    /** New production (bbl/day) from one quarter of drilling with this many rigs. */
    public function permianAdds(int $rigs): float
    {
        $sum = 0.0;
        for ($k = 1; $k <= $rigs; $k++) {
            $sum += $this->rigProductivity($k);
        }

        return $this->data->c('permian_adds_per_rig') * $sum;
    }

    public function openingState(): CompanyState
    {
        $rigs = 14;

        return new CompanyState(
            permianProd: $this->permianAdds($rigs) / $this->data->c('permian_decline_qtr'),
            prevRigs: $rigs,
            rotStatus: 'running',
            capitalEmployed: $this->data->c('opening_capital_employed'),
            netDebt: $this->data->c('opening_net_debt'),
            assetHealth: $this->data->c('opening_asset_health'),
        );
    }

    /** @return array{0: float, 1: float} [market price, cost price] for Texas crude delivered to Baton Rouge */
    public function transferPrices(float $wti): array
    {
        $c = fn (string $k): float => $this->data->c($k);

        return [
            $wti - $c('permian_wellhead_discount') + $c('transport_permian_br'),
            $c('delivered_marginal_cost') + $c('sr_capital_charge'),
        ];
    }

    public function genevaCapturePerBbl(float $tp, float $market, float $cost): float
    {
        $band = $this->data->c('geneva_band');
        $near = fn (float $v, float $a): bool => $a * (1 - $band) <= $v && $v <= $a * (1 + $band);
        if ($near($tp, $cost) || $near($tp, $market) || $tp >= $market) {
            return 0.0;
        }

        return $this->data->c('geneva_capture_rate') * ($market - $tp);
    }

    /** Window 1: the class's average European run rate in Q3 2027 sets the NWE margin in Q1 2028. */
    public function window1Nwe(float $avgUtil): float
    {
        $c = fn (string $k): float => $this->data->c($k);

        return max($c('window1_floor'), $c('window1_base_crack') + $c('window1_slope') * ($c('window1_base_util') - $avgUtil));
    }

    /** Share of Rotterdam's capacity a team ran (0 when paused or closed). */
    public function europeanUtil(Decisions $dec): float
    {
        return $dec->rotPosture === 'run' ? $dec->rotRun / 100.0 : 0.0;
    }

    /** 1 = crude price at cost, 0.5 = at market, 0 = somewhere a trader can exploit. */
    public function crudePriceDiscipline(Decisions $dec, float $wti): float
    {
        [$market, $cost] = $this->transferPrices($wti);
        if ($dec->tpMethod === 'cost') {
            return 1.0;
        }
        if ($dec->tpMethod === 'market') {
            return 0.5;
        }
        $tp = (float) $dec->tpValue;
        $band = $this->data->c('geneva_band');
        if (abs($tp - $cost) <= $band * $cost) {
            return 1.0;
        }
        if (abs($tp - $market) <= $band * $market) {
            return 0.5;
        }

        return 0.0;
    }

    /** @return array{behaviour: string, rate: float, envelope: float} Q2 2028 capital terms from Q4 2027 crude-price discipline */
    public function capitalTerms(float $avgDiscipline): array
    {
        $b = $avgDiscipline > 2 / 3 + 1e-9 ? 'disciplined' : ($avgDiscipline < 1 / 3 - 1e-9 ? 'lax' : 'base');
        foreach ($this->data->cohortCapital as $row) {
            if ($row['behaviour'] === $b) {
                return $row;
            }
        }
        throw new \RuntimeException("No capital terms for [$b].");
    }

    /** Window 3: the class's Q3 2028 price aggression sets the Cordell shop margin per gallon in Q1 2029, within 15% of base. */
    public function window3Nonfuel(float $avgAggression): float
    {
        $c = fn (string $k): float => $this->data->c($k);
        $base = $c('window3_base_nonfuel');
        $v = $base - $c('window3_slope') * ($avgAggression - $c('window3_pivot'));

        return min($base * (1 + $c('window3_bound')), max($base * (1 - $c('window3_bound')), $v));
    }

    /** Share of the Cordell markets where a team matched the rival's cut (0 = held every price, 1 = matched everywhere). */
    public function priceAggression(Decisions $dec): float
    {
        $matched = 0;
        foreach ($this->data->cordell as $c) {
            if (($dec->responses[$c['key']] ?? 'ignore') === 'match') {
                $matched++;
            }
        }

        return $matched / count($this->data->cordell);
    }

    /** Quarterly payoff of Halden's answer to the rival's expansion once the rival has shown its hand. */
    public function capacityPayoff(string $action, bool $rivalBuilds): float
    {
        if (! $rivalBuilds) {
            return 0.0;
        }

        return $this->data->capacityGame["$action|builds"] / 4;
    }

    /**
     * Window 2: the share of the class that went ahead with the Baton Rouge upgrade in Q2 2028 moves the
     * Q4 2028 Gulf Coast margin. Overbuilding costs twice what restraint earns.
     */
    public function window2Shift(float $brShare): float
    {
        $c = fn (string $k): float => $this->data->c($k);
        $pivot = $c('window2_pivot');
        if ($brShare > $pivot) {
            return -$c('window2_slope_above') * ($brShare - $pivot);
        }

        return $c('window2_slope_below') * ($pivot - $brShare);
    }

    /**
     * The Q4 2028 prices once OPEC+ has decided: WTI moves by the outcome, the Gulf Coast margin compresses
     * 35 cents a dollar and carries the Window 2 shift.
     *
     * @param  array<string, mixed>  $mkt
     * @return array<string, mixed>
     */
    public function opecMarket(array $mkt, string $outcome, float $brShare = 0.5): array
    {
        $d = $this->data->opec[$outcome]['dwti'];
        $m = $mkt;
        $m['wti'] = $mkt['wti'] + $d;
        $m['gc'] = $mkt['gc'] + $this->data->c('crack_per_wti') * $d + $this->window2Shift($brShare);
        $m['wti_shock'] = $d;
        $m['opec_outcome'] = $outcome;

        return $m;
    }

    public function expectedWti(float $wtiPre): float
    {
        $sum = 0.0;
        foreach ($this->data->opec as $s) {
            $sum += $s['p'] * $s['dwti'];
        }

        return $wtiPre + $sum;
    }

    public function inventoryDays(string $case): float
    {
        return $this->data->c("opec_inventory_days_$case");
    }

    /** @param  array<string, mixed>  $mkt  one row of the market path */
    public function step(CompanyState $state, Decisions $dec, array $mkt): QuarterResult
    {
        $c = fn (string $k): float => $this->data->c($k);
        $D = $this->days;
        $lines = [];
        $notes = [];
        $wti = $mkt['wti'];
        $shock = (float) ($mkt['wti_shock'] ?? 0.0);   // how far an OPEC+ outcome moved WTI this quarter
        $brent = $wti + $c('brent_spread');
        $fx = (bool) ($mkt['fx_live'] ?? false);
        $eurF = $fx ? $mkt['eurusd'] / $c('fx_ref_eurusd') : 1.0;
        $nokF = $fx ? $c('fx_ref_usdnok') / $mkt['usdnok'] : 1.0;
        $sgdF = $fx ? $c('fx_ref_usdsgd') / $mkt['usdsgd'] : 1.0;
        $norLifting = $c('norway_lifting') * $nokF;

        // Oil fields
        $prod = $state->permianProd;
        $internal = min($c('internal_volume_to_br'), $prod);
        [$marketTp, $costTp] = $this->transferPrices($wti);
        $tp = match ($dec->tpMethod) {
            'market' => $marketTp,
            'cost' => $costTp,
            default => (float) $dec->tpValue,
        };
        $wellhead = $wti - $c('permian_wellhead_discount');
        $permianRev = ($internal * $tp + ($prod - $internal) * $wellhead) * $D / 1e6;
        $permianCost = ($prod * ($c('permian_lifting_avg') + $c('permian_gathering'))
            + $internal * $c('transport_permian_br')) * $D / 1e6;
        $lines['permian'] = $permianRev - $permianCost;

        $cut = $dec->norway === 'cut' ? $c('norway_cut_share') : 0.0;
        $norMarginFull = $brent - $c('norway_discount_to_brent') - $norLifting - $c('norway_transport');
        $norVol = $c('norway_op_volume');
        $norSavedPerBbl = $norLifting * $c('norway_lifting_variable_share') + $c('norway_transport');
        $lines['norway_operated'] = ($norVol * $norMarginFull
            - $norVol * $cut * ($brent - $c('norway_discount_to_brent') - $norSavedPerBbl)) * $D / 1e6;
        $lines['norway_cutback_effect'] = -$norVol * $cut * ($brent - $c('norway_discount_to_brent') - $norSavedPerBbl) * $D / 1e6;
        $lines['norway_partner_run'] = $c('norway_nonop_volume') * $norMarginFull * $D / 1e6;
        $kesNet = ($brent - $c('kessana_discount_to_brent') - $c('kessana_lifting')) * $c('kessana_company_share_profit_oil');
        $lines['kessana'] = $c('kessana_volume') * $kesNet * $D / 1e6;
        $lines['gas_other'] = $c('gas_other_ebitda');
        $upstreamBase = $lines['permian'] + $lines['norway_operated'] + $lines['norway_partner_run'] + $lines['kessana'] + $lines['gas_other'];

        // Refineries
        $brTpBbl = $c('br_capacity') * $dec->brRun / 100.0;
        $brCrackMargin = $brTpBbl * ($mkt['gc'] + $c('br_complexity') - $c('br_variable_opex')) * $D / 1e6;
        $brFixed = $c('br_capacity') * $c('br_fixed_opex_per_bbl_capacity') * $D / 1e6;
        $internalShift = ($marketTp - $tp) * $internal * $D / 1e6;
        $gBbl = $this->genevaCapturePerBbl($tp, $marketTp, $costTp);
        $geneva = $gBbl > 0 ? $gBbl * $c('geneva_max_volume') * $D / 1e6 : 0.0;
        $lines['internal_crude_shift'] = $internalShift;
        $lines['geneva_gap_trading'] = $geneva;
        $lines['baton_rouge'] = $brCrackMargin - $brFixed + $internalShift - $geneva;

        $rotFixed = $c('rot_capacity') * $c('rot_fixed_opex_per_bbl_capacity') * $D / 1e6;
        $oneTime = 0.0;
        $rotStatus = $state->rotStatus;
        if ($rotStatus === 'closed' || $dec->rotPosture === 'close') {
            if ($rotStatus !== 'closed') {
                $oneTime -= $c('rot_closure_cost');
                $notes['rot_event'] = 'closed';
            }
            $rotStatus = 'closed';
            $rotTp = 0.0;
            $rot = -$c('rot_closed_cost');
        } elseif ($dec->rotPosture === 'idle') {
            $rotStatus = 'idle';
            $rotTp = 0.0;
            $rot = -$rotFixed - $c('rot_idle_care_cost');
        } else {
            if ($state->rotStatus === 'idle') {
                $oneTime -= $c('rot_restart_cost');
                $notes['rot_event'] = 'restarted';
            }
            $rotStatus = 'running';
            $rotTp = $c('rot_capacity') * $dec->rotRun / 100.0;
            $rot = $rotTp * ($mkt['nwe'] + $c('rot_complexity') - $c('rot_variable_opex')) * $D / 1e6 - $rotFixed;
        }
        $lines['rotterdam'] = $rot * $eurF;
        $lines['rotterdam_one_time'] = $oneTime;

        $sgRun = min(max($dec->sgRequest, $c('sg_accept_min')), $c('sg_accept_max'));
        $notes['sg_accepted'] = $sgRun;
        $sgTp = $c('sg_capacity') * $c('sg_halden_share') * $sgRun / 100.0;
        $lines['singapore'] = $sgTp * ($mkt['sg'] + $c('sg_complexity') - $c('sg_opex')) * $D / 1e6 * $sgdF;

        // Projects committed earlier pay a quarter of each year's cash flow, cut to what such projects deliver.
        $projAge = [];
        $projLines = ['refineries' => 0.0, 'oil_fields' => 0.0];
        foreach ($state->projects as $key => $age) {
            $p = $this->data->projects[$key];
            $age++;
            $projAge[$key] = $age;
            $year = intdiv($age - 1, 4) + 1;
            if ($year > count($p['cf']) || ($key === 'rot_upgrade' && $rotStatus === 'closed')) {
                continue;
            }
            $projLines[$p['segment']] += $p['cf'][$year - 1] * $p['haircut'] / 4;
        }
        $commitOutlay = 0.0;
        foreach ($dec->projects as $key => $choice) {
            if ($choice === 'commit' && ! array_key_exists($key, $state->projects)) {
                $projAge[$key] = 0;
                $commitOutlay += $this->data->projects[$key]['outlay'];
            }
        }
        $lines['projects_refining'] = $projLines['refineries'];
        $lines['projects_upstream'] = $projLines['oil_fields'];
        // The rival's Gulf Coast expansion: once it is built (from Q4 2028), Halden's answer sets a yearly payoff.
        $lines['capacity_game'] = $this->capacityPayoff($dec->capacityResponse, (bool) ($mkt['rival_builds'] ?? false));
        $refining = $lines['baton_rouge'] + $lines['rotterdam'] + $lines['rotterdam_one_time'] + $lines['singapore']
            + $lines['projects_refining'] + $lines['capacity_game'];

        // Geneva. Ahead of an OPEC+ decision it buys crude for Baton Rouge at the pre-decision price according to
        // the case the team plans for: it gains if crude rises, loses if it falls, and the money tied up costs interest.
        $lines['geneva_desk'] = $c('geneva_base_desk');
        $boughtAhead = 0.0;
        $carry = 0.0;
        if ((bool) ($mkt['opec'] ?? false)) {
            $days = $this->inventoryDays($dec->opecCase);
            $wtiPre = $wti - $shock;
            $boughtAhead = $days * $brTpBbl * $shock / 1e6;
            $carry = -$days * $brTpBbl * $wtiPre * $c('opec_inventory_carry_annual') / 4 / 1e6;
        }
        $lines['crude_bought_ahead'] = $boughtAhead;
        $lines['inventory_carry'] = $carry;
        $trading = $lines['geneva_desk'] + $geneva + $boughtAhead + $carry;

        // Gas stations
        $heldUp = $state->heldUp;
        $heldDown = $state->heldDown;
        $offsets = $dec->offsets + $this->data->baseOffsets();
        $volumeFactor = function (array $cl, float $offset) use (&$heldUp, &$heldDown, $c): array {
            $delta = ($offset - $cl['base']) / 100.0;
            $street = $cl['pt'] * $delta;
            $k = $cl['key'];
            if ($delta > 1e-12) {
                $n = $heldUp[$k] ?? 0;
                $e = $cl['e'] * (1 + $c('longrun_elasticity_step') * min($n, 4));
                $heldUp[$k] = $n + 1;
                $heldDown[$k] = 0;
            } elseif ($delta < -1e-12) {
                $n = $heldDown[$k] ?? 0;
                $e = $cl['e'] * max(0.0, 1 - $c('longrun_elasticity_step') * $n);
                $heldDown[$k] = $n + 1;
                $heldUp[$k] = 0;
            } else {
                $e = $cl['e'];
                $heldUp[$k] = 0;
                $heldDown[$k] = 0;
            }

            return [1 + $e * $street / $c('pump_base'), $delta];
        };

        // A rival's street cut (from Q3 2028): where the team holds its price, drivers drift to the rival;
        // where it matches, Cordell gives up the cut on every gallon and keeps the drivers.
        // A crude shock reaches the pump at 60 cents on the dollar, and drivers barely react.
        $crudeVf = 1 + $c('retail_crude_elasticity') * $c('retail_crude_passthrough') * ($shock / $c('gal_per_bbl')) / $c('pump_base');
        $cordGalTotal = $c('cordell_sites') * $c('cordell_gal_per_site_qtr') * $crudeVf;
        $nonfuelPerGal = (float) ($mkt['cordell_nonfuel'] ?? 0) > 0 ? (float) $mkt['cordell_nonfuel'] : $c('cordell_nonfuel_per_gal');
        $rivalCut = (float) ($mkt['rival_cut'] ?? 0.0);
        $cordFuel = 0.0;
        $cordNonfuel = 0.0;
        $matchCost = 0.0;
        $ignoreCost = 0.0;
        foreach ($this->data->cordell as $cl) {
            [$vf, $delta] = $volumeFactor($cl, $offsets[$cl['key']]);
            $gal = $cordGalTotal * $cl['share'] * $vf;
            $cutGiven = 0.0;
            if ($rivalCut > 0) {
                if (($dec->responses[$cl['key']] ?? 'ignore') === 'match') {
                    $cutGiven = $rivalCut;
                    $matchCost += $gal * $rivalCut;
                } else {
                    $lost = $gal * (-$cl['e']) * $rivalCut / $c('pump_base');
                    $ignoreCost += $lost * ($c('cordell_fuel_margin') + $delta + $nonfuelPerGal * $c('cordell_nonfuel_halden_share'));
                    $gal -= $lost;
                }
            }
            $cordFuel += $gal * ($c('cordell_fuel_margin') + $delta - $cutGiven);
            $cordNonfuel += $gal * $nonfuelPerGal * $c('cordell_nonfuel_halden_share');
        }
        $lines['cordell_fuel'] = $cordFuel / 1e6;
        $lines['cordell_shop'] = $cordNonfuel / 1e6;
        $lines['cordell_price_match'] = -$matchCost / 1e6;   // already inside cordell_fuel; shown on its own

        $euFactor = $state->europeVolumeFactor * (1 - $c('europe_volume_decline_qtr'));
        $euGalTotal = $c('europe_sites') * $c('europe_gal_per_site_qtr') * $euFactor * $crudeVf;
        $eu = 0.0;
        foreach ($this->data->europe as $cl) {
            [$vf, $delta] = $volumeFactor($cl, $offsets[$cl['key']]);
            $gal = $euGalTotal * $cl['share'] * $vf;
            $eu += $gal * ($c('europe_fuel_margin') + $delta + $c('europe_nonfuel_per_gal'));
        }
        $lines['europe_stations'] = $eu / 1e6 * $eurF;
        $lines['retail_fixed'] = -$c('retail_fixed_cost');
        $retail = $lines['cordell_fuel'] + $lines['cordell_shop'] + $lines['europe_stations'] + $lines['retail_fixed'];

        // Head office
        $lines['head_office'] = -$c('corporate_ga');
        $lines['advisor_time'] = -$c('advisor_cost_per_answer') * $dec->advisorAnswers;
        $h = $state->hedges;
        $settle = 0.0;
        if ($h !== []) {
            $settle += ($h['crude_bbl_day'] ?? 0.0) * $D * (($h['crude_price'] ?? $wti) - $wti) / 1e6;
            if (($h['eur'] ?? 0.0) != 0.0) {
                $settle += $h['eur'] * ($h['eur_rate'] - $mkt['eurusd']) / $h['eur_rate'];
            }
            if (($h['nok'] ?? 0.0) != 0.0) {
                $settle += $h['nok'] * ($h['nok_rate'] / $mkt['usdnok'] - 1);
            }
        }
        if ((bool) ($mkt['existing_eur_hedge'] ?? false)) {
            $r0 = $c('existing_eur_hedge_rate');
            $settle += $c('existing_eur_hedge_notional') * ($r0 - $mkt['eurusd']) / $r0;
        }
        $lines['hedges'] = $settle;
        $corporate = $lines['head_office'] + $lines['advisor_time'] + $lines['hedges'];

        $upstream = $upstreamBase + $lines['projects_upstream'];
        $seg = ['oil_fields' => $upstream, 'refineries' => $refining, 'geneva' => $trading, 'gas_stations' => $retail, 'head_office' => $corporate];
        $ebitda = 0.0;
        foreach ($seg as $v) {
            $ebitda += $v;
        }

        // Money
        $da = $state->capitalEmployed * $c('da_rate_annual') / 4;
        $tax = $c('tax_rate') * max(0.0, $ebitda - $da);
        $capex = $c('other_sustaining_capex') + $dec->rigs * $c('rig_capex_per_qtr') + $commitOutlay;
        $fcf = $ebitda - $tax - $capex;
        $newCe = $state->capitalEmployed + $capex - $da;
        $newNd = $state->netDebt - $fcf + $c('shareholder_payout');

        // Plant condition
        $health = $state->assetHealth;
        $health -= 0.5 * max(0.0, $dec->brRun - $c('br_wear_threshold'));
        if ($rotStatus === 'running' && $dec->rotRun < $c('rot_sticky_threshold')) {
            $health -= 0.5;
        }
        $health = max(0.0, min(100.0, $health));

        // Score inputs
        $refinedBbl = ($brTpBbl + $rotTp + $sgTp) * $D;
        $refiningExInternal = ($brCrackMargin - $brFixed) + $lines['rotterdam'] + $lines['singapore'];
        $benchCrack = ($brTpBbl * $mkt['gc'] + $rotTp * $mkt['nwe'] + $sgTp * $mkt['sg']) / max(1.0, $brTpBbl + $rotTp + $sgTp);
        $benchMargin = $benchCrack + $c('industry_complexity') - $c('industry_opex');
        $kpi = [
            'profit_per_barrel' => ($ebitda - $corporate) * 1e6 / ($c('total_production') * $D),
            'roace_pct' => 100 * 4 * ($ebitda - $da) * (1 - $c('tax_rate')) / $state->capitalEmployed,
            // Before growth projects (decision S2, Vikram 9 Oct 2026): a sound project isn't punished in the quarter its money goes out.
            'free_cash_flow' => $fcf + $commitOutlay,
            'refining_vs_industry' => $refinedBbl > 0 ? $refiningExInternal * 1e6 / $refinedBbl - $benchMargin : -$benchMargin,
            'shop_profit_per_station_k' => $cordNonfuel / $c('cordell_sites') / 1e3,
            'debt_to_earnings' => $ebitda > 0 ? $newNd / (4 * $ebitda) : 99.0,
            'plant_condition' => $health,
        ];

        $nextProd = $prod * (1 - $c('permian_decline_qtr')) + $this->permianAdds($dec->rigs);

        // Hedges opened now settle next quarter: crude at this quarter's price, currencies at today's rates.
        $nextCrude = $nextProd + $norVol * (1 - $cut) + $c('norway_nonop_volume');
        $newHedges = [];
        if ($dec->crudeHedgePct > 0) {
            $newHedges['crude_bbl_day'] = $dec->crudeHedgePct / 100.0 * $nextCrude;
            $newHedges['crude_price'] = $wti;
        }
        if ($dec->eurHedge > 0) {
            $newHedges['eur'] = $dec->eurHedge;
            $newHedges['eur_rate'] = $mkt['eurusd'];
        }
        if ($dec->nokHedge > 0) {
            $newHedges['nok'] = $dec->nokHedge;
            $newHedges['nok_rate'] = $mkt['usdnok'];
        }
        $fxEffect = 0.0;
        if ($fx) {
            $fxEffect = $lines['rotterdam'] * (1 - 1 / $eurF) + $lines['europe_stations'] * (1 - 1 / $eurF)
                + $lines['singapore'] * (1 - 1 / $sgdF)
                + ($c('norway_lifting') - $norLifting) * ($norVol * (1 - $cut * $c('norway_lifting_variable_share')) + $c('norway_nonop_volume')) * $D / 1e6;
        }

        $newState = new CompanyState(
            permianProd: $nextProd,
            prevRigs: $dec->rigs,
            rotStatus: $rotStatus,
            capitalEmployed: $newCe,
            netDebt: $newNd,
            assetHealth: $health,
            europeVolumeFactor: $euFactor,
            heldUp: $heldUp,
            heldDown: $heldDown,
            hedges: $newHedges,
            projects: $projAge,
        );

        return new QuarterResult(
            lines: $lines,
            segments: $seg,
            money: ['ebitda' => $ebitda, 'da' => $da, 'tax' => $tax, 'capex' => $capex, 'fcf' => $fcf,
                'capital_employed_end' => $newCe, 'net_debt_end' => $newNd],
            kpi: $kpi,
            ops: ['tp' => $tp, 'market_tp' => $marketTp, 'cost_tp' => $costTp, 'permian_prod' => $prod,
                'br_throughput' => $brTpBbl, 'rot_throughput' => $rotTp, 'sg_accepted' => $sgRun, 'rot_status' => $rotStatus,
                'fx_effect' => $fxEffect, 'project_outlay' => $commitOutlay, 'nwe' => $mkt['nwe'],
                'rival_match_cost' => $matchCost / 1e6, 'rival_ignore_cost' => $ignoreCost / 1e6,
                'wti_shock' => $shock, 'gc' => $mkt['gc'], 'wti' => $wti],
            notes: $notes,
            state: $newState,
        );
    }

    /**
     * Runs the four 2026 history quarters with the old presidents' settings.
     *
     * @return array{0: CompanyState, 1: list<array{quarter: array<string, mixed>, result: QuarterResult}>}
     */
    public function runHistory(): array
    {
        $state = $this->openingState();
        $history = [];
        foreach ($this->data->market as $m) {
            if (! $m['is_history']) {
                continue;
            }
            $result = $this->step($state, new Decisions(offsets: $this->data->baseOffsets()), $m);
            $state = $result->state;
            $history[] = ['quarter' => $m, 'result' => $result];
        }

        return [$state, $history];
    }
}
