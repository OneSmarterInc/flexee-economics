<?php

namespace App\Domain\Interpretation;

use App\Models\AdvisorConsultationSession;
use App\Models\ConsequenceLink;
use App\Models\DecisionSubmission;
use App\Models\EconomicResolution;
use App\Models\KpiSnapshot;
use App\Models\MemoSubmission;
use App\Models\RankingSnapshot;
use App\Models\SectionSimulationWeek;
use App\Models\StandingEvent;
use App\Models\StandingState;
use App\Models\TeamSimulation;
use App\Models\WhatIfRun;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;

final class FacultyInterpretationContextBuilder
{
    public const CONTEXT_VERSION = 'faculty_interpretation_context_v1';

    /**
     * @return array<string, mixed>
     */
    public function build(TeamSimulation $teamSimulation, SectionSimulationWeek $runtimeWeek): array
    {
        $teamSimulation->loadMissing(['team', 'sectionSimulation.section']);
        $runtimeWeek->loadMissing('definition');

        return [
            'context_version' => self::CONTEXT_VERSION,
            'team' => [
                'team_simulation_id' => $teamSimulation->id,
                'team_id' => $teamSimulation->team_id,
                'team_name' => $teamSimulation->team->name,
                'section_simulation_id' => $teamSimulation->section_simulation_id,
                'section_name' => $teamSimulation->sectionSimulation->section->name,
            ],
            'runtime_week' => [
                'section_simulation_week_id' => $runtimeWeek->id,
                'week_number' => $runtimeWeek->definition->week_number,
                'title' => $runtimeWeek->definition->title,
                'status' => $runtimeWeek->statusValue(),
            ],
            'decisions' => $this->decisions($teamSimulation, $runtimeWeek),
            'memos' => $this->memos($teamSimulation, $runtimeWeek),
            'economic_resolutions' => $this->economicResolutions($teamSimulation, $runtimeWeek),
            'kpi_snapshots' => $this->kpiSnapshots($teamSimulation, $runtimeWeek),
            'ranking_snapshots' => $this->rankingSnapshots($teamSimulation, $runtimeWeek),
            'standing_states' => $this->standingStates($teamSimulation),
            'standing_events' => $this->standingEvents($teamSimulation, $runtimeWeek),
            'consequence_links' => $this->consequenceLinks($teamSimulation, $runtimeWeek),
            'advisor_consultations' => $this->advisorConsultations($teamSimulation, $runtimeWeek),
            'alternatives' => $this->whatIfRuns($teamSimulation, $runtimeWeek),
        ];
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function decisions(TeamSimulation $teamSimulation, SectionSimulationWeek $runtimeWeek): array
    {
        $items = [];

        foreach (DecisionSubmission::query()
            ->with('definition')
            ->where('tenant_id', $teamSimulation->tenant_id)
            ->where('team_simulation_id', $teamSimulation->id)
            ->where('section_simulation_week_id', '<=', $runtimeWeek->id)
            ->orderBy('section_simulation_week_id')
            ->orderBy('id')
            ->get() as $submission) {
            $items[] = [
                'id' => $submission->id,
                'week_id' => $submission->section_simulation_week_id,
                'definition_key' => $submission->definition->key,
                'definition_name' => $submission->definition->name,
                'status' => $submission->statusValue(),
                'answers' => $submission->answers,
                'submitted_at' => $this->dateIso($submission->getAttribute('submitted_at')),
            ];
        }

        return $items;
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function memos(TeamSimulation $teamSimulation, SectionSimulationWeek $runtimeWeek): array
    {
        $items = [];

        foreach (MemoSubmission::query()
            ->with('definition')
            ->where('tenant_id', $teamSimulation->tenant_id)
            ->where('team_simulation_id', $teamSimulation->id)
            ->where('section_simulation_week_id', '<=', $runtimeWeek->id)
            ->orderBy('section_simulation_week_id')
            ->orderBy('id')
            ->get() as $submission) {
            $items[] = [
                'id' => $submission->id,
                'week_id' => $submission->section_simulation_week_id,
                'definition_key' => $submission->definition->key,
                'title' => $submission->definition->title,
                'status' => $submission->statusValue(),
                'body' => $submission->body,
                'word_count' => $submission->word_count,
                'submitted_at' => $this->dateIso($submission->getAttribute('submitted_at')),
            ];
        }

        return $items;
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function economicResolutions(TeamSimulation $teamSimulation, SectionSimulationWeek $runtimeWeek): array
    {
        $items = [];

        foreach (EconomicResolution::query()
            ->where('tenant_id', $teamSimulation->tenant_id)
            ->where('team_simulation_id', $teamSimulation->id)
            ->where('section_simulation_week_id', '<=', $runtimeWeek->id)
            ->orderBy('section_simulation_week_id')
            ->orderBy('id')
            ->get() as $resolution) {
            $items[] = [
                'id' => $resolution->id,
                'week_id' => $resolution->section_simulation_week_id,
                'decision_submission_id' => $resolution->decision_submission_id,
                'engine' => $resolution->economic_engine,
                'engine_version' => $resolution->engine_version,
                'transfer_price' => $resolution->transfer_price,
                'integrated_margin' => $resolution->integrated_margin,
                'upstream_margin' => $resolution->upstream_margin,
                'refining_margin' => $resolution->refining_margin,
                'geneva_capture_per_bbl' => $resolution->geneva_capture_per_bbl,
                'resolved_at' => $this->dateIso($resolution->getAttribute('resolved_at')),
            ];
        }

        return $items;
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function kpiSnapshots(TeamSimulation $teamSimulation, SectionSimulationWeek $runtimeWeek): array
    {
        $items = [];

        foreach (KpiSnapshot::query()
            ->with('definition')
            ->where('tenant_id', $teamSimulation->tenant_id)
            ->where('team_simulation_id', $teamSimulation->id)
            ->where('section_simulation_week_id', '<=', $runtimeWeek->id)
            ->orderBy('section_simulation_week_id')
            ->orderBy('id')
            ->get() as $snapshot) {
            $items[] = [
                'id' => $snapshot->id,
                'week_id' => $snapshot->section_simulation_week_id,
                'definition_key' => $snapshot->definition->key,
                'definition_version' => $snapshot->definition->version,
                'status' => $snapshot->statusEnum()->value,
                'value' => $snapshot->value,
                'unit' => $snapshot->unit,
                'calculation_version' => $snapshot->calculation_version,
                'unavailable_reason' => $snapshot->unavailable_reason,
            ];
        }

        return $items;
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function rankingSnapshots(TeamSimulation $teamSimulation, SectionSimulationWeek $runtimeWeek): array
    {
        $items = [];

        foreach (RankingSnapshot::query()
            ->where('tenant_id', $teamSimulation->tenant_id)
            ->where('team_simulation_id', $teamSimulation->id)
            ->where('section_simulation_week_id', '<=', $runtimeWeek->id)
            ->orderBy('section_simulation_week_id')
            ->orderBy('id')
            ->get() as $snapshot) {
            $items[] = [
                'id' => $snapshot->id,
                'week_id' => $snapshot->section_simulation_week_id,
                'scope' => $snapshot->scopeEnum()->value,
                'status' => $snapshot->statusEnum()->value,
                'composite_score' => $snapshot->composite_score,
                'rank' => $snapshot->rank,
                'ranking_version' => $snapshot->ranking_version,
                'incomplete_reason' => $snapshot->incomplete_reason,
            ];
        }

        return $items;
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function standingStates(TeamSimulation $teamSimulation): array
    {
        $items = [];

        foreach (StandingState::query()
            ->with('counterparty')
            ->where('tenant_id', $teamSimulation->tenant_id)
            ->where('team_simulation_id', $teamSimulation->id)
            ->orderBy('counterparty_id')
            ->get() as $standing) {
            $items[] = [
                'id' => $standing->id,
                'counterparty_key' => $standing->counterparty->key,
                'state' => $standing->stateEnum()->value,
                'reason' => $standing->reason,
                'state_changed_at' => $this->dateIso($standing->getAttribute('state_changed_at')),
            ];
        }

        return $items;
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function standingEvents(TeamSimulation $teamSimulation, SectionSimulationWeek $runtimeWeek): array
    {
        $items = [];

        foreach (StandingEvent::query()
            ->with('counterparty')
            ->where('tenant_id', $teamSimulation->tenant_id)
            ->where('team_simulation_id', $teamSimulation->id)
            ->where(function (Builder $query) use ($runtimeWeek): void {
                $query->whereNull('section_simulation_week_id')
                    ->orWhere('section_simulation_week_id', '<=', $runtimeWeek->id);
            })
            ->orderBy('section_simulation_week_id')
            ->orderBy('id')
            ->get() as $event) {
            $items[] = [
                'id' => $event->id,
                'week_id' => $event->section_simulation_week_id,
                'counterparty_key' => $event->counterparty->key,
                'old_state' => $event->oldStateEnum()?->value,
                'new_state' => $event->newStateEnum()->value,
                'reason' => $event->reason,
                'trigger_type' => $event->trigger_type,
                'trigger_id' => $event->trigger_id,
                'occurred_at' => $event->occurredAtIso(),
            ];
        }

        return $items;
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function consequenceLinks(TeamSimulation $teamSimulation, SectionSimulationWeek $runtimeWeek): array
    {
        $items = [];

        foreach (ConsequenceLink::query()
            ->where('tenant_id', $teamSimulation->tenant_id)
            ->where('team_simulation_id', $teamSimulation->id)
            ->where(function (Builder $query) use ($runtimeWeek): void {
                $query->whereNull('source_section_simulation_week_id')
                    ->orWhere('source_section_simulation_week_id', '<=', $runtimeWeek->id);
            })
            ->orderBy('source_section_simulation_week_id')
            ->orderBy('id')
            ->get() as $link) {
            $items[] = [
                'id' => $link->id,
                'source_week_id' => $link->source_section_simulation_week_id,
                'target_week_id' => $link->target_section_simulation_week_id,
                'definition_key' => $link->definition_key,
                'definition_version' => $link->definition_version,
                'source_type' => $link->source_type,
                'source_id' => $link->source_id,
                'target_type' => $link->target_type,
                'target_id' => $link->target_id,
                'effect_type' => $link->effect_type,
                'explanation' => $link->explanation,
                'metadata' => $link->metadata,
                'occurred_at' => $link->occurredAtIso(),
            ];
        }

        return $items;
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function advisorConsultations(TeamSimulation $teamSimulation, SectionSimulationWeek $runtimeWeek): array
    {
        $items = [];

        foreach (AdvisorConsultationSession::query()
            ->with(['advisor', 'response'])
            ->where('tenant_id', $teamSimulation->tenant_id)
            ->where('team_simulation_id', $teamSimulation->id)
            ->where('section_simulation_week_id', '<=', $runtimeWeek->id)
            ->orderBy('section_simulation_week_id')
            ->orderBy('id')
            ->get() as $session) {
            $items[] = [
                'id' => $session->id,
                'week_id' => $session->section_simulation_week_id,
                'advisor_key' => $session->advisor->key,
                'advisor_name' => $session->advisor->name,
                'advisor_title' => $session->advisor->title,
                'question' => $session->question,
                'response' => $session->response?->response,
                'content_version' => $session->response?->content_version,
                'requested_at' => $session->requestedAtIso(),
                'responded_at' => $session->response?->respondedAtIso(),
            ];
        }

        return $items;
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function whatIfRuns(TeamSimulation $teamSimulation, SectionSimulationWeek $runtimeWeek): array
    {
        $items = [];

        foreach (WhatIfRun::query()
            ->where('tenant_id', $teamSimulation->tenant_id)
            ->where('team_simulation_id', $teamSimulation->id)
            ->where('section_simulation_week_id', '<=', $runtimeWeek->id)
            ->orderBy('section_simulation_week_id')
            ->orderBy('id')
            ->get() as $run) {
            $items[] = [
                'id' => $run->id,
                'week_id' => $run->section_simulation_week_id,
                'source_economic_resolution_id' => $run->source_economic_resolution_id,
                'scenario_type' => $run->scenario_type,
                'counterfactual' => $run->counterfactual,
                'scenario_inputs' => $run->scenario_inputs,
                'calculated_outputs' => $run->calculated_outputs,
                'engine_identifier' => $run->engine_identifier,
                'engine_version' => $run->engine_version,
            ];
        }

        return $items;
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
