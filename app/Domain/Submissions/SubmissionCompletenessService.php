<?php

namespace App\Domain\Submissions;

use App\Enums\SubmissionStatus;
use App\Models\CapitalAllocationDecision;
use App\Models\DecisionFormDefinition;
use App\Models\DecisionSubmission;
use App\Models\MemoDefinition;
use App\Models\MemoSubmission;
use App\Models\SectionSimulationWeek;
use App\Models\TeamSimulation;

class SubmissionCompletenessService
{
    /**
     * @return array<string, mixed>
     */
    public function statusFor(SectionSimulationWeek $runtimeWeek, TeamSimulation $teamSimulation): array
    {
        $runtimeWeek->loadMissing('definition.decisionFormDefinitions', 'definition.memoDefinitions');

        $decisionDefinition = $runtimeWeek->definition->decisionFormDefinitions->first();
        $memoDefinition = $runtimeWeek->definition->memoDefinitions->first();

        $decisionSubmission = $decisionDefinition
            ? DecisionSubmission::query()
                ->where('tenant_id', $runtimeWeek->tenant_id)
                ->where('section_simulation_week_id', $runtimeWeek->id)
                ->where('team_simulation_id', $teamSimulation->id)
                ->where('decision_form_definition_id', $decisionDefinition->id)
                ->first()
            : null;

        $memoSubmission = $memoDefinition
            ? MemoSubmission::query()
                ->where('tenant_id', $runtimeWeek->tenant_id)
                ->where('section_simulation_week_id', $runtimeWeek->id)
                ->where('team_simulation_id', $teamSimulation->id)
                ->where('memo_definition_id', $memoDefinition->id)
                ->first()
            : null;

        $decisionComplete = $this->pieceComplete($decisionDefinition, $decisionSubmission);
        $memoComplete = $this->pieceComplete($memoDefinition, $memoSubmission);
        $capitalAllocationRequired = $runtimeWeek->definition->week_number === 6;
        $capitalAllocationSubmission = $capitalAllocationRequired
            ? CapitalAllocationDecision::query()
                ->where('tenant_id', $runtimeWeek->tenant_id)
                ->where('section_simulation_week_id', $runtimeWeek->id)
                ->where('team_simulation_id', $teamSimulation->id)
                ->first()
            : null;
        $capitalAllocationComplete = ! $capitalAllocationRequired || $capitalAllocationSubmission instanceof CapitalAllocationDecision;

        return [
            'decision_required' => (bool) $decisionDefinition?->is_required,
            'memo_required' => (bool) $memoDefinition?->is_required,
            'capital_allocation_required' => $capitalAllocationRequired,
            'decision_status' => $decisionSubmission?->statusValue() ?? 'not_started',
            'memo_status' => $memoSubmission?->statusValue() ?? 'not_started',
            'capital_allocation_status' => $capitalAllocationSubmission instanceof CapitalAllocationDecision ? 'submitted' : 'not_started',
            'decision_submitted_at' => $decisionSubmission?->submitted_at,
            'memo_submitted_at' => $memoSubmission?->submitted_at,
            'capital_allocation_submitted_at' => $capitalAllocationSubmission?->submitted_at,
            'complete' => $decisionComplete && $memoComplete && $capitalAllocationComplete,
            'ready_for_evaluation' => $decisionComplete && $memoComplete && $capitalAllocationComplete,
        ];
    }

    private function pieceComplete(DecisionFormDefinition|MemoDefinition|null $definition, DecisionSubmission|MemoSubmission|null $submission): bool
    {
        if (! $definition || ! $definition->is_required) {
            return true;
        }

        return $submission?->statusEnum() === SubmissionStatus::Submitted;
    }
}
