<?php

namespace App\Domain\CohortFeedback;

use Brick\Math\BigDecimal;
use Illuminate\Support\Facades\File;
use InvalidArgumentException;
use JsonException;

final readonly class Window2CohortPackage
{
    public const DEFAULT_ROOT = 'halden-window2-cohort-addendum';

    /**
     * @param  array<string, string>  $artifacts
     */
    private function __construct(
        private string $packageRoot,
        private string $packageVersion,
        private array $artifacts,
    ) {}

    /**
     * @throws JsonException
     */
    public static function fromRepository(?string $packageRoot = null): self
    {
        $packageRoot ??= base_path(self::DEFAULT_ROOT);
        $provenancePath = $packageRoot.DIRECTORY_SEPARATOR.'fixtures'.DIRECTORY_SEPARATOR.'provenance.json';

        if (! File::isDirectory($packageRoot) || ! File::isFile($provenancePath)) {
            throw new InvalidArgumentException('Authoritative Window 2 cohort addendum package is not available.');
        }

        $provenance = json_decode((string) file_get_contents($provenancePath), true, 512, JSON_THROW_ON_ERROR);

        return new self(
            packageRoot: $packageRoot,
            packageVersion: is_string($provenance['package_version'] ?? null) ? $provenance['package_version'] : 'unknown',
            artifacts: is_array($provenance['artifacts'] ?? null) ? $provenance['artifacts'] : [],
        );
    }

    public function version(): string
    {
        return $this->packageVersion;
    }

    /**
     * @return array<string, string>
     */
    public function sourceHashes(): array
    {
        return $this->artifacts;
    }

    /**
     * @return array<string, array{expected: string, actual: string}>
     */
    public function validateProvenance(): array
    {
        $mismatches = [];

        foreach ($this->artifacts as $relativePath => $expectedHash) {
            $path = $this->path($relativePath);
            $actualHash = hash_file('sha256', $path);

            if ($actualHash === false) {
                throw new InvalidArgumentException("Window 2 package artifact [{$relativePath}] could not be hashed.");
            }

            if ($actualHash !== $expectedHash) {
                $mismatches[$relativePath] = [
                    'expected' => $expectedHash,
                    'actual' => $actualHash,
                ];
            }
        }

        return $mismatches;
    }

    /**
     * @return array<string, mixed>
     *
     * @throws JsonException
     */
    public function golden(): array
    {
        return $this->json('fixtures/window2_golden.json');
    }

    /**
     * @return array<string, BigDecimal>
     */
    public function parameters(): array
    {
        $parameters = [];

        foreach ($this->csv('data/window2_params.csv') as $row) {
            $parameters[(string) $row['parameter']] = BigDecimal::of((string) $row['value']);
        }

        return $parameters;
    }

    /**
     * @return array<string, BigDecimal>
     */
    public function batonRougeMarginInputs(): array
    {
        $parameters = [];

        foreach ($this->csv('data/baton_rouge_margin.csv') as $row) {
            $parameters[(string) $row['parameter']] = BigDecimal::of((string) $row['value']);
        }

        return $parameters;
    }

    /**
     * @return array<string, BigDecimal>
     */
    public function week8Link(): array
    {
        $parameters = [];

        foreach ($this->csv('data/week8_link.csv') as $row) {
            $parameters[(string) $row['parameter']] = BigDecimal::of((string) $row['value']);
        }

        return $parameters;
    }

    /**
     * @return array<string, BigDecimal>
     */
    public function workedExample(): array
    {
        $parameters = [];

        foreach ($this->csv('data/worked_example_window.csv') as $row) {
            $parameters[(string) $row['parameter']] = BigDecimal::of((string) $row['value']);
        }

        return $parameters;
    }

    /**
     * @return array<string, BigDecimal>
     */
    public function cohortStates(): array
    {
        $states = [];

        foreach ($this->csv('data/cohort_states.csv') as $row) {
            $states[(string) $row['state']] = BigDecimal::of((string) $row['share']);
        }

        return $states;
    }

    /**
     * @return list<array<string, string>>
     */
    private function csv(string $relativePath): array
    {
        $handle = fopen($this->path($relativePath), 'rb');

        if ($handle === false) {
            throw new InvalidArgumentException("Window 2 package CSV [{$relativePath}] could not be opened.");
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
        $path = $this->packageRoot.DIRECTORY_SEPARATOR.str_replace('/', DIRECTORY_SEPARATOR, $relativePath);

        if (! File::isFile($path)) {
            throw new InvalidArgumentException("Window 2 package artifact [{$relativePath}] is missing.");
        }

        return $path;
    }
}
