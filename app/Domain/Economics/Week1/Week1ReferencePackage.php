<?php

namespace App\Domain\Economics\Week1;

use Brick\Math\BigDecimal;
use Illuminate\Support\Facades\File;
use InvalidArgumentException;
use JsonException;

final readonly class Week1ReferencePackage
{
    public const MISSING_REASON = 'Authoritative Week 1 reference package is not available.';

    private function __construct(
        private bool $available,
        private ?string $version,
        private ?string $unavailableReason,
        private ?string $packageRoot = null,
    ) {}

    public static function missing(): self
    {
        return new self(
            available: false,
            version: null,
            unavailableReason: self::MISSING_REASON,
        );
    }

    /**
     * @throws JsonException
     */
    public static function fromRepository(?string $packageRoot = null): self
    {
        $packageRoot ??= base_path('halden-week1-data-package');
        $provenancePath = $packageRoot.DIRECTORY_SEPARATOR.'fixtures'.DIRECTORY_SEPARATOR.'provenance.json';

        if (! File::isDirectory($packageRoot) || ! File::isFile($provenancePath)) {
            return self::missing();
        }

        $provenance = json_decode((string) file_get_contents($provenancePath), true, 512, JSON_THROW_ON_ERROR);
        $version = is_array($provenance) && is_string($provenance['package_version'] ?? null)
            ? $provenance['package_version']
            : 'unknown';

        return new self(
            available: true,
            version: $version,
            unavailableReason: null,
            packageRoot: $packageRoot,
        );
    }

    public function isAvailable(): bool
    {
        return $this->available;
    }

    public function version(): ?string
    {
        return $this->version;
    }

    public function unavailableReason(): ?string
    {
        return $this->unavailableReason;
    }

    /**
     * @throws JsonException
     */
    public function inputs(): Week1EconomicInputs
    {
        if (! $this->available || $this->packageRoot === null || $this->version === null) {
            throw new InvalidArgumentException($this->unavailableReason ?? self::MISSING_REASON);
        }

        $provenance = $this->json('fixtures/provenance.json');

        return new Week1EconomicInputs(
            benchmarks: $this->keyedDecimals('data/benchmarks.csv', 'parameter', 'value', lowercaseKeys: true),
            upstreamAssets: $this->upstreamAssets(),
            refiningAssets: $this->refiningAssets(),
            rotterdamCostSplit: $this->keyedDecimals('data/rotterdam_cost_split.csv', 'parameter', 'value'),
            bookValues: $this->bookValues(),
            workedExample: $this->keyedDecimals('data/worked_example_assets.csv', 'parameter', 'value'),
            golden: $this->json('fixtures/week1_golden.json'),
            sourceHashes: is_array($provenance['artifacts'] ?? null) ? $provenance['artifacts'] : [],
            packageVersion: $this->version,
        );
    }

    /**
     * @return array<string, array<string, BigDecimal|string>>
     */
    private function upstreamAssets(): array
    {
        $assets = [];

        foreach ($this->csv('data/upstream_assets.csv') as $row) {
            $assets[(string) $row['asset']] = [
                'benchmark' => (string) $row['benchmark'],
                'differential' => BigDecimal::of((string) $row['differential']),
                'lifting' => BigDecimal::of((string) $row['lifting']),
                'logistics' => BigDecimal::of((string) $row['logistics']),
                'fiscal_type' => (string) $row['fiscal_type'],
                'fiscal_rate' => BigDecimal::of((string) $row['fiscal_rate']),
            ];
        }

        return $assets;
    }

    /**
     * @return array<string, array<string, BigDecimal>>
     */
    private function refiningAssets(): array
    {
        $assets = [];

        foreach ($this->csv('data/refining_assets.csv') as $row) {
            $assets[(string) $row['refinery']] = [
                'crack' => BigDecimal::of((string) $row['crack']),
                'complexity' => BigDecimal::of((string) $row['complexity']),
                'opex' => BigDecimal::of((string) $row['opex']),
                'halden_share' => BigDecimal::of((string) $row['halden_share']),
            ];
        }

        return $assets;
    }

    /**
     * @return array<string, array<string, BigDecimal>>
     */
    private function bookValues(): array
    {
        $values = [];

        foreach ($this->csv('data/book_values.csv') as $row) {
            $values[(string) $row['asset']] = [
                'book_value_musd' => BigDecimal::of((string) $row['book_value_musd']),
                'replacement_cost_musd' => BigDecimal::of((string) $row['replacement_cost_musd']),
                'reported_profit_musd' => BigDecimal::of((string) $row['reported_profit_musd']),
            ];
        }

        return $values;
    }

    /**
     * @return array<string, BigDecimal>
     */
    private function keyedDecimals(string $relativePath, string $keyColumn, string $valueColumn, bool $lowercaseKeys = false): array
    {
        $values = [];

        foreach ($this->csv($relativePath) as $row) {
            $key = (string) $row[$keyColumn];
            $values[$lowercaseKeys ? strtolower($key) : $key] = BigDecimal::of((string) $row[$valueColumn]);
        }

        return $values;
    }

    /**
     * @return list<array<string, string>>
     */
    private function csv(string $relativePath): array
    {
        $path = $this->path($relativePath);
        $handle = fopen($path, 'rb');

        if ($handle === false) {
            throw new InvalidArgumentException("Week 1 package CSV [{$relativePath}] could not be opened.");
        }

        $header = fgetcsv($handle);

        if ($header === false) {
            fclose($handle);

            return [];
        }

        $rows = [];

        while (($row = fgetcsv($handle)) !== false) {
            $combined = array_combine($header, $row);
            $rows[] = $combined;
        }

        fclose($handle);

        return $rows;
    }

    /**
     * @return array<string, mixed>
     *
     * @throws JsonException
     */
    private function json(string $relativePath): array
    {
        return json_decode((string) file_get_contents($this->path($relativePath)), true, 512, JSON_THROW_ON_ERROR);
    }

    private function path(string $relativePath): string
    {
        if ($this->packageRoot === null) {
            throw new InvalidArgumentException(self::MISSING_REASON);
        }

        $path = $this->packageRoot.DIRECTORY_SEPARATOR.str_replace('/', DIRECTORY_SEPARATOR, $relativePath);

        if (! File::isFile($path)) {
            throw new InvalidArgumentException("Week 1 package artifact [{$relativePath}] is missing.");
        }

        return $path;
    }
}
