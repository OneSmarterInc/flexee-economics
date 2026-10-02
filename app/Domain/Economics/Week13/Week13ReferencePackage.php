<?php

namespace App\Domain\Economics\Week13;

use Brick\Math\BigDecimal;
use Illuminate\Support\Facades\File;
use InvalidArgumentException;
use JsonException;

final readonly class Week13ReferencePackage
{
    public const MISSING_REASON = 'Authoritative Week 13 reference package is not available.';

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
        $packageRoot ??= base_path('halden-week13-data-package');
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
    public function inputs(): Week13EconomicInputs
    {
        if (! $this->available || $this->packageRoot === null || $this->version === null) {
            throw new InvalidArgumentException($this->unavailableReason ?? self::MISSING_REASON);
        }

        $provenance = $this->json('fixtures/provenance.json');

        return new Week13EconomicInputs(
            norwayUnion: $this->keyedDecimals('data/norway_union.csv', 'parameter', 'value'),
            permianLabor: $this->keyedDecimals('data/permian_labor.csv', 'parameter', 'value'),
            turnaround: $this->keyedDecimals('data/turnaround.csv', 'parameter', 'value'),
            wageBenchmarks: $this->wageBenchmarks(),
            workedExample: $this->keyedDecimals('data/worked_example_labor.csv', 'parameter', 'value'),
            golden: $this->json('fixtures/week13_golden.json'),
            sourceHashes: is_array($provenance['artifacts'] ?? null) ? $provenance['artifacts'] : [],
            packageVersion: $this->version,
        );
    }

    /**
     * @return array<string, array{structure: string, benchmark_wage_k: BigDecimal}>
     */
    private function wageBenchmarks(): array
    {
        $benchmarks = [];

        foreach ($this->csv('data/wage_benchmarks.csv') as $row) {
            $benchmarks[(string) $row['market']] = [
                'structure' => (string) $row['structure'],
                'benchmark_wage_k' => BigDecimal::of((string) $row['benchmark_wage_k']),
            ];
        }

        return $benchmarks;
    }

    /**
     * @return array<string, BigDecimal>
     */
    private function keyedDecimals(string $relativePath, string $keyColumn, string $valueColumn): array
    {
        $values = [];

        foreach ($this->csv($relativePath) as $row) {
            $values[(string) $row[$keyColumn]] = BigDecimal::of((string) $row[$valueColumn]);
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
            throw new InvalidArgumentException("Week 13 package CSV [{$relativePath}] could not be opened.");
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
            throw new InvalidArgumentException("Week 13 package artifact [{$relativePath}] is missing.");
        }

        return $path;
    }
}
