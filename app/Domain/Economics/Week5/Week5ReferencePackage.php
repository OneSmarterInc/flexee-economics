<?php

namespace App\Domain\Economics\Week5;

use Brick\Math\BigDecimal;
use Illuminate\Support\Facades\File;
use InvalidArgumentException;
use JsonException;

final readonly class Week5ReferencePackage
{
    public const MISSING_REASON = 'Authoritative Week 5 reference package is not available.';

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
        $packageRoot ??= base_path('halden-week5-data-package');
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
    public function inputs(): Week5EconomicInputs
    {
        if (! $this->available || $this->packageRoot === null || $this->version === null) {
            throw new InvalidArgumentException($this->unavailableReason ?? self::MISSING_REASON);
        }

        $provenance = $this->json('fixtures/provenance.json');

        return new Week5EconomicInputs(
            fxRates: $this->fxRates(),
            entityFlows: $this->entityFlows(),
            norwayUnitCosts: $this->keyedDecimals('data/norway_unit_cost.csv', 'parameter', 'value'),
            existingHedges: $this->hedges(),
            forwardRates: $this->keyedDecimals('data/forwards_options.csv', 'pair', 'forward_1y'),
            collarPremiums: $this->keyedDecimals('data/forwards_options.csv', 'pair', 'collar_premium_pct'),
            workedExampleParameters: $this->keyedDecimals('data/worked_example_entity.csv', 'parameter', 'value'),
            golden: $this->json('fixtures/week5_golden.json'),
            sourceHashes: is_array($provenance['artifacts'] ?? null) ? $provenance['artifacts'] : [],
            packageVersion: $this->version,
        );
    }

    /**
     * @return array<string, Week5FxRate>
     */
    private function fxRates(): array
    {
        $rates = [];

        foreach ($this->csv('data/fx_shock.csv') as $row) {
            $pair = (string) $row['pair'];
            $rates[$pair] = new Week5FxRate(
                pair: $pair,
                pre: BigDecimal::of((string) $row['pre']),
                post: BigDecimal::of((string) $row['post']),
            );
        }

        return $rates;
    }

    /**
     * @return array<string, Week5EntityFlow>
     */
    private function entityFlows(): array
    {
        $flows = [];

        foreach ($this->csv('data/entity_flows.csv') as $row) {
            $flowId = (string) $row['flow_id'];
            $flows[$flowId] = new Week5EntityFlow(
                flowId: $flowId,
                entity: (string) $row['entity'],
                currency: (string) $row['currency'],
                annualMusdPre: BigDecimal::of((string) $row['annual_musd_pre']),
            );
        }

        return $flows;
    }

    /**
     * @return array<string, Week5Hedge>
     */
    private function hedges(): array
    {
        $hedges = [];

        foreach ($this->csv('data/existing_hedges.csv') as $row) {
            $hedgeId = (string) $row['hedge_id'];
            $hedges[$hedgeId] = new Week5Hedge(
                hedgeId: $hedgeId,
                pair: (string) $row['pair'],
                direction: (string) $row['direction'],
                notionalMusd: BigDecimal::of((string) $row['notional_musd']),
                maturityMonths: BigDecimal::of((string) $row['maturity_months']),
            );
        }

        return $hedges;
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
            throw new InvalidArgumentException("Week 5 package CSV [{$relativePath}] could not be opened.");
        }

        $header = fgetcsv($handle);

        if ($header === false) {
            fclose($handle);

            return [];
        }

        $rows = [];

        while (($row = fgetcsv($handle)) !== false) {
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
            throw new InvalidArgumentException("Week 5 package artifact [{$relativePath}] is missing.");
        }

        return $path;
    }
}
