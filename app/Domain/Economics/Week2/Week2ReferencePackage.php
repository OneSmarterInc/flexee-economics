<?php

namespace App\Domain\Economics\Week2;

use Brick\Math\BigDecimal;
use Illuminate\Support\Facades\File;
use InvalidArgumentException;
use JsonException;

final readonly class Week2ReferencePackage
{
    public const DEFAULT_ROOT = 'halden-week2-data-package';

    public const MISSING_REASON = 'Authoritative Week 2 reference package is not available.';

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
    public function inputs(): Week2EconomicInputs
    {
        if (! $this->available || $this->packageRoot === null || $this->version === null) {
            throw new InvalidArgumentException($this->unavailableReason ?? self::MISSING_REASON);
        }

        $provenance = $this->json('fixtures/provenance.json');

        return new Week2EconomicInputs(
            clusters: $this->clusters(),
            pricingParameters: $this->keyedDecimals('data/pricing_params.csv'),
            fuelNonfuel: $this->fuelNonfuel(),
            workedExampleObservations: $this->observations('data/worked_example_cluster.csv'),
            workedExampleParameters: $this->keyedDecimals('data/worked_example_params.csv'),
            golden: $this->json('fixtures/week2_golden.json'),
            sourceHashes: is_array($provenance['artifacts'] ?? null) ? $provenance['artifacts'] : [],
            packageVersion: $this->version,
        );
    }

    /**
     * @return array<string, Week2Cluster>
     */
    private function clusters(): array
    {
        $observations = [];

        foreach ($this->csv('data/cluster_price_volume.csv') as $row) {
            $clusterKey = (string) $row['cluster'];
            $observations[$clusterKey] ??= [];
            $observations[$clusterKey][] = [
                'week' => (int) $row['week'],
                'ln_price' => BigDecimal::of((string) $row['ln_price']),
                'ln_volume' => BigDecimal::of((string) $row['ln_volume']),
            ];
        }

        $clusters = [];

        foreach ($this->csv('data/cluster_params.csv') as $row) {
            $key = (string) $row['cluster'];
            $clusters[$key] = new Week2Cluster(
                key: $key,
                market: (string) $row['market'],
                designElasticity: BigDecimal::of((string) $row['design_elasticity']),
                passthrough: BigDecimal::of((string) $row['passthrough']),
                volumeShare: BigDecimal::of((string) $row['vol_share']),
                observations: $observations[$key] ?? [],
            );
        }

        return $clusters;
    }

    /**
     * @return array<string, BigDecimal>
     */
    private function keyedDecimals(string $relativePath, string $keyColumn = 'parameter', string $valueColumn = 'value'): array
    {
        $values = [];

        foreach ($this->csv($relativePath) as $row) {
            $values[(string) $row[$keyColumn]] = BigDecimal::of((string) $row[$valueColumn]);
        }

        return $values;
    }

    /**
     * @return array<string, array{fuel_margin_per_gal: BigDecimal, nonfuel_per_fill: BigDecimal}>
     */
    private function fuelNonfuel(): array
    {
        $values = [];

        foreach ($this->csv('data/fuel_nonfuel.csv') as $row) {
            $values[(string) $row['network']] = [
                'fuel_margin_per_gal' => BigDecimal::of((string) $row['fuel_margin_per_gal']),
                'nonfuel_per_fill' => BigDecimal::of((string) $row['nonfuel_per_fill']),
            ];
        }

        return $values;
    }

    /**
     * @return list<array{week: int, ln_price: BigDecimal, ln_volume: BigDecimal}>
     */
    private function observations(string $relativePath): array
    {
        return array_map(fn (array $row): array => [
            'week' => (int) $row['week'],
            'ln_price' => BigDecimal::of((string) $row['ln_price']),
            'ln_volume' => BigDecimal::of((string) $row['ln_volume']),
        ], $this->csv($relativePath));
    }

    /**
     * @return list<array<string, string>>
     */
    private function csv(string $relativePath): array
    {
        $path = $this->path($relativePath);
        $handle = fopen($path, 'rb');

        if ($handle === false) {
            throw new InvalidArgumentException("Week 2 package CSV [{$relativePath}] could not be opened.");
        }

        $header = fgetcsv($handle);
        $rows = [];

        while ($header !== false && ($row = fgetcsv($handle)) !== false) {
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
            throw new InvalidArgumentException("Week 2 package artifact [{$relativePath}] is missing.");
        }

        return $path;
    }
}
