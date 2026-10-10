<?php

namespace App\Halden\Content;

use RuntimeException;

/**
 * Everything students read comes from packages/content. This class only selects and fills it in.
 */
final class ContentPack
{
    /** @var array<int, array<string, mixed>> quarter number => content */
    private array $quarters;

    /** @var array<string, mixed> */
    private array $opening;

    /** @var array<string, mixed> */
    private array $levers;

    public function __construct(?string $root = null)
    {
        $root ??= base_path('packages/content');
        $this->quarters = [];
        $raw = json_decode((string) file_get_contents("$root/quarters.json"), true, flags: JSON_THROW_ON_ERROR);
        foreach (is_array($raw) ? $raw : [] as $n => $q) {
            if (is_int($n) && is_array($q)) {
                $this->quarters[$n] = $q;
            }
        }
        $this->opening = json_decode((string) file_get_contents("$root/opening.json"), true, flags: JSON_THROW_ON_ERROR);
        $this->levers = json_decode((string) file_get_contents("$root/levers.json"), true, flags: JSON_THROW_ON_ERROR);
    }

    /** @return array<string, mixed> help text and button words for the decision pages */
    public function leverText(): array
    {
        return $this->levers;
    }

    /** @return array<string, mixed> */
    public function opening(): array
    {
        return $this->opening;
    }

    public function hasQuarter(int $n): bool
    {
        return isset($this->quarters[$n]);
    }

    /** @return array<string, mixed> */
    public function quarter(int $n): array
    {
        if (! $this->hasQuarter($n)) {
            throw new RuntimeException("No content for quarter $n yet.");
        }

        return $this->quarters[$n];
    }

    /** Which quarter lists this file as reading, or null if none does. */
    public function exhibitQuarter(string $file): ?int
    {
        foreach ($this->quarters as $n => $q) {
            if (! is_array($q['exhibits'] ?? null)) {
                continue;
            }
            foreach ($q['exhibits'] as $e) {
                if (is_array($e) && ($e['file'] ?? null) === $file) {
                    return $n;
                }
            }
        }

        return null;
    }

    public static function exhibitPath(string $file): string
    {
        $base = realpath(base_path('packages/content/exhibits'));
        $path = realpath(base_path('packages/content/exhibits/'.$file));
        if ($base === false || $path === false || ! str_starts_with($path, $base.DIRECTORY_SEPARATOR)) {
            throw new RuntimeException('Unknown file.');
        }

        return $path;
    }

    /**
     * Picks the results story band for this quarter and fills in the team's numbers.
     *
     * @param  array<string, float|string>  $results
     * @param  array<string, string|float|int|null>  $decisions
     * @param  array<string, float>  $baseOffsets
     * @param  array<string, string>  $extra  placeholders the caller supplies (names from the model data, say)
     * @return array{title: string, paragraphs: list<string>, band: string}
     */
    public function story(int $n, array $results, array $decisions, array $baseOffsets, array $extra = []): array
    {
        $story = $this->quarter($n)['story'];
        $band = $this->band((string) $story['band_on'], $results, $decisions, $baseOffsets);
        $chosen = null;
        foreach ($story['bands'] as $b) {
            if (isset($b['key']) ? $b['key'] === $band['key'] : $band['value'] <= (float) $b['max']) {
                $chosen = $b;
                break;
            }
        }
        $chosen ??= end($story['bands']);
        $vars = self::placeholders($results, $decisions) + $extra;
        // A fill is a sentence picked by a choice (or a band); it can hold placeholders of its own, so fill those first.
        $vars += array_map(fn (string $t): string => strtr($t, $vars), $this->fills($n, $results, $decisions, $baseOffsets));
        $fill = fn (string $t): string => strtr($t, $vars);

        return [
            'title' => $fill((string) $chosen['title']),
            'paragraphs' => array_values(array_map($fill, [...$chosen['paragraphs'], (string) $story['closing']])),
            'band' => $band['key'],
        ];
    }

    /**
     * @param  array<string, float|string>  $results
     * @param  array<string, string|float|int|null>  $decisions
     * @param  array<string, float>  $baseOffsets
     * @return list<array{who: string, was: string, now: string, why: string}>
     */
    public function relations(int $n, array $results, array $decisions, array $baseOffsets, ?string $firstMeeting): array
    {
        $rel = $this->quarter($n)['relations'];
        $key = $rel['band_on'] === 'first_meeting'
            ? ($firstMeeting ?? 'none')
            : $this->band((string) $rel['band_on'], $results, $decisions, $baseOffsets)['key'];

        return array_values($rel['bands'][$key] ?? []);
    }

    /**
     * @param  array<string, float|string>  $results
     * @param  array<string, string|float|int|null>  $decisions
     * @param  array<string, float>  $baseOffsets
     * @return array{key: string, value: float}
     */
    public function band(string $on, array $results, array $decisions, array $baseOffsets): array
    {
        switch ($on) {
            case 'rigs':
                $v = (float) $decisions['rigs'];

                return ['key' => $v <= 12 ? 'fewer' : ($v <= 15 ? 'steady' : 'more'), 'value' => $v];
            case 'price_move':
                $sum = 0.0;
                foreach ($baseOffsets as $k => $base) {
                    $sum += (float) ($decisions["off_$k"] ?? $base) - $base;
                }
                $v = $sum / max(1, count($baseOffsets));

                return ['key' => $v < -0.25 ? 'down' : ($v <= 0.25 ? 'flat' : 'up'), 'value' => $v];
            case 'rotterdam':
                $status = (string) ($results['ops.rot_status'] ?? 'running');
                if ($status !== 'running') {
                    return ['key' => $status, 'value' => 0.0];
                }

                return ['key' => (float) $decisions['rot_run'] >= 85 ? 'running_hard' : 'running_slow', 'value' => (float) $decisions['rot_run']];
            case 'tp':
                $tp = (float) $results['ops.tp'];
                $cost = (float) $results['ops.cost_tp'];
                $market = (float) $results['ops.market_tp'];
                $key = abs($tp - $cost) <= 0.1 * $cost ? 'cost' : (($tp >= $market * 0.9) ? 'market' : 'between');

                return ['key' => $key, 'value' => $tp];
            case 'hedges':
                $crude = (float) ($decisions['crude_hedge'] ?? 0);
                $eur = (float) ($decisions['eur_hedge'] ?? 0);
                $nok = (float) ($decisions['nok_hedge'] ?? 0);
                $key = $crude + $eur + $nok <= 0 ? 'none' : (($crude >= 40 || $eur >= 450 || $nok >= 450) ? 'heavy' : 'measured');

                return ['key' => $key, 'value' => $crude];
            case 'rival':
                $matched = 0;
                foreach (['urban', 'suburban', 'rural', 'interstate'] as $k) {
                    $matched += ($decisions["resp_$k"] ?? 'ignore') === 'match' ? 1 : 0;
                }

                return ['key' => $matched === 0 ? 'held' : ($matched >= 3 ? 'matched' : 'mixed'), 'value' => (float) $matched];
            case 'rebrand':
                $n = 0;
                foreach (['core', 'gulf', 'edge'] as $k) {
                    $n += ($decisions["rebrand_$k"] ?? 'keep') === 'rebrand' ? 1 : 0;
                }

                return ['key' => $n === 0 ? 'none' : ($n === 3 ? 'full' : 'partial'), 'value' => (float) $n];
            case 'window3':
                $margin = (float) ($results['ops.nonfuel_per_gal'] ?? 0.42);

                return ['key' => $margin < 0.42 - 0.005 ? 'war' : ($margin > 0.42 + 0.005 ? 'calm' : 'base'), 'value' => $margin];
            case 'binding':
                $cover = (float) ($results['history.delacroix_cover'] ?? 0) > 0;
                $partner = (float) ($results['history.sg_cut_by_partner'] ?? 0) > 0;

                return ['key' => $cover && $partner ? 'both' : ($cover ? 'cover' : ($partner ? 'partner' : 'none')), 'value' => (float) ((int) $cover + (int) $partner)];
            case 'recession':
                $bound = $this->band('binding', $results, $decisions, $baseOffsets);

                return ['key' => $bound['key'] === 'none' ? 'room' : 'bound', 'value' => $bound['value']];
            case 'opec':
                $shock = (float) ($results['ops.wti_shock'] ?? 0);

                return ['key' => $shock > 10 ? 'full' : ($shock > 0 ? 'partial' : 'fails'), 'value' => $shock];
            case 'projects':
                $helix = ($decisions['proj_helix'] ?? 'hold') === 'commit';
                $refining = ($decisions['proj_br_upgrade'] ?? 'hold') === 'commit' || ($decisions['proj_rot_upgrade'] ?? 'hold') === 'commit';
                $key = $helix ? 'helix' : ($refining ? 'refining' : 'none');

                return ['key' => $key, 'value' => (float) ($results['ops.project_outlay'] ?? 0)];
        }
        throw new RuntimeException("Unknown story band [$on].");
    }

    /**
     * Sentences a quarter's story picks by one of the team's choices, or by a band ("fills" in quarters.json;
     * "on" names a decision, or "band:<name>").
     *
     * @param  array<string, float|string>  $r
     * @param  array<string, string|float|int|null>  $d
     * @param  array<string, float>  $baseOffsets
     * @return array<string, string>
     */
    private function fills(int $n, array $r, array $d, array $baseOffsets): array
    {
        $out = [];
        foreach ((array) ($this->quarter($n)['fills'] ?? []) as $placeholder => $fill) {
            if (! is_array($fill)) {
                continue;
            }
            $on = (string) $fill['on'];
            $choice = str_starts_with($on, 'band:') ? $this->band(substr($on, 5), $r, $d, $baseOffsets)['key'] : (string) ($d[$on] ?? '');
            $out[(string) $placeholder] = (string) ($fill[$choice] ?? '');
        }

        return $out;
    }

    /**
     * @param  array<string, float|string>  $r
     * @param  array<string, string|float|int|null>  $d
     * @return array<string, string>
     */
    private static function placeholders(array $r, array $d): array
    {
        $money = fn (string $k): string => self::money((float) ($r[$k] ?? 0));

        return [
            '{rigs}' => (string) $d['rigs'],
            '{ebitda}' => $money('money.ebitda'),
            '{fcf}' => $money('money.fcf'),
            '{permian_prod}' => number_format(round((float) ($r['ops.permian_prod'] ?? 0), -2)),
            '{cordell_fuel}' => $money('line.cordell_fuel'),
            '{rotterdam}' => $money('line.rotterdam'),
            '{geneva}' => $money('line.geneva_gap_trading'),
            '{sg_accepted}' => number_format((float) ($r['ops.sg_accepted'] ?? 0)),
            '{fx_effect}' => $money('ops.fx_effect'),
            '{hedges}' => $money('line.hedges'),
            '{project_outlay}' => $money('ops.project_outlay'),
            '{nwe}' => '$'.number_format((float) ($r['ops.nwe'] ?? 0), 2),
            '{match_cost}' => $money('ops.rival_match_cost'),
            '{ignore_cost}' => $money('ops.rival_ignore_cost'),
            '{wti}' => '$'.number_format((float) ($r['ops.wti'] ?? 0), 0),
            '{gc}' => '$'.number_format((float) ($r['ops.gc'] ?? 0), 2),
            '{bought_ahead}' => $money('line.crude_bought_ahead'),
            '{inventory_carry}' => self::money(abs((float) ($r['line.inventory_carry'] ?? 0))),   // always a cost, so shown without a sign
            '{rebrand_outlay}' => $money('ops.rebrand_outlay'),
            '{cordell_shop}' => $money('line.cordell_shop'),
            '{nonfuel}' => rtrim(rtrim(number_format((float) ($r['ops.nonfuel_per_gal'] ?? 0) * 100, 1), '0'), '.').' cents',
        ];
    }

    public static function money(float $millions): string
    {
        $sign = $millions < 0 ? '−' : '';

        return $sign.'$'.number_format(abs($millions), abs($millions) < 10 ? 1 : 0).'M';
    }
}
