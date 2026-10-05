<?php

namespace Tests\Feature\Economics;

use App\Domain\Economics\Week4\Week4EconomicEngine;
use App\Domain\Economics\Week4\Week4EconomicInputs;
use Brick\Math\RoundingMode;
use Tests\TestCase;

class Week4EconomicEngineTest extends TestCase
{
    public function test_delivered_cost_and_anchor_prices_match_reference_package(): void
    {
        $engine = new Week4EconomicEngine;
        $inputs = $this->currentInputs();
        $expected = $this->jsonFixture('expected/week4_reference.json');

        $this->assertSame(
            $expected['outputs']['delivered_marginal_cost'],
            (string) $engine->deliveredMarginalCost($inputs)->toScale(2, RoundingMode::Unnecessary),
        );

        $this->assertSame(
            $expected['outputs']['transfer_prices'],
            $engine->transferPrices($inputs)->toPackageArray(),
        );
    }

    public function test_segment_splits_and_compensation_deltas_match_reference_package(): void
    {
        $engine = new Week4EconomicEngine;
        $expected = $this->jsonFixture('expected/week4_reference.json');

        $actual = [];
        foreach ($engine->calculateReferenceAnchors($this->currentInputs()) as $key => $result) {
            $actual[$key] = $result->toPackageSegmentArray();
        }

        $this->assertSame($expected['outputs']['segment_splits'], $actual);
    }

    public function test_integrated_margin_is_invariant_for_reference_and_custom_transfer_prices(): void
    {
        $engine = new Week4EconomicEngine;
        $inputs = $this->currentInputs();
        $expected = $this->jsonFixture('expected/week4_reference.json')['outputs']['integrated_margin'];

        foreach (['18.70', '20.00', '46.20', '50.00', '73.70'] as $transferPrice) {
            $result = $engine->calculate($inputs, $transferPrice);

            $this->assertSame($expected, $result->money($result->integratedMargin));
            $this->assertSame(
                $expected,
                $result->money($result->upstreamMargin->plus($result->refiningMargin)),
                "Integrated split changed for transfer price {$transferPrice}.",
            );
        }
    }

    public function test_geneva_arbitrage_matches_reference_package_without_period_conversion(): void
    {
        $engine = new Week4EconomicEngine;
        $expected = $this->jsonFixture('expected/week4_reference.json');

        $this->assertSame(
            $expected['outputs']['geneva_arbitrage'],
            $engine->genevaArbitrageAtMidpoint($this->currentInputs())->toPackageArray(),
        );
    }

    public function test_runtime_geneva_arbitrage_uses_transfer_price_bands(): void
    {
        $engine = new Week4EconomicEngine;
        $inputs = $this->currentInputs();

        foreach ([
            '16.83' => '0',
            '18.70' => '0',
            '20.57' => '0',
            '46.20' => '9.625',
            '66.33' => '0',
            '73.70' => '0',
            '81.07' => '0',
            '90.00' => '0',
        ] as $transferPrice => $capture) {
            $this->assertSame(
                $capture,
                $engine->genevaArbitrageForTransferPrice($inputs, $transferPrice)->toPackageArray()['capture_per_bbl'],
                "Unexpected Geneva capture for transfer price {$transferPrice}.",
            );
        }
    }

    public function test_current_outputs_match_week4_reference_json(): void
    {
        $engine = new Week4EconomicEngine;
        $inputs = $this->currentInputs();
        $expected = $this->jsonFixture('expected/week4_reference.json');

        $actual = [
            'realized_wellhead' => (string) $engine->realizedWellheadPrice($inputs)->toScale(2, RoundingMode::Unnecessary),
            'product_slate_value' => (string) $engine->productSlateValue($inputs)->toScale(2, RoundingMode::Unnecessary),
            'delivered_marginal_cost' => (string) $engine->deliveredMarginalCost($inputs)->toScale(2, RoundingMode::Unnecessary),
            'integrated_margin' => (string) $engine->integratedMargin($inputs)->toScale(2, RoundingMode::Unnecessary),
            'transfer_prices' => $engine->transferPrices($inputs)->toPackageArray(),
            'segment_splits' => array_map(
                fn ($result) => $result->toPackageSegmentArray(),
                $engine->calculateReferenceAnchors($inputs),
            ),
            'geneva_arbitrage' => $engine->genevaArbitrageAtMidpoint($inputs)->toPackageArray(),
        ];

        $this->assertSame($expected['outputs'], $actual);
    }

    public function test_worked_example_outputs_match_reference_package(): void
    {
        $engine = new Week4EconomicEngine;
        $inputs = $this->workedExampleInputs();
        $expected = $this->jsonFixture('expected/worked_example.json')['outputs'];

        $market = $engine->calculate($inputs, $expected['transfer_prices']['market']);
        $marginal = $engine->calculate($inputs, $expected['transfer_prices']['marginal_cost']);

        $this->assertSame($expected['realized_wellhead'], (string) $engine->realizedWellheadPrice($inputs)->toScale(2, RoundingMode::Unnecessary));
        $this->assertSame($expected['product_slate_value'], (string) $engine->productSlateValue($inputs)->toScale(2, RoundingMode::Unnecessary));
        $this->assertSame($expected['integrated_margin'], (string) $engine->integratedMargin($inputs)->toScale(2, RoundingMode::Unnecessary));
        $this->assertSame($expected['segment_splits']['market'], [
            'upstream_margin' => $market->money($market->upstreamMargin),
            'refining_margin' => $market->money($market->refiningMargin),
            'integrated_margin' => $market->money($market->integratedMargin),
        ]);
        $this->assertSame($expected['segment_splits']['marginal_cost'], [
            'upstream_margin' => $marginal->money($marginal->upstreamMargin),
            'refining_margin' => $marginal->money($marginal->refiningMargin),
            'integrated_margin' => $marginal->money($marginal->integratedMargin),
        ]);
    }

    private function currentInputs(): Week4EconomicInputs
    {
        return Week4EconomicInputs::fromReferenceData(
            costConstants: $this->costConstants(),
            liftingCostsByVintage: $this->liftingCostsByVintage(),
            segmentTargets: $this->segmentTargets(),
        );
    }

    private function workedExampleInputs(): Week4EconomicInputs
    {
        $constants = array_merge($this->costConstants(), $this->workedExampleConstants());
        $constants['wti'] = $constants['wti_prior'];

        return Week4EconomicInputs::fromReferenceData(
            costConstants: $constants,
            liftingCostsByVintage: $this->liftingCostsByVintage(),
            segmentTargets: $this->segmentTargets(),
        );
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
    private function workedExampleConstants(): array
    {
        return $this->parameterCsv('data/worked_example_prior.csv');
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
        $path = $this->packagePath($relativePath);
        $handle = fopen($path, 'rb');

        $this->assertIsResource($handle);

        /** @var list<string>|false $headers */
        $headers = fgetcsv($handle);
        $this->assertIsArray($headers);

        $rows = [];
        while (($values = fgetcsv($handle)) !== false) {
            $row = [];
            foreach ($headers as $index => $header) {
                $row[$header] = (string) ($values[$index] ?? '');
            }
            $rows[] = $row;
        }

        fclose($handle);

        return $rows;
    }

    /**
     * @return array<string, mixed>
     */
    private function jsonFixture(string $relativePath): array
    {
        $contents = file_get_contents($this->packagePath($relativePath));
        $this->assertIsString($contents);

        $decoded = json_decode($contents, associative: true, flags: JSON_THROW_ON_ERROR);
        $this->assertIsArray($decoded);

        return $decoded;
    }

    private function packagePath(string $relativePath): string
    {
        return base_path('halden-week4-data-package/'.$relativePath);
    }
}
