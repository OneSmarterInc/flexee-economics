<?php

namespace App\Domain\Capital\Week6;

use Brick\Math\BigDecimal;
use Illuminate\Support\Facades\File;
use InvalidArgumentException;
use JsonException;

final readonly class Week6CapitalReferencePackage
{
    public const MISSING_REASON = 'Authoritative Week 6 reference package is not available.';

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
        $packageRoot ??= base_path('halden-week6-data-package');
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
    public function inputs(): Week6CapitalEconomicInputs
    {
        if (! $this->available || $this->packageRoot === null || $this->version === null) {
            throw new InvalidArgumentException($this->unavailableReason ?? self::MISSING_REASON);
        }

        $provenance = $this->json('fixtures/provenance.json');

        return new Week6CapitalEconomicInputs(
            projects: $this->projectCashFlows(),
            cohortSchedule: $this->cohortSchedule(),
            haircuts: $this->haircuts(),
            golden: $this->json('fixtures/week6_golden.json'),
            sourceHashes: is_array($provenance['artifacts'] ?? null) ? $provenance['artifacts'] : [],
            packageVersion: $this->version,
        );
    }

    /**
     * @return array<string, Week6ProjectCashFlows>
     */
    private function projectCashFlows(): array
    {
        $rows = $this->csv('data/project_cashflows.csv');
        $projects = [];

        foreach (array_keys($rows[0] ?? []) as $key) {
            if ($key === 'year') {
                continue;
            }

            $projects[$key] = new Week6ProjectCashFlows(
                key: $key,
                flows: array_map(
                    fn (array $row): BigDecimal => BigDecimal::of((string) $row[$key]),
                    $rows,
                ),
            );
        }

        return $projects;
    }

    /**
     * @return array<string, array{rate: BigDecimal, envelope_musd: BigDecimal}>
     */
    private function cohortSchedule(): array
    {
        $schedule = [];

        foreach ($this->csv('data/cohort_discount_schedule.csv') as $row) {
            $schedule[(string) $row['cohort_wk4_behavior']] = [
                'rate' => BigDecimal::of((string) $row['discount_rate']),
                'envelope_musd' => BigDecimal::of((string) $row['capital_envelope_musd']),
            ];
        }

        return $schedule;
    }

    /**
     * @return array<string, BigDecimal>
     */
    private function haircuts(): array
    {
        $haircuts = [];

        foreach ($this->csv('data/forecast_haircuts.csv') as $row) {
            $haircuts[(string) $row['project_type']] = BigDecimal::of((string) $row['actual_pct_of_forecast']);
        }

        return $haircuts;
    }

    /**
     * @return list<array<string, string>>
     */
    private function csv(string $relativePath): array
    {
        $path = $this->path($relativePath);
        $handle = fopen($path, 'rb');

        if ($handle === false) {
            throw new InvalidArgumentException("Week 6 package CSV [{$relativePath}] could not be opened.");
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
            throw new InvalidArgumentException("Week 6 package artifact [{$relativePath}] is missing.");
        }

        return $path;
    }
}
