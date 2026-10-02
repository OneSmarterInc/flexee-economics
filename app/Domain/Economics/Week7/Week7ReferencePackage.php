<?php

namespace App\Domain\Economics\Week7;

use Brick\Math\BigDecimal;
use Illuminate\Support\Facades\File;
use InvalidArgumentException;
use JsonException;

final readonly class Week7ReferencePackage
{
    public const DEFAULT_ROOT = 'halden-week7-data-package';

    public const MISSING_REASON = 'Authoritative Week 7 reference package is not available.';

    private function __construct(
        private bool $available,
        private ?string $version,
        private ?string $unavailableReason,
        private ?string $packageRoot = null,
    ) {}

    public static function missing(): self
    {
        return new self(false, null, self::MISSING_REASON);
    }

    /**
     * @throws JsonException
     */
    public static function fromRepository(?string $packageRoot = null): self
    {
        $packageRoot ??= base_path(self::DEFAULT_ROOT);
        $provenancePath = $packageRoot.DIRECTORY_SEPARATOR.'fixtures'.DIRECTORY_SEPARATOR.'provenance.json';

        if (! File::isDirectory($packageRoot) || ! File::isFile($provenancePath)) {
            return self::missing();
        }

        $provenance = json_decode((string) file_get_contents($provenancePath), true, 512, JSON_THROW_ON_ERROR);
        $version = is_string($provenance['package_version'] ?? null) ? $provenance['package_version'] : 'unknown';

        return new self(true, $version, null, $packageRoot);
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
    public function inputs(): Week7EconomicInputs
    {
        if (! $this->available || $this->packageRoot === null || $this->version === null) {
            throw new InvalidArgumentException($this->unavailableReason ?? self::MISSING_REASON);
        }

        $provenance = $this->json('fixtures/provenance.json');

        return new Week7EconomicInputs(
            clusters: $this->clusters(),
            rivalMove: $this->keyedDecimals('data/rival_move.csv'),
            capacityPayoffs: $this->capacityPayoffs(),
            windowParameters: $this->keyedDecimals('data/window3_params.csv'),
            cohortStates: $this->keyedDecimals('data/cohort_states.csv', 'state', 'aggression'),
            workedExample: $this->keyedDecimals('data/worked_example_markets.csv'),
            golden: $this->json('fixtures/week7_golden.json'),
            sourceHashes: is_array($provenance['artifacts'] ?? null) ? $provenance['artifacts'] : [],
            packageVersion: $this->version,
        );
    }

    /**
     * @return array<string, Week7Cluster>
     */
    private function clusters(): array
    {
        $clusters = [];

        foreach ($this->csv('data/clusters.csv') as $row) {
            $clusters[(string) $row['cluster']] = new Week7Cluster(
                key: (string) $row['cluster'],
                elasticity: BigDecimal::of((string) $row['elasticity']),
                passthrough: BigDecimal::of((string) $row['passthrough']),
                annualVolumeMgal: BigDecimal::of((string) $row['annual_volume_mgal']),
                core: (string) $row['core'] === 'yes',
            );
        }

        return $clusters;
    }

    /**
     * @return array<string, BigDecimal>
     */
    private function capacityPayoffs(): array
    {
        $payoffs = [];

        foreach ($this->csv('data/capacity_payoffs.csv') as $row) {
            $payoffs[(string) $row['cell_id']] = BigDecimal::of((string) $row['payoff_musd']);
        }

        return $payoffs;
    }

    /**
     * @return array<string, BigDecimal>
     */
    public function keyedDecimals(string $relativePath, string $keyColumn = 'parameter', string $valueColumn = 'value'): array
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
            throw new InvalidArgumentException("Week 7 package CSV [{$relativePath}] could not be opened.");
        }

        $header = fgetcsv($handle);
        $rows = [];

        while ($header !== false && ($row = fgetcsv($handle)) !== false) {
            $rows[] = array_combine($header, $row);
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
            throw new InvalidArgumentException("Week 7 package artifact [{$relativePath}] is missing.");
        }

        return $path;
    }
}
