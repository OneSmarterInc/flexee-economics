<?php

namespace App\Domain\Economics\Week12;

use Brick\Math\BigDecimal;
use Illuminate\Support\Facades\File;
use InvalidArgumentException;
use JsonException;

final readonly class Week12ReferencePackage
{
    public const MISSING_REASON = 'Authoritative Week 12 reference package is not available.';

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
        $packageRoot ??= base_path('halden-week12-data-package');
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
    public function inputs(): Week12EconomicInputs
    {
        if (! $this->available || $this->packageRoot === null || $this->version === null) {
            throw new InvalidArgumentException($this->unavailableReason ?? self::MISSING_REASON);
        }

        $provenance = $this->json('fixtures/provenance.json');

        return new Week12EconomicInputs(
            envelope: $this->keyedDecimals('data/envelope.csv', 'parameter', 'value'),
            buckets: $this->buckets(),
            projects: $this->projects(),
            carbonScenarios: $this->keyedDecimals('data/carbon_scenarios.csv', 'scenario', 'carbon_price'),
            demandScenarios: $this->keyedDecimals('data/demand_scenarios.csv', 'scenario', 'code'),
            workedExample: $this->keyedDecimals('data/worked_example_projects.csv', 'parameter', 'value'),
            golden: $this->json('fixtures/week12_golden.json'),
            sourceHashes: is_array($provenance['artifacts'] ?? null) ? $provenance['artifacts'] : [],
            packageVersion: $this->version,
        );
    }

    /**
     * @return array<string, array{floor: BigDecimal, ceiling: BigDecimal}>
     */
    private function buckets(): array
    {
        $buckets = [];

        foreach ($this->csv('data/buckets.csv') as $row) {
            $buckets[(string) $row['bucket']] = [
                'floor' => BigDecimal::of((string) $row['floor']),
                'ceiling' => BigDecimal::of((string) $row['ceiling']),
            ];
        }

        return $buckets;
    }

    /**
     * @return array<string, array{bucket: string, cost_musd: BigDecimal, npv_base: BigDecimal, carbon_sens: BigDecimal, demand_sens: BigDecimal}>
     */
    private function projects(): array
    {
        $projects = [];

        foreach ($this->csv('data/projects.csv') as $row) {
            $projects[(string) $row['project']] = [
                'bucket' => (string) $row['bucket'],
                'cost_musd' => BigDecimal::of((string) $row['cost_musd']),
                'npv_base' => BigDecimal::of((string) $row['npv_base']),
                'carbon_sens' => BigDecimal::of((string) $row['carbon_sens']),
                'demand_sens' => BigDecimal::of((string) $row['demand_sens']),
            ];
        }

        return $projects;
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
            throw new InvalidArgumentException("Week 12 package CSV [{$relativePath}] could not be opened.");
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
            throw new InvalidArgumentException("Week 12 package artifact [{$relativePath}] is missing.");
        }

        return $path;
    }
}
