<?php

namespace App\Domain\CausalTrace;

use App\Domain\Assignments\EffectiveSeatAssignmentService;
use App\Domain\CausalTrace\Nodes\AdvisorNode;
use App\Domain\CausalTrace\Nodes\CausalTraceNode;
use App\Domain\CausalTrace\Nodes\ConsequenceNode;
use App\Domain\CausalTrace\Nodes\DecisionNode;
use App\Domain\CausalTrace\Nodes\EconomicNode;
use App\Domain\CausalTrace\Nodes\KpiNode;
use App\Domain\CausalTrace\Nodes\RankingNode;
use App\Domain\CausalTrace\Nodes\StandingNode;
use App\Domain\Submissions\DecisionDefinitionSnapshotter;
use App\Models\AdvisorConsultationSession;
use App\Models\ConsequenceLink;
use App\Models\DecisionSubmission;
use App\Models\EconomicResolution;
use App\Models\KpiSnapshot;
use App\Models\RankingSnapshot;
use App\Models\StandingEvent;
use App\Models\User;
use Carbon\CarbonInterface;
use Illuminate\Support\Carbon;
use InvalidArgumentException;

final class CausalTraceService
{
    public function __construct(
        private readonly DecisionDefinitionSnapshotter $snapshotter,
        private readonly EffectiveSeatAssignmentService $seatAssignments,
    ) {}

    public function forwardFromDecision(User $actor, DecisionSubmission $decision): CausalTrace
    {
        $this->assertFacultyCanTrace($actor, $decision->tenant_id, $decision->section_simulation_id);
        $decision->loadMissing(['definition', 'runtimeWeek', 'teamSimulation']);

        $nodes = [
            $this->decisionNode($decision, 10),
        ];
        $nodes = array_merge(
            $nodes,
            $this->advisorNodes($decision->tenant_id, $decision->team_simulation_id, $decision->section_simulation_week_id, 20),
            $this->standingNodesForDecision($decision, 25),
        );

        $resolutions = EconomicResolution::query()
            ->where('tenant_id', $decision->tenant_id)
            ->where('decision_submission_id', $decision->id)
            ->orderBy('id')
            ->get();

        foreach ($resolutions as $resolution) {
            $nodes[] = $this->economicNode($resolution, 30);
            $nodes = array_merge(
                $nodes,
                $this->consequenceNodesForEconomicResolution($resolution, 40),
                $this->kpiNodesForEconomicResolution($resolution, 50),
                $this->rankingNodes($resolution->tenant_id, $resolution->team_simulation_id, $resolution->section_simulation_week_id, 60),
            );
        }

        return new CausalTrace(
            direction: 'forward',
            rootType: 'decision',
            rootId: $decision->id,
            nodes: $this->orderedUniqueNodes($nodes),
        );
    }

    public function backwardFromConsequence(User $actor, ConsequenceLink $consequence): CausalTrace
    {
        $this->assertFacultyCanTrace($actor, $consequence->tenant_id, $consequence->section_simulation_id);
        $nodes = [
            $this->consequenceNode($consequence, 10),
        ];

        if ($consequence->source_type === EconomicResolution::class) {
            $resolution = EconomicResolution::query()
                ->where('tenant_id', $consequence->tenant_id)
                ->whereKey($consequence->source_id)
                ->first();

            if ($resolution instanceof EconomicResolution) {
                $decision = DecisionSubmission::query()->find($resolution->decision_submission_id);

                if ($decision instanceof DecisionSubmission) {
                    $nodes[] = $this->decisionNode($decision, 20);
                    $nodes = array_merge(
                        $nodes,
                        $this->advisorNodes($decision->tenant_id, $decision->team_simulation_id, $decision->section_simulation_week_id, 25),
                        $this->standingNodesForDecision($decision, 30),
                    );
                }

                $nodes[] = $this->economicNode($resolution, 40);
                $nodes = array_merge(
                    $nodes,
                    $this->kpiNodesForEconomicResolution($resolution, 50),
                    $this->rankingNodes($resolution->tenant_id, $resolution->team_simulation_id, $resolution->section_simulation_week_id, 60),
                );
            }
        }

        return new CausalTrace(
            direction: 'backward',
            rootType: 'consequence',
            rootId: $consequence->id,
            nodes: $this->orderedUniqueNodes($nodes),
        );
    }

    public function assertFacultyCanTrace(User $actor, int $tenantId, int $sectionSimulationId): void
    {
        if ($actor->tenant_id !== $tenantId) {
            throw new InvalidArgumentException('Actor cannot trace another tenant.');
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

        throw new InvalidArgumentException('Only authorized faculty can view causal traces.');
    }

    private function decisionNode(DecisionSubmission $decision, int $sortOrder): DecisionNode
    {
        $snapshot = $decision->historicalDefinitionSnapshot();
        $answers = $decision->historicalAnswers();

        if ($snapshot === []) {
            $decision->loadMissing('definition.fields');
            $snapshot = $this->snapshotter->snapshotForSubmission($decision->definition, $answers);
        }

        $definition = is_array($snapshot['definition'] ?? null) ? $snapshot['definition'] : [];
        $seatContext = is_array($snapshot['seat_context'] ?? null)
            ? $snapshot['seat_context']
            : $this->seatContextFor($decision);

        return new DecisionNode(
            id: $decision->id,
            teamSimulationId: $decision->team_simulation_id,
            runtimeWeekId: $decision->section_simulation_week_id,
            label: (string) ($definition['name'] ?? $decision->definition->name),
            sortOrder: $sortOrder,
            payload: [
                'status' => $decision->statusValue(),
                'answers' => $decision->answers,
                'definition' => $definition,
                'definition_version' => $definition['version'] ?? null,
                'definition_snapshot' => $snapshot,
                'available_alternatives' => $snapshot['available_alternatives'] ?? $this->snapshotter->alternativesFromSnapshot($snapshot, $answers),
                'seat_context' => $seatContext,
                'submitted_at' => $this->dateIso($decision->getAttribute('submitted_at')),
            ],
        );
    }

    /**
     * @return array<string, mixed>|null
     */
    private function seatContextFor(DecisionSubmission $decision): ?array
    {
        $userId = $decision->submitted_by_user_id ?? $decision->updated_by_user_id;

        if (! is_int($userId)) {
            return null;
        }

        $user = User::query()
            ->where('tenant_id', $decision->tenant_id)
            ->find($userId);

        if (! $user instanceof User) {
            return null;
        }

        $decision->loadMissing(['runtimeWeek', 'teamSimulation']);

        $submittedAt = $decision->getAttribute('submitted_at');

        return $this->seatAssignments->contextFor(
            teamSimulation: $decision->teamSimulation,
            user: $user,
            runtimeWeek: $decision->runtimeWeek,
            at: $submittedAt instanceof CarbonInterface ? $submittedAt : null,
        );
    }

    private function economicNode(EconomicResolution $resolution, int $sortOrder): EconomicNode
    {
        return new EconomicNode(
            id: $resolution->id,
            teamSimulationId: $resolution->team_simulation_id,
            runtimeWeekId: $resolution->section_simulation_week_id,
            label: $resolution->economic_engine,
            sortOrder: $sortOrder,
            payload: [
                'engine_version' => $resolution->engine_version,
                'transfer_price' => $resolution->transfer_price,
                'integrated_margin' => $resolution->integrated_margin,
                'resolved_at' => $this->dateIso($resolution->getAttribute('resolved_at')),
            ],
        );
    }

    private function consequenceNode(ConsequenceLink $link, int $sortOrder): ConsequenceNode
    {
        return new ConsequenceNode(
            id: $link->id,
            teamSimulationId: $link->team_simulation_id,
            runtimeWeekId: $link->source_section_simulation_week_id,
            label: $link->definition_key,
            sortOrder: $sortOrder,
            payload: [
                'definition_version' => $link->definition_version,
                'effect_type' => $link->effect_type,
                'explanation' => $link->explanation,
                'metadata' => $link->metadata,
                'occurred_at' => $link->occurredAtIso(),
            ],
        );
    }

    private function kpiNode(KpiSnapshot $snapshot, int $sortOrder): KpiNode
    {
        $snapshot->loadMissing('definition');

        return new KpiNode(
            id: $snapshot->id,
            teamSimulationId: $snapshot->team_simulation_id,
            runtimeWeekId: $snapshot->section_simulation_week_id,
            label: $snapshot->definition->key,
            sortOrder: $sortOrder,
            payload: [
                'status' => $snapshot->statusEnum()->value,
                'value' => $snapshot->value,
                'unit' => $snapshot->unit,
                'calculation_version' => $snapshot->calculation_version,
                'unavailable_reason' => $snapshot->unavailable_reason,
            ],
        );
    }

    private function rankingNode(RankingSnapshot $snapshot, int $sortOrder): RankingNode
    {
        return new RankingNode(
            id: $snapshot->id,
            teamSimulationId: $snapshot->team_simulation_id,
            runtimeWeekId: $snapshot->section_simulation_week_id,
            label: $snapshot->scopeEnum()->value,
            sortOrder: $sortOrder,
            payload: [
                'status' => $snapshot->statusEnum()->value,
                'composite_score' => $snapshot->composite_score,
                'rank' => $snapshot->rank,
                'ranking_version' => $snapshot->ranking_version,
            ],
        );
    }

    private function standingNode(StandingEvent $event, int $sortOrder): StandingNode
    {
        return new StandingNode(
            id: $event->id,
            teamSimulationId: $event->team_simulation_id,
            runtimeWeekId: $event->section_simulation_week_id,
            label: (string) $event->counterparty?->key,
            sortOrder: $sortOrder,
            payload: [
                'old_state' => $event->oldStateEnum()?->value,
                'new_state' => $event->newStateEnum()->value,
                'reason' => $event->reason,
                'trigger_type' => $event->trigger_type,
                'trigger_id' => $event->trigger_id,
                'occurred_at' => $event->occurredAtIso(),
            ],
        );
    }

    private function advisorNode(AdvisorConsultationSession $session, int $sortOrder): AdvisorNode
    {
        $session->loadMissing(['advisor', 'response']);

        return new AdvisorNode(
            id: $session->id,
            teamSimulationId: $session->team_simulation_id,
            runtimeWeekId: $session->section_simulation_week_id,
            label: $session->advisor->key,
            sortOrder: $sortOrder,
            payload: [
                'advisor_name' => $session->advisor->name,
                'advisor_title' => $session->advisor->title,
                'question' => $session->question,
                'response' => $session->response?->response,
                'content_version' => $session->response?->content_version,
                'requested_at' => $session->requestedAtIso(),
                'responded_at' => $session->response?->respondedAtIso(),
            ],
        );
    }

    /**
     * @return list<AdvisorNode>
     */
    private function advisorNodes(int $tenantId, int $teamSimulationId, int $runtimeWeekId, int $sortOrder): array
    {
        $nodes = [];

        foreach (AdvisorConsultationSession::query()
            ->where('tenant_id', $tenantId)
            ->where('team_simulation_id', $teamSimulationId)
            ->where('section_simulation_week_id', $runtimeWeekId)
            ->with(['advisor', 'response'])
            ->orderBy('id')
            ->get() as $session) {
            $nodes[] = $this->advisorNode($session, $sortOrder);
        }

        return $nodes;
    }

    /**
     * @return list<StandingNode>
     */
    private function standingNodesForDecision(DecisionSubmission $decision, int $sortOrder): array
    {
        $nodes = [];

        foreach (StandingEvent::query()
            ->with('counterparty')
            ->where('tenant_id', $decision->tenant_id)
            ->where('team_simulation_id', $decision->team_simulation_id)
            ->where('trigger_type', $decision->getMorphClass())
            ->where('trigger_id', $decision->id)
            ->orderBy('id')
            ->get() as $event) {
            $nodes[] = $this->standingNode($event, $sortOrder);
        }

        return $nodes;
    }

    /**
     * @return list<ConsequenceNode>
     */
    private function consequenceNodesForEconomicResolution(EconomicResolution $resolution, int $sortOrder): array
    {
        $nodes = [];

        foreach (ConsequenceLink::query()
            ->where('tenant_id', $resolution->tenant_id)
            ->where('team_simulation_id', $resolution->team_simulation_id)
            ->where('source_type', $resolution->getMorphClass())
            ->where('source_id', $resolution->id)
            ->orderBy('id')
            ->get() as $link) {
            $nodes[] = $this->consequenceNode($link, $sortOrder);
        }

        return $nodes;
    }

    /**
     * @return list<KpiNode>
     */
    private function kpiNodesForEconomicResolution(EconomicResolution $resolution, int $sortOrder): array
    {
        $nodes = [];

        foreach (KpiSnapshot::query()
            ->with('definition')
            ->where('tenant_id', $resolution->tenant_id)
            ->where('economic_resolution_id', $resolution->id)
            ->orderBy('id')
            ->get() as $snapshot) {
            $nodes[] = $this->kpiNode($snapshot, $sortOrder);
        }

        return $nodes;
    }

    /**
     * @return list<RankingNode>
     */
    private function rankingNodes(int $tenantId, int $teamSimulationId, int $runtimeWeekId, int $sortOrder): array
    {
        $nodes = [];

        foreach (RankingSnapshot::query()
            ->where('tenant_id', $tenantId)
            ->where('team_simulation_id', $teamSimulationId)
            ->where('section_simulation_week_id', $runtimeWeekId)
            ->orderBy('id')
            ->get() as $snapshot) {
            $nodes[] = $this->rankingNode($snapshot, $sortOrder);
        }

        return $nodes;
    }

    /**
     * @param  list<CausalTraceNode>  $nodes
     * @return list<CausalTraceNode>
     */
    private function orderedUniqueNodes(array $nodes): array
    {
        $unique = [];

        foreach ($nodes as $node) {
            $unique[$node->type.':'.$node->id] = $node;
        }

        usort($unique, fn (CausalTraceNode $left, CausalTraceNode $right): int => $left->sortOrder <=> $right->sortOrder ?: $left->type <=> $right->type ?: $left->id <=> $right->id);

        return $unique;
    }

    private function dateIso(mixed $value): ?string
    {
        if ($value === null) {
            return null;
        }

        if ($value instanceof CarbonInterface) {
            return $value->toISOString();
        }

        return Carbon::parse((string) $value)->toISOString();
    }
}
