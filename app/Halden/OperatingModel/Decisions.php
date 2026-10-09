<?php

namespace App\Halden\OperatingModel;

/**
 * Everything a team decides for one quarter. Anything a team has not changed keeps last
 * quarter's value before it reaches the engine.
 */
final class Decisions
{
    /**
     * @param  array<string, float>  $offsets  cents per gallon above (+) or below (-) the going rate, by station market
     * @param  array<string, string>  $projects  project key => commit | hold
     * @param  array<string, string>  $responses  Cordell cluster => ignore | match the rival's street cut
     */
    public function __construct(
        public int $rigs = 14,
        public string $norway = 'run',          // run | cut
        public float $brRun = 96.0,             // percent
        public float $rotRun = 82.0,            // percent, used while Rotterdam is running
        public string $rotPosture = 'run',      // run | idle | close
        public float $sgRequest = 90.0,         // percent requested of Straits Pacific
        public array $offsets = [],
        public string $tpMethod = 'market',     // market | cost | other
        public ?float $tpValue = null,          // used when tpMethod is other
        public int $advisorAnswers = 0,
        public float $crudeHedgePct = 0.0,      // percent of next quarter's crude sold forward at this quarter's price
        public float $eurHedge = 0.0,           // USD m of euros sold forward for next quarter
        public float $nokHedge = 0.0,           // USD m of kroner bought forward for next quarter
        public array $projects = [],
        public array $responses = [],
        public string $capacityResponse = 'hold',  // hold | match the rival's Gulf Coast expansion
    ) {}

    /** @param  array<string, mixed>  $row  a row shaped like fixtures/reference_decisions.csv */
    public static function fromRow(array $row, ModelData $data): self
    {
        $offsets = $data->baseOffsets();
        foreach (array_keys($offsets) as $k) {
            if (isset($row["off_$k"]) && $row["off_$k"] !== '') {
                $offsets[$k] = (float) $row["off_$k"];
            }
        }

        return new self(
            rigs: (int) $row['rigs'],
            norway: (string) $row['norway'],
            brRun: (float) $row['br_run'],
            rotRun: (float) $row['rot_run'],
            rotPosture: (string) $row['rot_posture'],
            sgRequest: (float) $row['sg_request'],
            offsets: $offsets,
            tpMethod: (string) $row['tp_method'],
            tpValue: ($row['tp_value'] ?? '') === '' ? null : (float) $row['tp_value'],
            advisorAnswers: (int) ($row['advisor_answers'] ?? 0),
            crudeHedgePct: (float) ($row['crude_hedge_pct'] ?? 0),
            eurHedge: (float) ($row['eur_hedge'] ?? 0),
            nokHedge: (float) ($row['nok_hedge'] ?? 0),
            projects: array_filter(array_map(fn (string $k): string => (string) ($row["proj_$k"] ?? 'hold'), array_combine(array_keys($data->projects), array_keys($data->projects)))),
            responses: self::responsesFromRow($row, $data),
            capacityResponse: (string) ($row['capacity_response'] ?? 'hold'),
        );
    }

    /**
     * @param  array<string, mixed>  $row
     * @return array<string, string>
     */
    private static function responsesFromRow(array $row, ModelData $data): array
    {
        $out = [];
        foreach ($data->cordell as $c) {
            $out[$c['key']] = (string) ($row['resp_'.$c['key']] ?? 'ignore');
        }

        return $out;
    }
}
