<?php

namespace App\Domain\Capital;

use App\Enums\SectionSimulationWeekStatus;
use App\Models\CapitalAllocationDecision;
use App\Models\CapitalProject;
use App\Models\DiscountRateConsequence;
use App\Models\SectionSimulationWeek;
use App\Models\TeamMember;
use App\Models\TeamSimulation;
use App\Models\User;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

final class CapitalAllocationService
{
    /**
     * @return Collection<int, CapitalProject>
     */
    public function activeProjects(): Collection
    {
        return CapitalProject::query()
            ->where('is_active', true)
            ->orderBy('category')
            ->orderBy('key')
            ->get();
    }

    public function contextFor(TeamSimulation $teamSimulation, SectionSimulationWeek $runtimeWeek): CapitalAllocationContext
    {
        $this->assertRuntimeWeekMatchesTeam($teamSimulation, $runtimeWeek);

        $consequence = DiscountRateConsequence::query()
            ->where('tenant_id', $teamSimulation->tenant_id)
            ->where('team_simulation_id', $teamSimulation->id)
            ->where('target_section_simulation_week_id', $runtimeWeek->id)
            ->orderByDesc('id')
            ->first();

        if (! $consequence instanceof DiscountRateConsequence) {
            return new CapitalAllocationContext(
                discountRateConsequence: null,
                discountRatePercent: null,
                capitalEnvelopeMusd: null,
                status: 'missing_discount_rate_consequence',
                snapshot: [
                    'status' => 'missing_discount_rate_consequence',
                    'reason' => 'No Week 4 discount-rate consequence exists for this Week 6 context.',
                    'runtime_week_id' => $runtimeWeek->id,
                    'team_simulation_id' => $teamSimulation->id,
                ],
            );
        }

        if ($consequence->status !== DiscountRateConsequence::STATUS_RESOLVED) {
            $resultSnapshot = $consequence->resultSnapshot();

            return new CapitalAllocationContext(
                discountRateConsequence: $consequence,
                discountRatePercent: null,
                capitalEnvelopeMusd: null,
                status: 'unavailable_discount_rate',
                snapshot: [
                    'status' => 'unavailable_discount_rate',
                    'discount_rate_consequence_id' => $consequence->id,
                    'reason' => $resultSnapshot['reason'] ?? 'Discount-rate consequence is unresolved.',
                    'classification' => $consequence->classification,
                ],
            );
        }

        return new CapitalAllocationContext(
            discountRateConsequence: $consequence,
            discountRatePercent: $consequence->discountRatePercentValue(),
            capitalEnvelopeMusd: $consequence->capitalEnvelopeMusdValue(),
            status: 'available',
            snapshot: [
                'status' => 'available',
                'discount_rate_consequence_id' => $consequence->id,
                'classification' => $consequence->classification,
                'discount_rate_percent' => $consequence->discountRatePercentValue(),
                'capital_envelope_musd' => $consequence->capitalEnvelopeMusdValue(),
                'schedule_key' => $consequence->schedule_key,
                'schedule_version' => $consequence->schedule_version,
            ],
        );
    }

    /**
     * @param  list<string>  $selectedProjectKeys
     * @param  list<string>  $rejectedProjectKeys
     * @param  array<string, mixed>  $memoReferences
     * @param  array<string, mixed>  $contextExtensions
     */
    public function submitAllocation(
        User $actor,
        TeamSimulation $teamSimulation,
        SectionSimulationWeek $runtimeWeek,
        array $selectedProjectKeys,
        array $rejectedProjectKeys,
        array $memoReferences = [],
        array $contextExtensions = [],
    ): CapitalAllocationDecision {
        $this->assertCanSubmit($actor, $teamSimulation, $runtimeWeek);
        $context = $this->contextFor($teamSimulation, $runtimeWeek);
        $contextSnapshot = $this->contextSnapshotWithExtensions($context->snapshot, $contextExtensions);
        $selected = $this->projectSnapshots($selectedProjectKeys);
        $rejected = $this->projectSnapshots($rejectedProjectKeys);
        $this->assertNoProjectOverlap($selected, $rejected);

        return DB::transaction(function () use ($actor, $teamSimulation, $runtimeWeek, $context, $contextSnapshot, $selected, $rejected, $memoReferences): CapitalAllocationDecision {
            $existing = CapitalAllocationDecision::query()
                ->where('tenant_id', $teamSimulation->tenant_id)
                ->where('section_simulation_week_id', $runtimeWeek->id)
                ->where('team_simulation_id', $teamSimulation->id)
                ->lockForUpdate()
                ->first();

            if ($existing instanceof CapitalAllocationDecision) {
                throw new InvalidArgumentException('Capital allocation decision has already been submitted for this team and week.');
            }

            return CapitalAllocationDecision::query()->create([
                'tenant_id' => $teamSimulation->tenant_id,
                'section_simulation_id' => $teamSimulation->section_simulation_id,
                'section_simulation_week_id' => $runtimeWeek->id,
                'team_simulation_id' => $teamSimulation->id,
                'team_id' => $teamSimulation->team_id,
                'discount_rate_consequence_id' => $context->discountRateConsequence?->id,
                'submitted_by_user_id' => $actor->id,
                'selected_projects' => $selected,
                'rejected_projects' => $rejected,
                'context_snapshot' => $contextSnapshot,
                'memo_references' => $memoReferences,
                'submitted_at' => Carbon::now(),
            ]);
        });
    }

    private function assertRuntimeWeekMatchesTeam(TeamSimulation $teamSimulation, SectionSimulationWeek $runtimeWeek): void
    {
        if ($teamSimulation->tenant_id !== $runtimeWeek->tenant_id || $teamSimulation->section_simulation_id !== $runtimeWeek->section_simulation_id) {
            throw new InvalidArgumentException('Capital allocation runtime week must match team simulation.');
        }
    }

    private function assertCanSubmit(User $actor, TeamSimulation $teamSimulation, SectionSimulationWeek $runtimeWeek): void
    {
        $this->assertRuntimeWeekMatchesTeam($teamSimulation, $runtimeWeek);

        if ($actor->tenant_id !== $teamSimulation->tenant_id) {
            throw new InvalidArgumentException('Actor cannot submit capital allocation for another tenant.');
        }

        $member = TeamMember::query()
            ->where('tenant_id', $teamSimulation->tenant_id)
            ->where('team_id', $teamSimulation->team_id)
            ->where('user_id', $actor->id)
            ->exists();

        if (! $member) {
            throw new InvalidArgumentException('Only team members can submit capital allocation decisions.');
        }

        if ($runtimeWeek->statusEnum() !== SectionSimulationWeekStatus::Open) {
            throw new InvalidArgumentException('Capital allocation week must be open for submission.');
        }
    }

    /**
     * @param  list<string>  $projectKeys
     * @return list<array<string, mixed>>
     */
    private function projectSnapshots(array $projectKeys): array
    {
        $snapshots = [];

        foreach ($projectKeys as $key) {
            $project = CapitalProject::query()
                ->where('key', $key)
                ->where('is_active', true)
                ->orderByDesc('id')
                ->first();

            if (! $project instanceof CapitalProject) {
                throw new InvalidArgumentException("Capital project [{$key}] is not active or does not exist.");
            }

            $snapshots[] = [
                'id' => $project->id,
                'key' => $project->key,
                'name' => $project->name,
                'category' => $project->category,
                'version' => $project->version,
                'cash_flow_reference' => $project->cash_flow_reference,
                'risk_class' => $project->risk_class,
                'required_inputs' => $project->requiredInputs(),
                'metadata' => $project->metadataSnapshot(),
            ];
        }

        return $snapshots;
    }

    /**
     * @param  list<array<string, mixed>>  $selected
     * @param  list<array<string, mixed>>  $rejected
     */
    private function assertNoProjectOverlap(array $selected, array $rejected): void
    {
        $selectedKeys = collect($selected)->pluck('key')->all();
        $rejectedKeys = collect($rejected)->pluck('key')->all();

        if (array_intersect($selectedKeys, $rejectedKeys) !== []) {
            throw new InvalidArgumentException('Capital allocation selected and rejected project lists must not overlap.');
        }
    }

    /**
     * @param  array<string, mixed>  $snapshot
     * @param  array<string, mixed>  $extensions
     * @return array<string, mixed>
     */
    private function contextSnapshotWithExtensions(array $snapshot, array $extensions): array
    {
        foreach (['seven_week_variant'] as $key) {
            if (array_key_exists($key, $extensions) && is_array($extensions[$key])) {
                $snapshot[$key] = [
                    ...(is_array($snapshot[$key] ?? null) ? $snapshot[$key] : []),
                    ...$extensions[$key],
                ];
            }
        }

        return $snapshot;
    }
}
