<?php

namespace App\Domain\Economics\Resolution;

use App\Domain\Consequences\Week4ConsequenceResolver;
use App\Domain\Economics\Week4\Week4EconomicEngine;
use App\Domain\Economics\Week4\Week4EconomicResult;
use App\Domain\Economics\Week4\Week4GenevaArbitrageResult;
use App\Domain\Economics\Week4\Week4MappedDecision;
use App\Domain\Economics\Week4\Week4ResolutionInputMapper;
use App\Enums\SectionSimulationWeekStatus;
use App\Enums\SubmissionStatus;
use App\Models\DecisionSubmission;
use App\Models\EconomicResolution;
use App\Models\TeamMember;
use App\Models\User;
use Brick\Math\BigDecimal;
use Brick\Math\RoundingMode;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

final class WeekResolutionService
{
    public function __construct(
        private readonly Week4EconomicEngine $week4Engine,
        private readonly Week4ResolutionInputMapper $week4Mapper,
        private readonly Week4ConsequenceResolver $week4Consequences,
    ) {}

    public function resolveSubmittedDecision(
        DecisionSubmission $submission,
        ?User $actor = null,
        string $process = 'week_resolution_service',
    ): EconomicResolution {
        $submission->loadMissing(['runtimeWeek.definition', 'teamSimulation', 'definition']);

        if ($actor !== null) {
            $this->assertCanAccessTeam($actor, $submission->tenant_id, $submission->section_simulation_id, $submission->team_id);
        }

        $this->assertResolvable($submission);
        $mapped = $this->week4Mapper->map($submission);
        $result = $this->week4Engine->calculate($mapped->inputs, $mapped->transferPrice);
        $geneva = $this->week4Engine->genevaArbitrageForTransferPrice($mapped->inputs, $mapped->transferPrice);
        $workedExampleGeneva = $this->week4Engine->genevaArbitrageAtMidpoint($mapped->inputs);

        $resolution = DB::transaction(function () use ($submission, $actor, $process, $mapped, $result, $geneva, $workedExampleGeneva): EconomicResolution {
            $existing = EconomicResolution::query()
                ->where('tenant_id', $submission->tenant_id)
                ->where('section_simulation_week_id', $submission->section_simulation_week_id)
                ->where('team_simulation_id', $submission->team_simulation_id)
                ->lockForUpdate()
                ->first();

            if ($existing) {
                return $existing;
            }

            return EconomicResolution::query()->create([
                'tenant_id' => $submission->tenant_id,
                'section_simulation_id' => $submission->section_simulation_id,
                'section_simulation_week_id' => $submission->section_simulation_week_id,
                'team_simulation_id' => $submission->team_simulation_id,
                'team_id' => $submission->team_id,
                'decision_submission_id' => $submission->id,
                'economic_engine' => Week4EconomicEngine::ENGINE_IDENTIFIER,
                'engine_version' => Week4EconomicEngine::ENGINE_VERSION,
                'input_snapshot' => $this->inputSnapshot($mapped),
                'output_snapshot' => $this->outputSnapshot($result, $geneva, $workedExampleGeneva, $mapped),
                'transfer_price' => $this->databaseDecimal($result->transferPrice),
                'integrated_margin' => $this->databaseDecimal($result->integratedMargin),
                'upstream_margin' => $this->databaseDecimal($result->upstreamMargin),
                'refining_margin' => $this->databaseDecimal($result->refiningMargin),
                'upstream_vs_target' => $this->databaseDecimal($result->upstreamVsTarget),
                'refining_vs_target' => $this->databaseDecimal($result->refiningVsTarget),
                'geneva_gap' => $this->databaseDecimal($geneva->gap),
                'geneva_capture_per_bbl' => $this->databaseDecimal($geneva->capturePerBbl),
                'geneva_max_volume_bbl_day' => $this->databaseDecimal($geneva->maxVolumeBblDay),
                'resolved_by_user_id' => $actor?->id,
                'resolved_by_process' => $process,
                'resolved_at' => Carbon::now(),
            ]);
        });

        $this->week4Consequences->resolve($resolution, $actor);

        return $resolution;
    }

    public function assertCanView(User $actor, EconomicResolution $resolution): void
    {
        $this->assertCanAccessTeam($actor, $resolution->tenant_id, $resolution->section_simulation_id, $resolution->team_id);
    }

    private function assertResolvable(DecisionSubmission $submission): void
    {
        if ($submission->statusEnum() !== SubmissionStatus::Submitted) {
            throw new InvalidArgumentException('Only final decision submissions can be resolved.');
        }

        $runtimeWeek = $submission->runtimeWeek;
        $definition = $submission->definition;

        if (! in_array($runtimeWeek->statusEnum(), [
            SectionSimulationWeekStatus::Open,
            SectionSimulationWeekStatus::Closed,
            SectionSimulationWeekStatus::Published,
        ], true)) {
            throw new InvalidArgumentException('Runtime week is not in a resolvable state.');
        }

        if ($runtimeWeek->definition->week_number !== 4) {
            throw new InvalidArgumentException('Only Week 4 submissions can use the Week 4 economic engine.');
        }

        if ($definition->simulation_version_id !== $runtimeWeek->simulation_version_id || $definition->simulation_week_id !== $runtimeWeek->simulation_week_id) {
            throw new InvalidArgumentException('Decision definition does not match the runtime week simulation version.');
        }

        if ($submission->teamSimulation->section_simulation_id !== $runtimeWeek->section_simulation_id) {
            throw new InvalidArgumentException('Submission team is not assigned to the runtime section simulation.');
        }
    }

    private function assertCanAccessTeam(User $actor, int $tenantId, int $sectionSimulationId, int $teamId): void
    {
        if ($actor->tenant_id !== $tenantId) {
            throw new InvalidArgumentException('Actor cannot access economic resolutions for another tenant.');
        }

        if ($actor->isAdministrator()) {
            return;
        }

        if ($actor->isFaculty()) {
            $assigned = $actor->facultySections()
                ->wherePivot('tenant_id', $tenantId)
                ->whereHas('sectionSimulations', fn ($query) => $query->whereKey($sectionSimulationId))
                ->exists();

            if ($assigned) {
                return;
            }
        }

        if ($actor->isStudent()) {
            $member = TeamMember::query()
                ->where('tenant_id', $tenantId)
                ->where('team_id', $teamId)
                ->where('user_id', $actor->id)
                ->exists();

            if ($member) {
                return;
            }
        }

        throw new InvalidArgumentException('Actor cannot access this team economic resolution.');
    }

    /**
     * @return array<string, mixed>
     */
    private function inputSnapshot(Week4MappedDecision $mapped): array
    {
        return [
            'engine' => Week4EconomicEngine::ENGINE_IDENTIFIER,
            'engine_version' => Week4EconomicEngine::ENGINE_VERSION,
            'week4_inputs' => $mapped->inputSnapshot,
            'submission' => $mapped->submissionSnapshot,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function outputSnapshot(
        Week4EconomicResult $result,
        Week4GenevaArbitrageResult $geneva,
        Week4GenevaArbitrageResult $workedExampleGeneva,
        Week4MappedDecision $mapped,
    ): array {
        $snapshot = [
            'segment_result' => $result->toPackageSegmentArray(),
            'geneva_arbitrage' => $geneva->toPackageArray(),
            'worked_example_geneva_arbitrage' => $workedExampleGeneva->toPackageArray(),
            'transfer_price' => $this->exactDecimal($result->transferPrice),
            'integrated_margin' => $result->money($result->integratedMargin),
            'delivered_marginal_cost' => $result->money($result->deliveredMarginalCost),
        ];

        return $snapshot;
    }

    private function databaseDecimal(BigDecimal $value): string
    {
        return (string) $value->toScale(3, RoundingMode::Unnecessary);
    }

    private function exactDecimal(BigDecimal $value): string
    {
        $decimal = (string) $value;

        if (! str_contains($decimal, '.')) {
            return $decimal;
        }

        return rtrim(rtrim($decimal, '0'), '.');
    }
}
