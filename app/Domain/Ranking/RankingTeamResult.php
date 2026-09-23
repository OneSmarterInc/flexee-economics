<?php

namespace App\Domain\Ranking;

use App\Enums\RankingSnapshotStatus;
use App\Models\TeamSimulation;
use Brick\Math\BigDecimal;

final readonly class RankingTeamResult
{
    /**
     * @param  list<array<string, mixed>>  $inputSnapshots
     */
    public function __construct(
        public TeamSimulation $teamSimulation,
        public RankingSnapshotStatus $status,
        public ?BigDecimal $compositeScore,
        public ?int $rank,
        public array $inputSnapshots,
        public ?string $incompleteReason,
    ) {}

    public function withRank(int $rank): self
    {
        return new self(
            teamSimulation: $this->teamSimulation,
            status: $this->status,
            compositeScore: $this->compositeScore,
            rank: $rank,
            inputSnapshots: $this->inputSnapshots,
            incompleteReason: $this->incompleteReason,
        );
    }
}
