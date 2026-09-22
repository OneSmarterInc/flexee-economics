<?php

namespace App\Domain\Economics\Week4;

use RuntimeException;

final class Week4ReferencePackage
{
    public function __construct(
        private readonly ?string $packageRoot = null,
    ) {}

    public function inputs(string $marginalVintage = '2024'): Week4EconomicInputs
    {
        return Week4EconomicInputs::fromReferenceData(
            costConstants: $this->costConstants(),
            liftingCostsByVintage: $this->liftingCostsByVintage(),
            segmentTargets: $this->segmentTargets(),
            marginalVintage: $marginalVintage,
        );
    }

    /**
     * @return array{
     *     source: string,
     *     marginal_vintage: string,
     *     cost_constants: array<string, string>,
     *     lifting_costs_by_vintage: array<string, string>,
     *     segment_targets: array<string, string>
     * }
     */
    public function inputSnapshot(string $marginalVintage = '2024'): array
    {
        return [
            'source' => $this->root(),
            'marginal_vintage' => $marginalVintage,
            'cost_constants' => $this->costConstants(),
            'lifting_costs_by_vintage' => $this->liftingCostsByVintage(),
            'segment_targets' => $this->segmentTargets(),
        ];
    }

    /**
     * @return array<string, string>
     */
    private function costConstants(): array
    {
        return $this->parameterCsv('data/cost_constants.csv');
    }

    /**
     * @return array<string, string>
     */
    private function liftingCostsByVintage(): array
    {
        $costs = [];

        foreach ($this->csvRows('data/permian_lifting.csv') as $row) {
            $costs[$row['vintage']] = $row['cash_lifting_cost_usd_bbl'];
        }

        return $costs;
    }

    /**
     * @return array<string, string>
     */
    private function segmentTargets(): array
    {
        $targets = [];

        foreach ($this->csvRows('data/segment_comp.csv') as $row) {
            if ($row['target_margin_usd_bbl'] !== '') {
                $targets[$row['segment']] = $row['target_margin_usd_bbl'];
            }
        }

        return $targets;
    }

    /**
     * @return array<string, string>
     */
    private function parameterCsv(string $relativePath): array
    {
        $values = [];

        foreach ($this->csvRows($relativePath) as $row) {
            $values[$row['parameter']] = $row['value'];
        }

        return $values;
    }

    /**
     * @return list<array<string, string>>
     */
    private function csvRows(string $relativePath): array
    {
        $path = $this->root().DIRECTORY_SEPARATOR.str_replace('/', DIRECTORY_SEPARATOR, $relativePath);
        $handle = fopen($path, 'rb');

        if ($handle === false) {
            throw new RuntimeException("Unable to read Week 4 reference package file [{$relativePath}].");
        }

        $headers = fgetcsv($handle);
        if (! is_array($headers)) {
            fclose($handle);

            throw new RuntimeException("Week 4 reference package file [{$relativePath}] has no header row.");
        }

        $rows = [];
        while (($values = fgetcsv($handle)) !== false) {
            $row = [];
            foreach ($headers as $index => $header) {
                $row[(string) $header] = (string) ($values[$index] ?? '');
            }
            $rows[] = $row;
        }

        fclose($handle);

        return $rows;
    }

    private function root(): string
    {
        return $this->packageRoot ?? base_path('halden-week4-data-package');
    }
}
