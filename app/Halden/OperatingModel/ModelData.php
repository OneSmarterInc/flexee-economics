<?php

namespace App\Halden\OperatingModel;

use RuntimeException;

/**
 * Reads the operating-model package (packages/operating-model/data). This is the only place
 * the engine gets its numbers from; nothing numeric is hard-coded in the engine itself.
 */
final class ModelData
{
    /** @var array<string, float> */
    public readonly array $constants;

    /** @var list<array<string, mixed>> */
    public readonly array $market;

    /** @var list<array{key: string, label: string, share: float, e: float, pt: float, base: float}> */
    public readonly array $cordell;

    /** @var array<string, array{label: string, segment: string, haircut: float, outlay: float, cf: list<float>}> */
    public readonly array $projects;

    /** @var list<array{behaviour: string, rate: float, envelope: float}> */
    public readonly array $cohortCapital;

    /** @var array<string, float> "action|outcome" => Halden's yearly payoff (USD m) in the capacity game */
    public readonly array $capacityGame;

    /** @var array<string, array{label: string, p: float, dwti: float}> OPEC+ outcomes by key */
    public readonly array $opec;

    /** @var array<string, array{label: string, equity: string, sites: float, keep: float, halden: float}> Cordell regions by what the name is worth */
    public readonly array $rebrand;

    /** @var array<string, float> product => income elasticity */
    public readonly array $elasticity;

    /** @var array<string, array<string, float>> refinery => product => yield */
    public readonly array $yields;

    /** @var list<array{key: string, label: string, share: float, e: float, pt: float, base: float}> */
    public readonly array $europe;

    public function __construct(?string $root = null, ?float $gasOther = null)
    {
        $root ??= base_path('packages/operating-model');
        $constants = [];
        foreach (self::csv("$root/data/constants.csv") as $row) {
            $constants[$row['key']] = (float) $row['value'];
        }
        if ($gasOther === null) {
            $calibration = json_decode((string) file_get_contents("$root/fixtures/calibration.json"), true, flags: JSON_THROW_ON_ERROR);
            $gasOther = (float) $calibration['gas_other_ebitda'];
        }
        $constants['gas_other_ebitda'] = $gasOther;
        $this->constants = $constants;

        $this->market = array_map(fn (array $r): array => [
            'quarter' => $r['quarter'],
            'label' => $r['label'],
            'is_history' => $r['is_history'] === '1',
            'wti' => (float) $r['wti'],
            'gc' => (float) $r['gc_crack'],
            'nwe' => (float) $r['nwe_crack'],
            'sg' => (float) $r['sg_crack'],
            'eurusd' => (float) $r['eurusd'],
            'usdnok' => (float) $r['usdnok'],
            'usdsgd' => (float) $r['usdsgd'],
            'fx_live' => ($r['fx_live'] ?? '0') === '1',
            'existing_eur_hedge' => ($r['existing_eur_hedge'] ?? '0') === '1',
            'rival_cut' => (float) ($r['rival_cut'] ?? 0),
            'rival_builds' => ($r['rival_builds'] ?? '0') === '1',
            'opec' => ($r['opec'] ?? '0') === '1',
            'recession' => ($r['recession'] ?? '0') === '1',
        ], self::csv("$root/data/market_path.csv"));

        $projects = [];
        foreach (self::csv("$root/data/projects.csv") as $r) {
            $cf = [];
            for ($i = 1; $i <= 10; $i++) {
                $cf[] = (float) $r["cf$i"];
            }
            $projects[$r['key']] = ['label' => $r['label'], 'segment' => $r['segment'], 'haircut' => (float) $r['haircut'], 'outlay' => (float) $r['outlay'], 'cf' => $cf];
        }
        $this->projects = $projects;
        $this->cohortCapital = array_map(fn (array $r): array => [
            'behaviour' => $r['behaviour'], 'rate' => (float) $r['discount_rate'], 'envelope' => (float) $r['capital_envelope'],
        ], self::csv("$root/data/cohort_capital.csv"));

        $game = [];
        foreach (self::csv("$root/data/capacity_game.csv") as $r) {
            $game[$r['halden_action'].'|'.$r['rival_outcome']] = (float) $r['payoff_musd_per_year'];
        }
        $this->capacityGame = $game;
        $opec = [];
        foreach (self::csv("$root/data/opec_scenarios.csv") as $r) {
            $opec[$r['key']] = ['label' => $r['label'], 'p' => (float) $r['probability'], 'dwti' => (float) $r['delta_wti']];
        }
        $this->opec = $opec;
        $rebrand = [];
        foreach (self::csv("$root/data/rebrand_markets.csv") as $r) {
            $rebrand[$r['key']] = ['label' => $r['label'], 'equity' => $r['equity'], 'sites' => (float) $r['sites'],
                'keep' => (float) $r['keep_uplift_per_fill'], 'halden' => (float) $r['halden_benefit_per_fill']];
        }
        $this->rebrand = $rebrand;
        $el = [];
        foreach (self::csv("$root/data/product_elasticities.csv") as $r) {
            $el[$r['product']] = (float) $r['income_elasticity'];
        }
        $this->elasticity = $el;
        $yields = [];
        foreach (self::csv("$root/data/refinery_yields.csv") as $r) {
            $yields[$r['refinery']] = ['gasoline' => (float) $r['gasoline'], 'diesel' => (float) $r['diesel'], 'jet' => (float) $r['jet'], 'other' => (float) $r['other']];
        }
        $this->yields = $yields;

        $this->cordell = self::clusters("$root/data/cordell_clusters.csv", 'cluster');
        $this->europe = self::clusters("$root/data/europe_countries.csv", 'country');
    }

    public function c(string $key): float
    {
        if (! array_key_exists($key, $this->constants)) {
            throw new RuntimeException("Unknown operating-model constant [$key].");
        }

        return $this->constants[$key];
    }

    /** @return array<string, mixed> */
    public function quarter(string $key): array
    {
        foreach ($this->market as $m) {
            if ($m['quarter'] === $key) {
                return $m;
            }
        }
        throw new RuntimeException("Unknown quarter [$key].");
    }

    /** @return array<string, float> base price offsets (cents per gallon) for every station market */
    public function baseOffsets(): array
    {
        $out = [];
        foreach (array_merge($this->cordell, $this->europe) as $c) {
            $out[$c['key']] = $c['base'];
        }

        return $out;
    }

    /** @return list<array<string, string>> */
    public static function csv(string $path): array
    {
        $handle = fopen($path, 'r');
        if ($handle === false) {
            throw new RuntimeException("Cannot read [$path].");
        }
        $header = fgetcsv($handle, escape: '');
        if ($header === false) {
            fclose($handle);
            throw new RuntimeException("[$path] has no header row.");
        }
        $keys = array_map(fn (?string $h): string => (string) $h, $header);
        $rows = [];
        while (($line = fgetcsv($handle, escape: '')) !== false) {
            if ($line === [null]) {
                continue;
            }
            if (count($line) !== count($keys)) {
                fclose($handle);
                throw new RuntimeException("[$path] has a row with the wrong number of columns.");
            }
            $rows[] = array_combine($keys, array_map(fn (?string $v): string => (string) $v, $line));
        }
        fclose($handle);

        return $rows;
    }

    /** @return list<array{key: string, label: string, share: float, e: float, pt: float, base: float}> */
    private static function clusters(string $path, string $keyColumn): array
    {
        return array_map(fn (array $r): array => [
            'key' => $r[$keyColumn],
            'label' => $r['label'],
            'share' => (float) $r['volume_share'],
            'e' => (float) $r['elasticity'],
            'pt' => (float) $r['pass_through'],
            'base' => (float) $r['base_offset_cents'],
        ], self::csv($path));
    }
}
