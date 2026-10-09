<?php

namespace App\Halden\Game;

use App\Halden\OperatingModel\Decisions;
use App\Halden\OperatingModel\ModelData;
use App\Models\Quarter;
use App\Models\Team;
use App\Models\TeamQuarter;

/**
 * What each team has decided. Reads the decision list from packages/operating-model/data/levers.csv:
 * which page each decision is on, its limits, its 2026 value, and the quarter it first appears.
 * Anything a team has not changed keeps last quarter's value.
 */
final class DecisionBook
{
    /** @var array<string, array{key: string, page: string, label: string, unit: string, min: ?float, max: ?float, step: ?float, choices: list<string>, default: string, unlock: int, tier: string}> */
    private array $levers = [];

    public function __construct(private readonly ModelData $data)
    {
        foreach (ModelData::csv(base_path('packages/operating-model/data/levers.csv')) as $r) {
            $choices = str_starts_with($r['unit'], 'choice ') ? explode('|', substr($r['unit'], 7)) : [];
            $this->levers[$r['key']] = [
                'key' => $r['key'],
                'page' => $r['page'],
                'label' => $r['label_on_screen'],
                'unit' => $choices === [] ? $r['unit'] : 'choice',
                'min' => $r['min'] === '' ? null : (float) $r['min'],
                'max' => $r['max'] === '' ? null : (float) $r['max'],
                'step' => $r['step'] === '' ? null : (float) $r['step'],
                'choices' => $choices,
                'default' => $r['default_history'],
                'unlock' => (int) $r['unlock_round'],
                'tier' => $r['tier'],
            ];
        }
    }

    /** @return array<string, array{key: string, page: string, label: string, unit: string, min: ?float, max: ?float, step: ?float, choices: list<string>, default: string, unlock: int, tier: string}> */
    public function levers(): array
    {
        return $this->levers;
    }

    /** The old presidents' settings, which run in 2026 and fill "last quarter" in Quarter 1. */
    /** @return array<string, string|float|int|null> */
    public function historyDefaults(): array
    {
        $out = [];
        foreach ($this->levers as $key => $l) {
            if ($key === 'tp') {
                $out['tp_method'] = 'market';
                $out['tp_value'] = null;

                continue;
            }
            $out[$key] = $l['unit'] === 'choice' ? $l['default'] : ($key === 'rigs' ? (int) $l['default'] : (float) $l['default']);
        }

        return $out;
    }

    public function isUnlocked(string $key, int $quarterNumber): bool
    {
        $lever = $this->levers[$key === 'tp_method' || $key === 'tp_value' ? 'tp' : $key] ?? null;

        return $lever !== null && $lever['unlock'] <= $quarterNumber;
    }

    /** @return list<string> pages a team can change in this quarter */
    public function openPages(int $quarterNumber): array
    {
        $pages = [];
        foreach ($this->levers as $l) {
            if ($l['unlock'] <= $quarterNumber && ! in_array($l['page'], $pages, true)) {
                $pages[] = $l['page'];
            }
        }

        return $pages;
    }

    /** @return array<string, string|float|int|null> what last quarter ran with (or 2026 settings in Quarter 1) */
    public function previousEffective(Team $team, Quarter $quarter): array
    {
        $prev = $quarter->previous();
        if ($prev !== null) {
            $tq = TeamQuarter::query()->where('team_id', $team->id)->where('quarter_id', $prev->id)->first();
            if ($tq !== null && is_array($tq->effective_decisions)) {
                return $tq->effective_decisions;
            }
        }

        return $this->historyDefaults();
    }

    /** @return array<string, string|float|int|null> what will run this quarter if nothing else changes */
    public function effective(Team $team, Quarter $quarter): array
    {
        $out = $this->previousEffective($team, $quarter);
        $tq = TeamQuarter::query()->where('team_id', $team->id)->where('quarter_id', $quarter->id)->first();
        foreach ($tq === null ? [] : ($tq->decisions ?? []) as $key => $value) {
            if ($this->isUnlocked($key, $quarter->number)) {
                $out[$key] = $value;
            }
        }

        return $out;
    }

    /**
     * Checks one page's input and returns the clean values plus plain-English errors by field.
     *
     * @param  array<string, mixed>  $input
     * @return array{0: array<string, string|float|int|null>, 1: array<string, string>}
     */
    public function validatePage(string $page, array $input, int $quarterNumber): array
    {
        $clean = [];
        $errors = [];
        foreach ($this->levers as $key => $l) {
            if ($l['page'] !== $page || $l['unlock'] > $quarterNumber) {
                continue;
            }
            if ($key === 'tp') {
                $method = (string) ($input['tp_method'] ?? '');
                if (! in_array($method, ['market', 'cost', 'other'], true)) {
                    $errors['tp_method'] = 'Pick how you want to set the price.';

                    continue;
                }
                $clean['tp_method'] = $method;
                $clean['tp_value'] = null;
                if ($method === 'other') {
                    $v = $input['tp_value'] ?? null;
                    if (! is_numeric($v) || (float) $v < (float) $l['min'] || (float) $v > (float) $l['max']) {
                        $errors['tp_value'] = sprintf('Enter a price from $%s to $%s a barrel.', (int) $l['min'], (int) $l['max']);
                    } else {
                        $clean['tp_value'] = round((float) $v, 2);
                    }
                }

                continue;
            }
            if (! array_key_exists($key, $input)) {
                continue;
            }
            $v = $input[$key];
            if ($l['unit'] === 'choice') {
                if (! in_array($v, $l['choices'], true)) {
                    $errors[$key] = 'Pick one of the options.';
                } else {
                    $clean[$key] = $v;
                }

                continue;
            }
            if (! is_numeric($v) || (float) $v < (float) $l['min'] || (float) $v > (float) $l['max']) {
                $errors[$key] = sprintf('Enter a number from %s to %s.', self::fmt((float) $l['min']), self::fmt((float) $l['max']));

                continue;
            }
            $clean[$key] = $key === 'rigs' ? (int) round((float) $v) : round((float) $v, 2);
        }

        return [$clean, $errors];
    }

    /** @param  array<string, string|float|int|null>  $d */
    public function toEngine(array $d, int $advisorAnswers = 0): Decisions
    {
        $offsets = $this->data->baseOffsets();
        foreach (array_keys($offsets) as $k) {
            if (isset($d["off_$k"])) {
                $offsets[$k] = (float) $d["off_$k"];
            }
        }

        return new Decisions(
            rigs: (int) $d['rigs'],
            norway: (string) $d['norway'],
            brRun: (float) $d['br_run'],
            rotRun: (float) $d['rot_run'],
            rotPosture: (string) $d['rot_posture'],
            sgRequest: (float) $d['sg_request'],
            offsets: $offsets,
            tpMethod: (string) ($d['tp_method'] ?? 'market'),
            tpValue: isset($d['tp_value']) ? (float) $d['tp_value'] : null,
            advisorAnswers: $advisorAnswers,
        );
    }

    private static function fmt(float $v): string
    {
        return rtrim(rtrim(number_format($v, 1, '.', ''), '0'), '.');
    }
}
