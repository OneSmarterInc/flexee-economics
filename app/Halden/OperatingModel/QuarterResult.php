<?php

namespace App\Halden\OperatingModel;

/**
 * Everything one quarter produced for one team: named P&L lines, segment EBITDA, money,
 * the seven score inputs, operating facts, notes and the state carried to next quarter.
 */
final class QuarterResult
{
    /**
     * @param  array<string, float>  $lines
     * @param  array<string, float>  $segments
     * @param  array<string, float>  $money
     * @param  array<string, float>  $kpi
     * @param  array<string, float|string>  $ops
     * @param  array<string, mixed>  $notes
     */
    public function __construct(
        public readonly array $lines,
        public readonly array $segments,
        public readonly array $money,
        public readonly array $kpi,
        public readonly array $ops,
        public readonly array $notes,
        public readonly CompanyState $state,
    ) {}

    /** @return array<string, float> every number keyed the way fixtures/golden_quarters.csv keys it */
    public function metrics(): array
    {
        $out = [];
        foreach ($this->lines as $k => $v) {
            $out["line.$k"] = $v;
        }
        foreach ($this->segments as $k => $v) {
            $out["segment.$k"] = $v;
        }
        foreach ($this->money as $k => $v) {
            $out["money.$k"] = $v;
        }
        foreach ($this->kpi as $k => $v) {
            $out["kpi.$k"] = $v;
        }
        foreach (['tp', 'market_tp', 'cost_tp', 'permian_prod', 'br_throughput', 'rot_throughput', 'sg_accepted', 'fx_effect', 'project_outlay', 'nwe'] as $k) {
            $out["ops.$k"] = (float) $this->ops[$k];
        }

        return $out;
    }
}
