<?php

namespace App\Domain\Economics\Week9;

use App\Domain\CohortFeedback\Window3CohortResponseFunctionCatalog;
use App\Enums\SubmissionStatus;
use App\Models\CohortFeedbackEffect;
use App\Models\DecisionSubmission;
use App\Models\User;
use App\Models\Week9EconomicEvaluation;
use Brick\Math\BigDecimal;
use Brick\Math\RoundingMode;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

final readonly class Week9EconomicEvaluationService
{
    public function __construct(
        private Week9EconomicEngine $engine,
    ) {}

    public function evaluate(
        DecisionSubmission $submission,
        User $actor,
        ?Week9ReferencePackage $package = null,
        string $process = 'week9_economic_evaluation_service',
    ): Week9EconomicEvaluation {
        $submission->loadMissing(['runtimeWeek.definition', 'definition']);
        $this->assertCanEvaluate($actor, $submission);

        return DB::transaction(function () use ($submission, $actor, $package, $process): Week9EconomicEvaluation {
            $existing = Week9EconomicEvaluation::query()
                ->where('tenant_id', $submission->tenant_id)
                ->where('decision_submission_id', $submission->id)
                ->where('engine_identifier', Week9EconomicEngine::ENGINE_IDENTIFIER)
                ->lockForUpdate()
                ->first();

            if ($existing instanceof Week9EconomicEvaluation) {
                return $existing;
            }

            $package ??= Week9ReferencePackage::fromRepository();

            if (! $package->isAvailable()) {
                return $this->createUnavailableEvaluation($submission, $actor, $package, $process);
            }

            $inputs = $package->inputs();
            $selectedMarkets = $this->selectedRebrandMarkets($submission, $inputs);
            $nonfuelStateKey = $this->nonfuelStateKey($submission);
            $result = $this->engine->calculate($inputs, $nonfuelStateKey, $selectedMarkets);
            $window3 = $this->window3HandoffSnapshot($submission);

            return Week9EconomicEvaluation::query()->create([
                'tenant_id' => $submission->tenant_id,
                'section_simulation_id' => $submission->section_simulation_id,
                'section_simulation_week_id' => $submission->section_simulation_week_id,
                'team_simulation_id' => $submission->team_simulation_id,
                'team_id' => $submission->team_id,
                'decision_submission_id' => $submission->id,
                'engine_identifier' => $result->engineIdentifier,
                'engine_version' => $result->engineVersion,
                'package_version' => $inputs->packageVersion,
                'status' => Week9EconomicEvaluation::STATUS_CALCULATED,
                'nonfuel_state_key' => $nonfuelStateKey,
                'selected_rebrand_markets' => $selectedMarkets,
                'cost_per_site' => $this->decimal($result->costPerSite, 6),
                'partial_gain_musd' => $this->decimal($result->partialGainMusd, 6),
                'partial_cost_musd' => $this->decimal($result->partialCostMusd, 6),
                'partial_payback_years' => $this->decimal($result->partialPaybackYears, 6),
                'full_net_gain_musd' => $this->decimal($result->fullNetGainMusd, 6),
                'pricewar_partial_payback_years' => $this->decimal($result->priceWarPartialPaybackYears, 6),
                'input_snapshot' => [
                    ...$result->inputSnapshot,
                    'cohort_feedback' => [
                        'window3' => $window3,
                    ],
                ],
                'output_snapshot' => [
                    ...$result->outputSnapshot,
                    'nonfuel_margin_handoff' => $window3,
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
        Week9ReferencePackage $package,
        string $process,
    ): Week9EconomicEvaluation {
        $reason = $package->unavailableReason() ?? Week9ReferencePackage::MISSING_REASON;

        return Week9EconomicEvaluation::query()->create([
            'tenant_id' => $submission->tenant_id,
            'section_simulation_id' => $submission->section_simulation_id,
            'section_simulation_week_id' => $submission->section_simulation_week_id,
            'team_simulation_id' => $submission->team_simulation_id,
            'team_id' => $submission->team_id,
            'decision_submission_id' => $submission->id,
            'engine_identifier' => Week9EconomicEngine::ENGINE_IDENTIFIER,
            'engine_version' => Week9EconomicEngine::ENGINE_VERSION,
            'package_version' => $package->version(),
            'status' => Week9EconomicEvaluation::STATUS_UNAVAILABLE_REFERENCE_PACKAGE,
            'input_snapshot' => [
                'status' => Week9EconomicEvaluation::STATUS_UNAVAILABLE_REFERENCE_PACKAGE,
                'decision_submission_id' => $submission->id,
            ],
            'output_snapshot' => [
                'status' => Week9EconomicEvaluation::STATUS_UNAVAILABLE_REFERENCE_PACKAGE,
                'reason' => $reason,
            ],
            'unavailable_reason' => $reason,
            'evaluated_by_user_id' => $actor->id,
            'evaluated_by_process' => $process,
            'evaluated_at' => Carbon::now(),
        ]);
    }

    /**
     * @return list<string>
     */
    private function selectedRebrandMarkets(DecisionSubmission $submission, Week9EconomicInputs $inputs): array
    {
        $answers = $this->answers($submission);
        $markets = $answers['rebrand_markets'] ?? null;

        if (is_array($markets)) {
            return $this->validateMarketKeys(array_values(array_unique(array_map('strval', $markets))), $inputs);
        }

        $marketDecisions = $answers['market_decisions'] ?? null;

        if (is_array($marketDecisions)) {
            $selected = [];
            foreach ($marketDecisions as $marketKey => $decision) {
                if ((string) $decision === 'rebrand') {
                    $selected[] = (string) $marketKey;
                }
            }

            return $this->validateMarketKeys($selected, $inputs);
        }

        $selected = [];
        foreach (array_keys($inputs->markets) as $marketKey) {
            $answerKey = 'rebrand_'.$marketKey;

            if (array_key_exists($answerKey, $answers) && filter_var($answers[$answerKey], FILTER_VALIDATE_BOOLEAN)) {
                $selected[] = $marketKey;
            }
        }

        if ($selected !== []) {
            return $this->validateMarketKeys($selected, $inputs);
        }

        throw new InvalidArgumentException('Week 9 decision submission is missing selected rebrand markets.');
    }

    private function nonfuelStateKey(DecisionSubmission $submission): string
    {
        $answers = $this->answers($submission);
        $state = $answers['nonfuel_state_key'] ?? $answers['nonfuel_state'] ?? 'base';

        return (string) $state;
    }

    /**
     * @param  list<string>  $marketKeys
     * @return list<string>
     */
    private function validateMarketKeys(array $marketKeys, Week9EconomicInputs $inputs): array
    {
        foreach ($marketKeys as $marketKey) {
            $inputs->market($marketKey);
        }

        return $marketKeys;
    }

    /**
     * @return array<string, mixed>|null
     */
    private function window3HandoffSnapshot(DecisionSubmission $submission): ?array
    {
        $effect = CohortFeedbackEffect::query()
            ->where('tenant_id', $submission->tenant_id)
            ->where('section_simulation_id', $submission->section_simulation_id)
            ->where('target_section_simulation_week_id', $submission->section_simulation_week_id)
            ->where('effect_key', Window3CohortResponseFunctionCatalog::EFFECT_KEY)
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
            'base_nonfuel_margin' => $parameters['base_nonfuel'] ?? $response['parallel_universe_baseline'] ?? null,
            'final_nonfuel_margin' => $response['bounded_value'] ?? null,
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
        if ($submission->runtimeWeek->definition->week_number !== 9) {
            throw new InvalidArgumentException('Week 9 economic evaluation can only evaluate Week 9 decision submissions.');
        }

        if ($submission->statusValue() !== SubmissionStatus::Submitted->value) {
            throw new InvalidArgumentException('Week 9 economic evaluation requires a submitted decision.');
        }

        if ($actor->tenant_id !== $submission->tenant_id) {
            throw new InvalidArgumentException('Actor cannot evaluate Week 9 economics for another tenant.');
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

        throw new InvalidArgumentException('Only authorized faculty can evaluate Week 9 economics.');
    }
}
