<?php

namespace App\Domain\Economics\Week10;

use Brick\Math\BigDecimal;
use Illuminate\Support\Facades\File;
use InvalidArgumentException;
use JsonException;

final readonly class Week10ReferencePackage
{
    public const MISSING_REASON = 'Authoritative Week 10 reference package is not available.';

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
        $packageRoot ??= base_path('halden-week10-data-package');
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
    public function inputs(): Week10ReferenceInputs
    {
        if (! $this->available || $this->packageRoot === null || $this->version === null) {
            throw new InvalidArgumentException($this->unavailableReason ?? self::MISSING_REASON);
        }

        $provenance = $this->json('fixtures/provenance.json');

        return new Week10ReferenceInputs(
            productElasticities: $this->productElasticities(),
            recessionParameters: $this->keyedDecimals('data/recession_params.csv', 'parameter', 'value'),
            refineryYields: $this->refineryYields(),
            bindingRules: $this->keyedDecimals('data/binding_rules.csv', 'parameter', 'value'),
            teamPriorStates: $this->teamPriorStates(),
            golden: $this->json('fixtures/week10_golden.json'),
            sourceHashes: is_array($provenance['artifacts'] ?? null) ? $provenance['artifacts'] : [],
            runtimeDependencies: self::runtimeDependencies(),
            decisionStructure: self::decisionStructure(),
            packageVersion: $this->version,
        );
    }

    /**
     * Week 10 consumes prior simulation state. The package fixture rows are regression references, not runtime team data.
     *
     * @return array<string, array<string, string>>
     */
    public static function runtimeDependencies(): array
    {
        return [
            'cancellable_capex_musd' => [
                'source_week' => '6',
                'source' => 'capital_allocation_evaluation',
                'purpose' => 'Determines whether capital flexibility is available during the recession.',
            ],
            'crude_hedge_coverage' => [
                'source_week' => '5',
                'source' => 'hedge_mandate_or_hedge_position',
                'purpose' => 'Determines whether crude exposure is protected as prices soften.',
            ],
            'br_reported_margin_strong' => [
                'source_week' => '4',
                'source' => 'transfer_price_resolution_or_consequence',
                'purpose' => 'Determines whether Delacroix has reported-margin cover to resist Baton Rouge run cuts.',
            ],
            'straits_pacific_standing' => [
                'source_week' => 'standing_history',
                'source' => 'standing_state',
                'purpose' => 'Determines whether Singapore operational flexibility is available.',
            ],
            'cash_cushion_musd' => [
                'source_week' => '8',
                'source' => 'week8_economic_evaluation_or_cash_position_state',
                'purpose' => 'Determines whether the recession response is strategic or liquidity-forced.',
            ],
        ];
    }

    /**
     * @return list<array<string, string>>
     */
    public static function decisionStructure(): array
    {
        return [
            [
                'key' => 'run_rate_baton_rouge_pct',
                'type' => 'decimal',
                'source' => 'student_decision',
                'description' => 'Baton Rouge run-rate response to product-mix demand hit.',
            ],
            [
                'key' => 'run_rate_rotterdam_pct',
                'type' => 'decimal',
                'source' => 'student_decision',
                'description' => 'Rotterdam run-rate response to product-mix demand hit.',
            ],
            [
                'key' => 'run_rate_singapore_pct',
                'type' => 'decimal',
                'source' => 'student_decision',
                'description' => 'Singapore run-rate response subject to Straits Pacific flexibility.',
            ],
            [
                'key' => 'capital_response',
                'type' => 'structured_selection',
                'source' => 'student_decision',
                'description' => 'Capital cancellations or deferrals subject to inherited cancellability.',
            ],
            [
                'key' => 'hedge_response',
                'type' => 'structured_selection',
                'source' => 'student_decision',
                'description' => 'Hedge action subject to inherited mandate and crude coverage.',
            ],
            [
                'key' => 'working_capital_release_musd',
                'type' => 'decimal',
                'source' => 'student_decision',
                'description' => 'Cash release from inventory and working-capital actions.',
            ],
            [
                'key' => 'binding_constraint_explanation',
                'type' => 'long_text',
                'source' => 'memo_or_decision_rationale',
                'description' => 'Student explanation of which earlier decision created the binding constraint.',
            ],
        ];
    }

    /**
     * @return array<string, array{income_elasticity: BigDecimal, demand_share_blended: BigDecimal}>
     */
    private function productElasticities(): array
    {
        $products = [];

        foreach ($this->csv('data/product_elasticities.csv') as $row) {
            $product = (string) $row['product'];
            $products[$product] = [
                'income_elasticity' => BigDecimal::of((string) $row['income_elasticity']),
                'demand_share_blended' => BigDecimal::of((string) $row['demand_share_blended']),
            ];
        }

        return $products;
    }

    /**
     * @return array<string, array<string, BigDecimal>>
     */
    private function refineryYields(): array
    {
        $refineries = [];

        foreach ($this->csv('data/refinery_yields.csv') as $row) {
            $refinery = (string) $row['refinery'];
            $refineries[$refinery] = [
                'gasoline' => BigDecimal::of((string) $row['gasoline']),
                'diesel' => BigDecimal::of((string) $row['diesel']),
                'jet' => BigDecimal::of((string) $row['jet']),
                'other' => BigDecimal::of((string) $row['other']),
            ];
        }

        return $refineries;
    }

    /**
     * @return array<string, array<string, mixed>>
     */
    private function teamPriorStates(): array
    {
        $states = [];

        foreach ($this->csv('data/team_prior_state.csv') as $row) {
            $team = (string) $row['team'];
            $states[$team] = [
                'cancellable_capex_musd' => BigDecimal::of((string) $row['cancellable_capex_musd']),
                'crude_hedge_coverage' => BigDecimal::of((string) $row['crude_hedge_coverage']),
                'br_reported_margin_strong' => (bool) ((int) $row['br_reported_margin_strong']),
                'straits_pacific_standing' => (string) $row['straits_pacific_standing'],
                'cash_cushion_musd' => BigDecimal::of((string) $row['cash_cushion_musd']),
                'origin_note' => (string) $row['origin_note'],
            ];
        }

        return $states;
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
            throw new InvalidArgumentException("Week 10 package CSV [{$relativePath}] could not be opened.");
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
            throw new InvalidArgumentException("Week 10 package artifact [{$relativePath}] is missing.");
        }

        return $path;
    }
}
