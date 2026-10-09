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
        ], self::csv("$root/data/market_path.csv"));

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
