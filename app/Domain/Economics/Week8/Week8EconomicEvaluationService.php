<?php

namespace App\Domain\Economics\Week8;

use App\Domain\CohortFeedback\Window2CohortResponseFunctionCatalog;
use App\Enums\SubmissionStatus;
use App\Models\CohortFeedbackEffect;
use App\Models\DecisionSubmission;
use App\Models\User;
use App\Models\Week8EconomicEvaluation;
use Brick\Math\BigDecimal;
use Brick\Math\RoundingMode;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

final readonly class Week8EconomicEvaluationService
{
    public function __construct(
        private Week8EconomicEngine $engine,
    ) {}

    public function evaluate(
        DecisionSubmission $submission,
        User $actor,
        ?Week8ReferencePackage $package = null,
        string $process = 'week8_economic_evaluation_service',
    ): Week8EconomicEvaluation {
        $submission->loadMissing(['runtimeWeek.definition', 'definition']);
        $this->assertCanEvaluate($actor, $submission);

        return DB::transaction(function () use ($submission, $actor, $package, $process): Week8EconomicEvaluation {
            $existing = Week8EconomicEvaluation::query()
                ->where('tenant_id', $submission->tenant_id)
                ->where('decision_submission_id', $submission->id)
                ->where('engine_identifier', Week8EconomicEngine::ENGINE_IDENTIFIER)
                ->lockForUpdate()
                ->first();

            if ($existing instanceof Week8EconomicEvaluation) {
                return $existing;
            }

            $package ??= Week8ReferencePackage::fromRepository();

            if (! $package->isAvailable()) {
                return $this->createUnavailableEvaluation($submission, $actor, $package, $process);
            }

            $inputs = $package->inputs($this->cohortAdjustmentFor($submission));
            $prediction = $this->predictionDistribution($submission);
            $realizedScenarioKey = $this->realizedScenarioKey($submission);
            $result = $this->engine->calculate($inputs, $prediction, $realizedScenarioKey);
            $realized = $result->realizedScenarioResult;
            $outputSnapshot = [
                ...$result->outputSnapshot,
                ...$this->week10InheritedState($submission),
            ];

            return Week8EconomicEvaluation::query()->create([
                'tenant_id' => $submission->tenant_id,
                'section_simulation_id' => $submission->section_simulation_id,
                'section_simulation_week_id' => $submission->section_simulation_week_id,
                'team_simulation_id' => $submission->team_simulation_id,
                'team_id' => $submission->team_id,
                'decision_submission_id' => $submission->id,
                'engine_identifier' => $result->engineIdentifier,
                'engine_version' => $result->engineVersion,
                'package_version' => $inputs->packageVersion,
                'status' => Week8EconomicEvaluation::STATUS_CALCULATED,
                'expected_wti' => $this->money($result->expectedWti),
                'expected_upstream_impact_per_bbl' => $this->money($result->expectedUpstreamImpactPerBbl),
                'expected_refining_crack' => $this->money($result->expectedRefiningCrack),
                'prediction_expected_wti' => $result->predictionExpectedWti === null ? null : $this->money($result->predictionExpectedWti),
                'prediction_expected_upstream_impact_per_bbl' => $result->predictionExpectedUpstreamImpactPerBbl === null ? null : $this->money($result->predictionExpectedUpstreamImpactPerBbl),
                'prediction_expected_refining_crack' => $result->predictionExpectedRefiningCrack === null ? null : $this->money($result->predictionExpectedRefiningCrack),
                'realized_scenario_key' => $realized?->scenarioKey,
                'realized_wti' => $realized === null ? null : $this->money($realized->wtiResolved),
                'realized_upstream_impact_per_bbl' => $realized === null ? null : $this->money($realized->upstreamImpactPerBbl),
                'realized_refining_crack' => $realized === null ? null : $this->money($realized->refiningCrack),
                'realized_retail_volume_percent' => $realized === null ? null : $this->percent($realized->retailVolumePercent),
                'prediction_snapshot' => $result->outputSnapshot['prediction'] ?? null,
                'realization_snapshot' => $result->outputSnapshot['realization'] ?? null,
                'input_snapshot' => $result->inputSnapshot,
                'output_snapshot' => $outputSnapshot,
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
        Week8ReferencePackage $package,
        string $process,
    ): Week8EconomicEvaluation {
        $reason = $package->unavailableReason() ?? Week8ReferencePackage::MISSING_REASON;

        return Week8EconomicEvaluation::query()->create([
            'tenant_id' => $submission->tenant_id,
            'section_simulation_id' => $submission->section_simulation_id,
            'section_simulation_week_id' => $submission->section_simulation_week_id,
            'team_simulation_id' => $submission->team_simulation_id,
            'team_id' => $submission->team_id,
            'decision_submission_id' => $submission->id,
            'engine_identifier' => Week8EconomicEngine::ENGINE_IDENTIFIER,
            'engine_version' => Week8EconomicEngine::ENGINE_VERSION,
            'package_version' => $package->version(),
            'status' => Week8EconomicEvaluation::STATUS_UNAVAILABLE_REFERENCE_PACKAGE,
            'input_snapshot' => [
                'status' => Week8EconomicEvaluation::STATUS_UNAVAILABLE_REFERENCE_PACKAGE,
                'decision_submission_id' => $submission->id,
            ],
            'output_snapshot' => [
                'status' => Week8EconomicEvaluation::STATUS_UNAVAILABLE_REFERENCE_PACKAGE,
                'reason' => $reason,
            ],
            'unavailable_reason' => $reason,
            'evaluated_by_user_id' => $actor->id,
            'evaluated_by_process' => $process,
            'evaluated_at' => Carbon::now(),
        ]);
    }

    private function cohortAdjustmentFor(DecisionSubmission $submission): Week8CohortAdjustment
    {
        $effect = CohortFeedbackEffect::query()
            ->where('tenant_id', $submission->tenant_id)
            ->where('target_section_simulation_week_id', $submission->section_simulation_week_id)
            ->where('effect_key', Window2CohortResponseFunctionCatalog::EFFECT_KEY)
            ->orderByDesc('id')
            ->first();

        if (! $effect instanceof CohortFeedbackEffect) {
            return Week8CohortAdjustment::none();
        }

        $snapshotValue = $effect->getAttribute('effect_snapshot');
        $snapshot = is_array($snapshotValue) ? $snapshotValue : [];
        $responseValue = $snapshot['response'] ?? null;
        $response = is_array($responseValue) ? $responseValue : [];
        $shift = BigDecimal::of((string) ($response['bounded_value'] ?? '0'));

        return new Week8CohortAdjustment(
            refiningCrackShift: $shift,
            snapshot: [
                'status' => 'applied',
                'cohort_feedback_effect_id' => $effect->id,
                'cohort_decision_aggregate_id' => $effect->cohort_decision_aggregate_id,
                'function' => $snapshot['function'] ?? null,
                'source_week_number' => $snapshot['source_week_number'] ?? 6,
                'target_week_number' => $snapshot['target_week_number'] ?? 8,
                'aggregate' => $snapshot['aggregate'] ?? null,
                'response' => $response,
                'effect_key' => $effect->effect_key,
                'effect_version' => $effect->effect_version,
            ],
        );
    }

    /**
     * @return array<string, string>
     */
    private function predictionDistribution(DecisionSubmission $submission): array
    {
        $answers = $this->answers($submission);
        $distribution = $answers['prediction_distribution'] ?? null;

        if (is_array($distribution)) {
            return [
                'holds_full' => $this->decimalString($distribution['holds_full'] ?? null, 'holds_full'),
                'holds_partial' => $this->decimalString($distribution['holds_partial'] ?? null, 'holds_partial'),
                'fails' => $this->decimalString($distribution['fails'] ?? null, 'fails'),
            ];
        }

        return [
            'holds_full' => $this->decimalString($answers['probability_holds_full'] ?? null, 'probability_holds_full'),
            'holds_partial' => $this->decimalString($answers['probability_holds_partial'] ?? null, 'probability_holds_partial'),
            'fails' => $this->decimalString($answers['probability_fails'] ?? null, 'probability_fails'),
        ];
    }

    private function realizedScenarioKey(DecisionSubmission $submission): ?string
    {
        $answers = $this->answers($submission);
        $value = $answers['realized_scenario_key'] ?? $answers['realized_scenario'] ?? null;

        if ($value === null || $value === '') {
            return null;
        }

        return (string) $value;
    }

    /**
     * @return array<string, array<string, string>>
     */
    private function week10InheritedState(DecisionSubmission $submission): array
    {
        $answers = $this->answers($submission);

        if (! array_key_exists('cash_cushion_musd', $answers)) {
            return [];
        }

        return [
            'week10_inherited_state' => [
                'cash_cushion_musd' => $this->money(BigDecimal::of((string) $answers['cash_cushion_musd'])),
            ],
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

    private function decimalString(mixed $value, string $key): string
    {
        if ($value === null || $value === '') {
            throw new InvalidArgumentException("Week 8 decision submission is missing prediction field [{$key}].");
        }

        return (string) BigDecimal::of((string) $value)->toScale(6, RoundingMode::HalfUp);
    }

    private function money(BigDecimal $value): string
    {
        return (string) $value->toScale(2, RoundingMode::HalfUp);
    }

    private function percent(BigDecimal $value): string
    {
        return (string) $value->toScale(3, RoundingMode::HalfUp);
    }

    private function assertCanEvaluate(User $actor, DecisionSubmission $submission): void
    {
        if ($submission->runtimeWeek->definition->week_number !== 8) {
            throw new InvalidArgumentException('Week 8 economic evaluation can only evaluate Week 8 decision submissions.');
        }

        if ($submission->statusValue() !== SubmissionStatus::Submitted->value) {
            throw new InvalidArgumentException('Week 8 economic evaluation requires a submitted decision.');
        }

        if ($actor->tenant_id !== $submission->tenant_id) {
            throw new InvalidArgumentException('Actor cannot evaluate Week 8 economics for another tenant.');
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

        throw new InvalidArgumentException('Only authorized faculty can evaluate Week 8 economics.');
    }
}
