<?php

namespace App\Domain\Economics\Week8;

use Brick\Math\BigDecimal;
use Illuminate\Support\Facades\File;
use InvalidArgumentException;
use JsonException;

final readonly class Week8ReferencePackage
{
    public const MISSING_REASON = 'Authoritative Week 8 reference package is not available.';

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
        $packageRoot ??= base_path('halden-week8-data-package');
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
    public function inputs(): Week8EconomicInputs
    {
        if (! $this->available || $this->packageRoot === null || $this->version === null) {
            throw new InvalidArgumentException($this->unavailableReason ?? self::MISSING_REASON);
        }

        $provenance = $this->json('fixtures/provenance.json');

        return new Week8EconomicInputs(
            scenarios: $this->scenarios('data/opec_scenarios.csv'),
            coefficients: $this->coefficients(),
            baseline: $this->baseline(),
            workedExampleScenarios: $this->scenarios('data/worked_example_prior.csv', hasResolvedWti: false),
            golden: $this->json('fixtures/week8_golden.json'),
            sourceHashes: is_array($provenance['artifacts'] ?? null) ? $provenance['artifacts'] : [],
            packageVersion: $this->version,
        );
    }

    /**
     * @return array<string, Week8Scenario>
     */
    private function scenarios(string $relativePath, bool $hasResolvedWti = true): array
    {
        $baselineWti = $this->baseline()['wti_pre'] ?? BigDecimal::zero();
        $scenarios = [];

        foreach ($this->csv($relativePath) as $row) {
            $key = (string) $row['scenario'];
            $deltaWti = BigDecimal::of((string) $row['delta_wti']);
            $wtiResolved = $hasResolvedWti
                ? BigDecimal::of((string) $row['wti_resolved'])
                : $baselineWti->plus($deltaWti);

            $scenarios[$key] = new Week8Scenario(
                key: $key,
                probability: BigDecimal::of((string) $row['probability']),
                wtiResolved: $wtiResolved,
                deltaWti: $deltaWti,
            );
        }

        return $scenarios;
    }

    /**
     * @return array<string, BigDecimal>
     */
    private function coefficients(): array
    {
        $coefficients = [];

        foreach ($this->csv('data/propagation_coefficients.csv') as $row) {
            $coefficients[(string) $row['channel']] = BigDecimal::of((string) $row['coefficient']);
        }

        return $coefficients;
    }

    /**
     * @return array<string, BigDecimal>
     */
    private function baseline(): array
    {
        $baseline = [];

        foreach ($this->csv('data/baseline_state.csv') as $row) {
            $baseline[(string) $row['parameter']] = BigDecimal::of((string) $row['value']);
        }

        return $baseline;
    }

    /**
     * @return list<array<string, string>>
     */
    private function csv(string $relativePath): array
    {
        $path = $this->path($relativePath);
        $handle = fopen($path, 'rb');

        if ($handle === false) {
            throw new InvalidArgumentException("Week 8 package CSV [{$relativePath}] could not be opened.");
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
            throw new InvalidArgumentException("Week 8 package artifact [{$relativePath}] is missing.");
        }

        return $path;
    }
}
