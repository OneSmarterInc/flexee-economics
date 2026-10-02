<?php

namespace App\Domain\Economics\Week13;

use Brick\Math\BigDecimal;
use InvalidArgumentException;

final readonly class Week13EconomicInputs
{
    /**
     * @param  array<string, BigDecimal>  $norwayUnion
     * @param  array<string, BigDecimal>  $permianLabor
     * @param  array<string, BigDecimal>  $turnaround
     * @param  array<string, array{structure: string, benchmark_wage_k: BigDecimal}>  $wageBenchmarks
     * @param  array<string, BigDecimal>  $workedExample
     * @param  array<string, mixed>  $golden
     * @param  array<string, string>  $sourceHashes
     */
    public function __construct(
        public array $norwayUnion,
        public array $permianLabor,
        public array $turnaround,
        public array $wageBenchmarks,
        public array $workedExample,
        public array $golden,
        public array $sourceHashes,
        public string $packageVersion,
    ) {}

    public function norwayParameter(string $key): BigDecimal
    {
        return $this->norwayUnion[$key]
            ?? throw new InvalidArgumentException("Week 13 Norway union parameter [{$key}] is not present in the reference package.");
    }

    public function permianParameter(string $key): BigDecimal
    {
        return $this->permianLabor[$key]
            ?? throw new InvalidArgumentException("Week 13 Permian labor parameter [{$key}] is not present in the reference package.");
    }

    public function turnaroundParameter(string $key): BigDecimal
    {
        return $this->turnaround[$key]
            ?? throw new InvalidArgumentException("Week 13 turnaround parameter [{$key}] is not present in the reference package.");
    }

    /**
     * @return array{structure: string, benchmark_wage_k: BigDecimal}
     */
    public function wageBenchmark(string $market): array
    {
        return $this->wageBenchmarks[$market]
            ?? throw new InvalidArgumentException("Week 13 wage benchmark [{$market}] is not present in the reference package.");
    }

    public function workedExampleParameter(string $key): BigDecimal
    {
        return $this->workedExample[$key]
            ?? throw new InvalidArgumentException("Week 13 worked-example parameter [{$key}] is not present in the reference package.");
    }
}
