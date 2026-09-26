<?php

namespace App\Domain\Economics\Week10;

use App\Enums\SubmissionStatus;
use App\Models\DecisionSubmission;
use App\Models\User;
use App\Models\Week10EconomicEvaluation;
use Brick\Math\BigDecimal;
use Brick\Math\RoundingMode;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

final readonly class Week10EconomicEvaluationService
{
    public function __construct(
        private Week10ConvergenceEconomicEngine $engine,
        private Week10InheritedStateAssembler $inheritedStateAssembler,
    ) {}

    public function evaluate(
        DecisionSubmission $submission,
        User $actor,
        ?Week10ReferencePackage $package = null,
        string $process = 'week10_economic_evaluation_service',
    ): Week10EconomicEvaluation {
        $submission->loadMissing(['runtimeWeek.definition', 'definition']);
        $this->assertCanEvaluate($actor, $submission);

        return DB::transaction(function () use ($submission, $actor, $package, $process): Week10EconomicEvaluation {
            $existing = Week10EconomicEvaluation::query()
                ->where('tenant_id', $submission->tenant_id)
                ->where('decision_submission_id', $submission->id)
                ->where('engine_identifier', Week10ConvergenceEconomicEngine::ENGINE_IDENTIFIER)
                ->lockForUpdate()
                ->first();

            if ($existing instanceof Week10EconomicEvaluation) {
                return $existing;
            }

            $package ??= Week10ReferencePackage::fromRepository();

            if (! $package->isAvailable()) {
                return $this->createUnavailableEvaluation($submission, $actor, $package, $process);
            }

            $inputs = $package->inputs();
            $state = $this->inheritedStateAssembler->assemble($submission);
            $result = $this->engine->calculate($inputs, $state);

            return Week10EconomicEvaluation::query()->create([
                'tenant_id' => $submission->tenant_id,
                'section_simulation_id' => $submission->section_simulation_id,
                'section_simulation_week_id' => $submission->section_simulation_week_id,
                'team_simulation_id' => $submission->team_simulation_id,
                'team_id' => $submission->team_id,
                'decision_submission_id' => $submission->id,
                'engine_identifier' => $result->engineIdentifier,
                'engine_version' => $result->engineVersion,
                'package_version' => $result->packageVersion,
                'status' => $result->status,
                'gasoline_demand_hit' => $this->decimal($result->demandHit('gasoline'), 6),
                'diesel_demand_hit' => $this->decimal($result->demandHit('diesel'), 6),
                'jet_demand_hit' => $this->decimal($result->demandHit('jet'), 6),
                'blended_demand_hit' => $this->decimal($result->blendedDemandHit, 6),
                'baton_rouge_demand_hit' => $this->decimal($result->refineryHit('baton_rouge'), 6),
                'rotterdam_demand_hit' => $this->decimal($result->refineryHit('rotterdam'), 6),
                'singapore_demand_hit' => $this->decimal($result->refineryHit('singapore'), 6),
                'hardest_hit_refinery' => $result->hardestHitRefinery,
                'binding_constraint_count' => $result->status === Week10EconomicResult::STATUS_CALCULATED ? $result->bindingCount : null,
                'unresolved_dependencies' => $result->unresolvedDependencies,
                'inherited_state_snapshot' => $state->snapshot(),
                'input_snapshot' => [
                    ...$result->inputSnapshot,
                    'decision_submission' => [
                        'id' => $submission->id,
                        'answers' => is_array($submission->answers) ? $submission->answers : [],
                    ],
                ],
                'output_snapshot' => $result->outputSnapshot,
                'unavailable_reason' => $result->status === Week10EconomicResult::STATUS_UNRESOLVED_DEPENDENCY
                    ? 'Week 10 inherited state is incomplete.'
                    : null,
                'evaluated_by_user_id' => $actor->id,
                'evaluated_by_process' => $process,
                'evaluated_at' => Carbon::now(),
            ]);
        });
    }

    private function createUnavailableEvaluation(
        DecisionSubmission $submission,
        User $actor,
        Week10ReferencePackage $package,
        string $process,
    ): Week10EconomicEvaluation {
        $reason = $package->unavailableReason() ?? Week10ReferencePackage::MISSING_REASON;

        return Week10EconomicEvaluation::query()->create([
            'tenant_id' => $submission->tenant_id,
            'section_simulation_id' => $submission->section_simulation_id,
            'section_simulation_week_id' => $submission->section_simulation_week_id,
            'team_simulation_id' => $submission->team_simulation_id,
            'team_id' => $submission->team_id,
            'decision_submission_id' => $submission->id,
            'engine_identifier' => Week10ConvergenceEconomicEngine::ENGINE_IDENTIFIER,
            'engine_version' => Week10ConvergenceEconomicEngine::ENGINE_VERSION,
            'package_version' => $package->version(),
            'status' => Week10EconomicEvaluation::STATUS_UNAVAILABLE_REFERENCE_PACKAGE,
            'unresolved_dependencies' => [],
            'inherited_state_snapshot' => [],
            'input_snapshot' => [
                'status' => Week10EconomicEvaluation::STATUS_UNAVAILABLE_REFERENCE_PACKAGE,
                'decision_submission_id' => $submission->id,
            ],
            'output_snapshot' => [
                'status' => Week10EconomicEvaluation::STATUS_UNAVAILABLE_REFERENCE_PACKAGE,
                'reason' => $reason,
            ],
            'unavailable_reason' => $reason,
            'evaluated_by_user_id' => $actor->id,
            'evaluated_by_process' => $process,
            'evaluated_at' => Carbon::now(),
        ]);
    }

    /**
     * @param  int<0, max>  $scale
     */
    private function decimal(BigDecimal $value, int $scale): string
    {
        return (string) $value->toScale($scale, RoundingMode::HalfUp);
    }

    private function assertCanEvaluate(User $actor, DecisionSubmission $submission): void
    {
        if ($submission->runtimeWeek->definition->week_number !== 10) {
            throw new InvalidArgumentException('Week 10 economic evaluation can only evaluate Week 10 decision submissions.');
        }

        if ($submission->statusValue() !== SubmissionStatus::Submitted->value) {
            throw new InvalidArgumentException('Week 10 economic evaluation requires a submitted decision.');
        }

        if ($actor->tenant_id !== $submission->tenant_id) {
            throw new InvalidArgumentException('Actor cannot evaluate Week 10 economics for another tenant.');
        }

        if ($actor->isAdministrator()) {
            return;
        }

        if ($actor->isFaculty()) {
            $assigned = $actor->facultySections()
                ->wherePivot('tenant_id', $submission->tenant_id)
                ->whereHas('sectionSimulations', fn ($query) => $query->whereKey($submission->section_simulation_id))
                ->exists();

            if ($assigned) {
                return;
            }
        }

        throw new InvalidArgumentException('Only authorized faculty can evaluate Week 10 economics.');
    }
}
