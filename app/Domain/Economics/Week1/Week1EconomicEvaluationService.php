<?php

namespace App\Domain\Economics\Week1;

use App\Enums\SubmissionStatus;
use App\Models\DecisionSubmission;
use App\Models\User;
use App\Models\Week1EconomicEvaluation;
use Brick\Math\BigDecimal;
use Brick\Math\RoundingMode;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

final readonly class Week1EconomicEvaluationService
{
    public const PACKAGE_IDENTIFIER = 'halden-week1-data-package';

    public function __construct(
        private Week1EconomicEngine $engine,
    ) {}

    public function evaluate(
        DecisionSubmission $submission,
        User $actor,
        ?Week1ReferencePackage $package = null,
        string $process = 'week1_economic_evaluation_service',
    ): Week1EconomicEvaluation {
        $submission->loadMissing(['runtimeWeek.definition', 'definition']);
        $this->assertCanEvaluate($actor, $submission);

        return DB::transaction(function () use ($submission, $actor, $package, $process): Week1EconomicEvaluation {
            $existing = Week1EconomicEvaluation::query()
                ->where('tenant_id', $submission->tenant_id)
                ->where('decision_submission_id', $submission->id)
                ->where('engine_identifier', Week1EconomicEngine::ENGINE_IDENTIFIER)
                ->lockForUpdate()
                ->first();

            if ($existing instanceof Week1EconomicEvaluation) {
                return $existing;
            }

            $package ??= Week1ReferencePackage::fromRepository();

            if (! $package->isAvailable()) {
                return $this->createUnavailableEvaluation($submission, $actor, $package, $process);
            }

            $inputs = $package->inputs();
            $result = $this->engine->calculate($inputs);

            return Week1EconomicEvaluation::query()->create([
                'tenant_id' => $submission->tenant_id,
                'section_simulation_id' => $submission->section_simulation_id,
                'section_simulation_week_id' => $submission->section_simulation_week_id,
                'team_simulation_id' => $submission->team_simulation_id,
                'team_id' => $submission->team_id,
                'decision_submission_id' => $submission->id,
                'engine_identifier' => $result->engineIdentifier,
                'engine_version' => $result->engineVersion,
                'package_identifier' => self::PACKAGE_IDENTIFIER,
                'package_version' => $result->packageVersion,
                'status' => Week1EconomicEvaluation::STATUS_CALCULATED,
                'permian_realized' => $this->decimal($result->permianRealized, 6),
                'permian_margin' => $this->decimal($result->permianMargin, 6),
                'norway_pretax' => $this->decimal($result->norwayPretax, 6),
                'norway_posttax' => $this->decimal($result->norwayPosttax, 6),
                'norway_2usd_loss_posttax' => $this->decimal($result->norwayTwoUsdLossPosttax, 6),
                'kessana_company' => $this->decimal($result->kessanaCompany, 6),
                'br_net' => $this->decimal($result->batonRougeNet, 6),
                'rot_contribution' => $this->decimal($result->rotterdamContribution, 6),
                'rot_net' => $this->decimal($result->rotterdamNet, 6),
                'rot_shutdown_crack' => $this->decimal($result->rotterdamShutdownCrack, 6),
                'rot_current_crack' => $this->decimal($result->rotterdamCurrentCrack, 6),
                'sing_halden_share' => $this->decimal($result->singaporeHaldenShare, 6),
                'economic_rank' => $result->economicRank,
                'reported_rank' => $result->reportedRank,
                'top_economic_asset' => $result->topEconomicAsset,
                'asset_economic_values' => $result->assetEconomicValues,
                'reported_profit_values' => $result->reportedProfitValues,
                'ordering_assertions' => $result->orderingAssertions,
                'worked_example_snapshot' => $result->workedExample,
                'input_snapshot' => [
                    ...$result->inputSnapshot,
                    'decision_submission' => [
                        'id' => $submission->id,
                        'decision_form_definition_id' => $submission->decision_form_definition_id,
                        'definition_key' => $submission->definition->key,
                        'definition_version' => $submission->definition->version,
                        'answers' => $this->submissionAnswers($submission),
                    ],
                ],
                'output_snapshot' => $result->outputSnapshot,
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
        Week1ReferencePackage $package,
        string $process,
    ): Week1EconomicEvaluation {
        $reason = $package->unavailableReason() ?? Week1ReferencePackage::MISSING_REASON;

        return Week1EconomicEvaluation::query()->create([
            'tenant_id' => $submission->tenant_id,
            'section_simulation_id' => $submission->section_simulation_id,
            'section_simulation_week_id' => $submission->section_simulation_week_id,
            'team_simulation_id' => $submission->team_simulation_id,
            'team_id' => $submission->team_id,
            'decision_submission_id' => $submission->id,
            'engine_identifier' => Week1EconomicEngine::ENGINE_IDENTIFIER,
            'engine_version' => Week1EconomicEngine::ENGINE_VERSION,
            'package_identifier' => self::PACKAGE_IDENTIFIER,
            'package_version' => $package->version(),
            'status' => Week1EconomicEvaluation::STATUS_UNAVAILABLE_REFERENCE_PACKAGE,
            'input_snapshot' => [
                'status' => Week1EconomicEvaluation::STATUS_UNAVAILABLE_REFERENCE_PACKAGE,
                'decision_submission_id' => $submission->id,
            ],
            'output_snapshot' => [
                'status' => Week1EconomicEvaluation::STATUS_UNAVAILABLE_REFERENCE_PACKAGE,
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

    /**
     * @return array<string, mixed>
     */
    private function submissionAnswers(DecisionSubmission $submission): array
    {
        $answers = $submission->getAttribute('answers');

        return is_array($answers) ? $answers : [];
    }

    private function assertCanEvaluate(User $actor, DecisionSubmission $submission): void
    {
        if ($submission->runtimeWeek->definition->week_number !== 1) {
            throw new InvalidArgumentException('Week 1 economic evaluation can only evaluate Week 1 decision submissions.');
        }

        if ($submission->statusValue() !== SubmissionStatus::Submitted->value) {
            throw new InvalidArgumentException('Week 1 economic evaluation requires a submitted decision.');
        }

        if ($actor->tenant_id !== $submission->tenant_id) {
            throw new InvalidArgumentException('Actor cannot evaluate Week 1 economics for another tenant.');
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

        throw new InvalidArgumentException('Only authorized faculty can evaluate Week 1 economics.');
    }
}
