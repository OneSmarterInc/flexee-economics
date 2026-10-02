<?php

namespace App\Domain\Economics\Week1;

use Brick\Math\BigDecimal;
use InvalidArgumentException;

final readonly class Week1EconomicInputs
{
    /**
     * @param  array<string, BigDecimal>  $benchmarks
     * @param  array<string, array<string, BigDecimal|string>>  $upstreamAssets
     * @param  array<string, array<string, BigDecimal>>  $refiningAssets
     * @param  array<string, BigDecimal>  $rotterdamCostSplit
     * @param  array<string, array<string, BigDecimal>>  $bookValues
     * @param  array<string, BigDecimal>  $workedExample
     * @param  array<string, mixed>  $golden
     * @param  array<string, mixed>  $sourceHashes
     */
    public function __construct(
        public array $benchmarks,
        public array $upstreamAssets,
        public array $refiningAssets,
        public array $rotterdamCostSplit,
        public array $bookValues,
        public array $workedExample,
        public array $golden,
        public array $sourceHashes,
        public string $packageVersion,
    ) {}

    public function benchmark(string $key): BigDecimal
    {
        return $this->benchmarks[strtolower($key)]
            ?? throw new InvalidArgumentException("Week 1 benchmark [{$key}] is not present in the reference package.");
    }

    /**
     * @return array<string, BigDecimal|string>
     */
    public function upstreamAsset(string $asset): array
    {
        return $this->upstreamAssets[$asset]
            ?? throw new InvalidArgumentException("Week 1 upstream asset [{$asset}] is not present in the reference package.");
    }

    /**
     * @return array<string, BigDecimal>
     */
    public function refinery(string $refinery): array
    {
        return $this->refiningAssets[$refinery]
            ?? throw new InvalidArgumentException("Week 1 refinery [{$refinery}] is not present in the reference package.");
    }

    public function rotterdamCost(string $key): BigDecimal
    {
        return $this->rotterdamCostSplit[$key]
            ?? throw new InvalidArgumentException("Week 1 Rotterdam cost parameter [{$key}] is not present in the reference package.");
    }

    /**
     * @return array<string, BigDecimal>
     */
    public function bookValue(string $asset): array
    {
        return $this->bookValues[$asset]
            ?? throw new InvalidArgumentException("Week 1 book-value row [{$asset}] is not present in the reference package.");
    }

    public function workedExampleParameter(string $key): BigDecimal
    {
        return $this->workedExample[$key]
            ?? throw new InvalidArgumentException("Week 1 worked-example parameter [{$key}] is not present in the reference package.");
    }
}
