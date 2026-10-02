<?php

namespace App\Domain\Economics\Week12;

use Brick\Math\BigDecimal;
use Brick\Math\RoundingMode;

final readonly class Week12PortfolioResult
{
    /**
     * @param  list<string>  $projectKeys
     * @param  array<string, BigDecimal>  $bucketSpend
     * @param  list<string>  $constraintFailures
     */
    public function __construct(
        public array $projectKeys,
        public BigDecimal $capitalRequiredMusd,
        public BigDecimal $divestmentProceedsMusd,
        public BigDecimal $availableEnvelopeMusd,
        public array $bucketSpend,
        public bool $feasible,
        public bool $unlockedByDivestment,
        public array $constraintFailures,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function snapshot(): array
    {
        return [
            'project_keys' => $this->projectKeys,
            'capital_required_musd' => (string) $this->capitalRequiredMusd->toScale(6, RoundingMode::HalfUp),
            'divestment_proceeds_musd' => (string) $this->divestmentProceedsMusd->toScale(6, RoundingMode::HalfUp),
            'available_envelope_musd' => (string) $this->availableEnvelopeMusd->toScale(6, RoundingMode::HalfUp),
            'bucket_spend' => array_map(
                fn (BigDecimal $value): string => (string) $value->toScale(6, RoundingMode::HalfUp),
                $this->bucketSpend,
            ),
            'feasible' => $this->feasible,
            'unlocked_by_divestment' => $this->unlockedByDivestment,
            'constraint_failures' => $this->constraintFailures,
        ];
    }
}
