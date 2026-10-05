<?php

namespace App\Domain\Consequences;

use Illuminate\Support\Facades\File;
use InvalidArgumentException;

final class KpiConsequenceReferencePackage
{
    public const PACKAGE_ROOT = 'halden-kpi-consequence-package';

    public const MISSING_REASON = 'halden-kpi-consequence-package is not available.';

    /**
     * @param  array<string, mixed>|null  $provenance
     */
    private function __construct(
        private readonly string $root,
        private readonly ?array $provenance,
        private readonly ?string $unavailableReason = null,
    ) {}

    public static function fromRepository(?string $root = null): self
    {
        $root ??= base_path(self::PACKAGE_ROOT);

        if (! is_dir($root)) {
            return new self($root, null, self::MISSING_REASON);
        }

        $provenancePath = $root.DIRECTORY_SEPARATOR.'fixtures'.DIRECTORY_SEPARATOR.'provenance.json';

        if (! is_file($provenancePath)) {
            return new self($root, null, 'KPI/consequence provenance file is missing.');
        }

        $provenance = json_decode((string) file_get_contents($provenancePath), true);

        if (! is_array($provenance)) {
            return new self($root, null, 'KPI/consequence provenance file is invalid.');
        }

        return new self($root, $provenance);
    }

    public function isAvailable(): bool
    {
        return $this->unavailableReason === null;
    }

    public function unavailableReason(): ?string
    {
        return $this->unavailableReason;
    }

    public function version(): string
    {
        return (string) ($this->provenance['package_version'] ?? 'unknown');
    }

    /**
     * @return array<string, string>
     */
    public function sourceHashes(): array
    {
        return is_array($this->provenance['artifacts'] ?? null)
            ? array_map('strval', $this->provenance['artifacts'])
            : [];
    }

    /**
     * @return list<array<string, string>>
     */
    public function consequenceCatalog(): array
    {
        return $this->csvRows('data/consequence_catalog.csv');
    }

    /**
     * @return list<array<string, string>>
     */
    public function consequenceResolutionFixture(): array
    {
        return $this->csvRows('data/consequence_resolution.csv');
    }

    /**
     * @return list<array<string, string>>
     */
    public function openingState(): array
    {
        return $this->csvRows('data/state_opening.csv');
    }

    /**
     * @return list<array<string, string>>
     */
    public function kpiDefinitions(): array
    {
        return $this->csvRows('data/kpi_definitions.csv');
    }

    /**
     * @return list<array<string, string>>
     */
    public function kpiRules(): array
    {
        return $this->csvRows('data/kpi_rules.csv');
    }

    /**
     * @return list<array<string, string>>
     */
    public function inputDictionary(): array
    {
        return $this->csvRows('data/input_dictionary.csv');
    }

    /**
     * @return list<array<string, string>>
     */
    public function referenceTeamInputs(): array
    {
        return $this->csvRows('data/reference_team_inputs.csv');
    }

    /**
     * @return list<array<string, string>>
     */
    public function referenceTeamDecisions(): array
    {
        return $this->csvRows('data/reference_team_decisions.csv');
    }

    /**
     * @return list<array<string, string>>
     */
    public function workedExampleNormalization(): array
    {
        return $this->csvRows('data/worked_example_normalization.csv');
    }

    /**
     * @return array<string, mixed>
     */
    public function goldenFixture(): array
    {
        $path = $this->root.DIRECTORY_SEPARATOR.'fixtures'.DIRECTORY_SEPARATOR.'kpi_consequence_golden.json';

        if (! is_file($path)) {
            throw new InvalidArgumentException('KPI/consequence golden fixture is missing.');
        }

        $fixture = json_decode((string) file_get_contents($path), true);

        if (! is_array($fixture)) {
            throw new InvalidArgumentException('KPI/consequence golden fixture is invalid.');
        }

        return $fixture;
    }

    /**
     * @return array<string, string>
     */
    public function catalogRow(string $key): array
    {
        foreach ($this->consequenceCatalog() as $row) {
            if (($row['key'] ?? '') === $key) {
                return $row;
            }
        }

        throw new InvalidArgumentException("KPI/consequence catalog row [{$key}] is missing.");
    }

    /**
     * @return list<array<string, string>>
     */
    private function csvRows(string $relativePath): array
    {
        $path = $this->root.DIRECTORY_SEPARATOR.str_replace('/', DIRECTORY_SEPARATOR, $relativePath);

        if (! is_file($path)) {
            throw new InvalidArgumentException("KPI/consequence package file [{$relativePath}] is missing.");
        }

        $handle = fopen($path, 'r');

        if ($handle === false) {
            throw new InvalidArgumentException("KPI/consequence package file [{$relativePath}] cannot be opened.");
        }

        $header = fgetcsv($handle);

        if (! is_array($header)) {
            fclose($handle);
            throw new InvalidArgumentException("KPI/consequence package file [{$relativePath}] has no header.");
        }

        $rows = [];

        while (($values = fgetcsv($handle)) !== false) {
            $row = [];

            foreach ($header as $index => $key) {
                $row[(string) $key] = (string) ($values[$index] ?? '');
            }

            $rows[] = $row;
        }

        fclose($handle);

        return $rows;
    }

    public function validateProvenance(): void
    {
        foreach ($this->sourceHashes() as $relativePath => $expectedHash) {
            $path = $this->root.DIRECTORY_SEPARATOR.str_replace('/', DIRECTORY_SEPARATOR, (string) $relativePath);

            if (! File::isFile($path)) {
                throw new InvalidArgumentException("KPI/consequence package artifact [{$relativePath}] is missing.");
            }

            $actualHash = strtolower(hash_file('sha256', $path) ?: '');

            if ($actualHash !== strtolower($expectedHash)) {
                throw new InvalidArgumentException("KPI/consequence package artifact [{$relativePath}] hash mismatch.");
            }
        }
    }
}
