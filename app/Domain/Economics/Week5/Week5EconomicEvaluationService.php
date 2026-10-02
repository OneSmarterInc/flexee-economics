<?php

namespace App\Domain\Economics\Week5;

use App\Domain\CohortFeedback\Window1CohortResponseFunctionCatalog;
use App\Enums\SubmissionStatus;
use App\Models\CohortFeedbackEffect;
use App\Models\DecisionSubmission;
use App\Models\User;
use App\Models\Week5EconomicEvaluation;
use Brick\Math\BigDecimal;
use Brick\Math\RoundingMode;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

final readonly class Week5EconomicEvaluationService
{
    public function __construct(
        private Week5EconomicEngine $engine,
    ) {}

    public function evaluate(
        DecisionSubmission $submission,
        User $actor,
        ?Week5ReferencePackage $package = null,
        string $process = 'week5_economic_evaluation_service',
    ): Week5EconomicEvaluation {
        $submission->loadMissing(['runtimeWeek.definition', 'definition']);
        $this->assertCanEvaluate($actor, $submission);

        return DB::transaction(function () use ($submission, $actor, $package, $process): Week5EconomicEvaluation {
            $existing = Week5EconomicEvaluation::query()
                ->where('tenant_id', $submission->tenant_id)
                ->where('decision_submission_id', $submission->id)
                ->where('engine_identifier', Week5EconomicEngine::ENGINE_IDENTIFIER)
                ->lockForUpdate()
                ->first();

            if ($existing instanceof Week5EconomicEvaluation) {
                return $existing;
            }

            $package ??= Week5ReferencePackage::fromRepository();

            if (! $package->isAvailable()) {
                return $this->createUnavailableEvaluation($submission, $actor, $package, $process);
            }

            $inputs = $package->inputs();
            $result = $this->engine->calculate($inputs);
            $decisionSnapshot = $this->decisionSnapshot($submission);
            $window1 = $this->window1HandoffSnapshot($submission);

            return Week5EconomicEvaluation::query()->create([
                'tenant_id' => $submission->tenant_id,
                'section_simulation_id' => $submission->section_simulation_id,
                'section_simulation_week_id' => $submission->section_simulation_week_id,
                'team_simulation_id' => $submission->team_simulation_id,
                'team_id' => $submission->team_id,
                'decision_submission_id' => $submission->id,
                'engine_identifier' => $result->engineIdentifier,
                'engine_version' => $result->engineVersion,
                'package_version' => $inputs->packageVersion,
                'status' => Week5EconomicEvaluation::STATUS_CALCULATED,
                'eur_change' => $this->decimal($result->eurChange, 6),
                'nok_usd_value_change' => $this->decimal($result->nokUsdValueChange, 6),
                'sgd_usd_value_change' => $this->decimal($result->sgdUsdValueChange, 6),
                'norway_benefit_musd' => $this->decimal($result->norwayBenefitMusd, 6),
                'norway_lifting_post' => $this->decimal($result->norwayLiftingPost, 6),
                'euro_retail_translation_musd' => $this->decimal($result->euroRetailTranslationMusd, 6),
                'existing_hedge_gain_musd' => $this->decimal($result->existingHedgeGainMusd, 6),
                'rot_net_eur_musd' => $this->decimal($result->rotNetEurMusd, 6),
                'rot_natural_hedge_ratio' => $this->decimal($result->rotNaturalHedgeRatio, 6),
                'rot_net_impact_musd' => $this->decimal($result->rotNetImpactMusd, 6),
                'rot_overhedge_loss_musd' => $this->decimal($result->rotOverhedgeLossMusd, 6),
                'sing_impact_musd' => $this->decimal($result->singImpactMusd, 6),
                'decision_snapshot' => $decisionSnapshot,
                'input_snapshot' => [
                    ...$result->inputSnapshot,
                    'decision_submission' => $decisionSnapshot,
                    'cohort_feedback' => [
                        'window1' => $window1,
                    ],
                ],
                'output_snapshot' => [
                    ...$result->outputSnapshot,
                    'nwe_crack_handoff' => $window1,
                    'week10_inherited_state' => $this->week10InheritedState($decisionSnapshot),
                ],
                'unavailable_reason' => null,
                'evaluated_by_user_id' => $actor->id,
                'evaluated_by_process' => $process,
                'evaluated_at' => Carbon::now(),
            ]);
        });
    }

    private function createUnavailableEvaluation(
        DecisionSubmission $submission,
        User $actor,
        Week5ReferencePackage $package,
        string $process,
    ): Week5EconomicEvaluation {
        $reason = $package->unavailableReason() ?? Week5ReferencePackage::MISSING_REASON;
        $decisionSnapshot = $this->decisionSnapshot($submission);

        return Week5EconomicEvaluation::query()->create([
            'tenant_id' => $submission->tenant_id,
            'section_simulation_id' => $submission->section_simulation_id,
            'section_simulation_week_id' => $submission->section_simulation_week_id,
            'team_simulation_id' => $submission->team_simulation_id,
            'team_id' => $submission->team_id,
            'decision_submission_id' => $submission->id,
            'engine_identifier' => Week5EconomicEngine::ENGINE_IDENTIFIER,
            'engine_version' => Week5EconomicEngine::ENGINE_VERSION,
            'package_version' => $package->version(),
            'status' => Week5EconomicEvaluation::STATUS_UNAVAILABLE_REFERENCE_PACKAGE,
            'decision_snapshot' => $decisionSnapshot,
            'input_snapshot' => [
                'status' => Week5EconomicEvaluation::STATUS_UNAVAILABLE_REFERENCE_PACKAGE,
                'decision_submission' => $decisionSnapshot,
            ],
            'output_snapshot' => [
                'status' => Week5EconomicEvaluation::STATUS_UNAVAILABLE_REFERENCE_PACKAGE,
                'reason' => $reason,
            ],
            'unavailable_reason' => $reason,
            'evaluated_by_user_id' => $actor->id,
            'evaluated_by_process' => $process,
            'evaluated_at' => Carbon::now(),
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function decisionSnapshot(DecisionSubmission $submission): array
    {
        return [
            'id' => $submission->id,
            'answers' => $this->answers($submission),
        ];
    }

    /**
     * @param  array<string, mixed>  $decisionSnapshot
     * @return array<string, mixed>
     */
    private function week10InheritedState(array $decisionSnapshot): array
    {
        $answers = $decisionSnapshot['answers'] ?? [];

        if (! is_array($answers)) {
            return [];
        }

        $nested = $answers['week10_inherited_state'] ?? [];
        $coverage = is_array($nested)
            ? ($nested['crude_hedge_coverage'] ?? null)
            : null;
        $coverage ??= $answers['crude_hedge_coverage'] ?? $answers['hedge_coverage'] ?? null;

        if ($coverage === null || $coverage === '') {
            return [];
        }

        return [
            'crude_hedge_coverage' => $this->decimal(BigDecimal::of((string) $coverage), 6),
        ];
    }

    /**
     * @return array<string, mixed>|null
     */
    private function window1HandoffSnapshot(DecisionSubmission $submission): ?array
    {
        $effect = CohortFeedbackEffect::query()
            ->where('tenant_id', $submission->tenant_id)
            ->where('section_simulation_id', $submission->section_simulation_id)
            ->where('target_section_simulation_week_id', $submission->section_simulation_week_id)
            ->where('effect_key', Window1CohortResponseFunctionCatalog::EFFECT_KEY)
            ->latest('id')
            ->first();

        if (! $effect instanceof CohortFeedbackEffect) {
            return null;
        }

        $snapshot = $effect->getAttribute('effect_snapshot');
        $response = is_array($snapshot) && is_array($snapshot['response'] ?? null)
            ? $snapshot['response']
            : [];
        $parameters = is_array($response['parameters'] ?? null)
            ? $response['parameters']
            : [];

        return [
            'cohort_feedback_effect_id' => $effect->id,
            'effect_key' => $effect->effect_key,
            'effect_version' => $effect->effect_version,
            'base_nwe_crack' => $parameters['base_crack'] ?? $response['parallel_universe_baseline'] ?? null,
            'final_nwe_crack' => $response['bounded_value'] ?? null,
            'effect_snapshot' => $snapshot,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function answers(DecisionSubmission $submission): array
    {
        $answers = $submission->getAttribute('answers');

        return is_array($answers) ? $answers : [];
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
        if ($submission->runtimeWeek->definition->week_number !== 5) {
            throw new InvalidArgumentException('Week 5 economic evaluation can only evaluate Week 5 decision submissions.');
        }

        if ($submission->statusValue() !== SubmissionStatus::Submitted->value) {
            throw new InvalidArgumentException('Week 5 economic evaluation requires a submitted decision.');
        }

        if ($actor->tenant_id !== $submission->tenant_id) {
            throw new InvalidArgumentException('Actor cannot evaluate Week 5 economics for another tenant.');
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

        throw new InvalidArgumentException('Only authorized faculty can evaluate Week 5 economics.');
    }
}
