<?php

namespace App\Domain\Economics\Week10;

use Brick\Math\BigDecimal;
use InvalidArgumentException;

final readonly class Week10InheritedState
{
    /**
     * @param  array<string, Week10HistoricalDependency>  $dependencies
     */
    public function __construct(
        public ?BigDecimal $cancellableCapexMusd,
        public ?BigDecimal $crudeHedgeCoverage,
        public ?bool $batonRougeReportedMarginStrong,
        public ?string $straitsPacificStanding,
        public ?BigDecimal $cashCushionMusd,
        public array $dependencies,
    ) {}

    public static function fromReferenceFixture(Week10ReferenceInputs $inputs, string $teamKey): self
    {
        $state = $inputs->priorState($teamKey);

        return new self(
            cancellableCapexMusd: $state['cancellable_capex_musd'],
            crudeHedgeCoverage: $state['crude_hedge_coverage'],
            batonRougeReportedMarginStrong: $state['br_reported_margin_strong'],
            straitsPacificStanding: $state['straits_pacific_standing'],
            cashCushionMusd: $state['cash_cushion_musd'],
            dependencies: [
                'cancellable_capex_musd' => Week10HistoricalDependency::available(
                    key: 'cancellable_capex_musd',
                    sourceWeek: 'fixture:week6',
                    sourceEntity: 'team_prior_state.csv',
                    sourceValue: (string) $state['cancellable_capex_musd'],
                    sourceId: $teamKey,
                    sourceVersion: $inputs->packageVersion,
                ),
                'crude_hedge_coverage' => Week10HistoricalDependency::available(
                    key: 'crude_hedge_coverage',
                    sourceWeek: 'fixture:week5',
                    sourceEntity: 'team_prior_state.csv',
                    sourceValue: (string) $state['crude_hedge_coverage'],
                    sourceId: $teamKey,
                    sourceVersion: $inputs->packageVersion,
                ),
                'br_reported_margin_strong' => Week10HistoricalDependency::available(
                    key: 'br_reported_margin_strong',
                    sourceWeek: 'fixture:week4',
                    sourceEntity: 'team_prior_state.csv',
                    sourceValue: $state['br_reported_margin_strong'] ? 'true' : 'false',
                    sourceId: $teamKey,
                    sourceVersion: $inputs->packageVersion,
                ),
                'straits_pacific_standing' => Week10HistoricalDependency::available(
                    key: 'straits_pacific_standing',
                    sourceWeek: 'fixture:standing_history',
                    sourceEntity: 'team_prior_state.csv',
                    sourceValue: (string) $state['straits_pacific_standing'],
                    sourceId: $teamKey,
                    sourceVersion: $inputs->packageVersion,
                ),
                'cash_cushion_musd' => Week10HistoricalDependency::available(
                    key: 'cash_cushion_musd',
                    sourceWeek: 'fixture:week8',
                    sourceEntity: 'team_prior_state.csv',
                    sourceValue: (string) $state['cash_cushion_musd'],
                    sourceId: $teamKey,
                    sourceVersion: $inputs->packageVersion,
                ),
            ],
        );
    }

    /**
     * @return list<string>
     */
    public function unresolvedDependencyKeys(): array
    {
        return array_values(array_map(
            fn (Week10HistoricalDependency $dependency): string => $dependency->key,
            array_filter($this->dependencies, fn (Week10HistoricalDependency $dependency): bool => ! $dependency->isAvailable()),
        ));
    }

    public function requireCancellableCapex(): BigDecimal
    {
        return $this->cancellableCapexMusd ?? throw new InvalidArgumentException('Week 10 cancellable capex dependency is unresolved.');
    }

    public function requireCrudeHedgeCoverage(): BigDecimal
    {
        return $this->crudeHedgeCoverage ?? throw new InvalidArgumentException('Week 10 crude hedge coverage dependency is unresolved.');
    }

    public function requireBatonRougeReportedMarginStrong(): bool
    {
        return $this->batonRougeReportedMarginStrong ?? throw new InvalidArgumentException('Week 10 Baton Rouge reported margin dependency is unresolved.');
    }

    public function requireStraitsPacificStanding(): string
    {
        return $this->straitsPacificStanding ?? throw new InvalidArgumentException('Week 10 Straits Pacific standing dependency is unresolved.');
    }

    public function requireCashCushion(): BigDecimal
    {
        return $this->cashCushionMusd ?? throw new InvalidArgumentException('Week 10 cash cushion dependency is unresolved.');
    }

    /**
     * @return array<string, mixed>
     */
    public function snapshot(): array
    {
        return [
            'values' => [
                'cancellable_capex_musd' => $this->cancellableCapexMusd === null ? null : (string) $this->cancellableCapexMusd,
                'crude_hedge_coverage' => $this->crudeHedgeCoverage === null ? null : (string) $this->crudeHedgeCoverage,
                'br_reported_margin_strong' => $this->batonRougeReportedMarginStrong,
                'straits_pacific_standing' => $this->straitsPacificStanding,
                'cash_cushion_musd' => $this->cashCushionMusd === null ? null : (string) $this->cashCushionMusd,
            ],
            'dependencies' => array_map(
                fn (Week10HistoricalDependency $dependency): array => $dependency->snapshot(),
                $this->dependencies,
            ),
        ];
    }
}
