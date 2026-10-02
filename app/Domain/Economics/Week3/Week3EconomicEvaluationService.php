<?php

namespace App\Domain\Economics\Week3;

use App\Enums\SubmissionStatus;
use App\Models\DecisionSubmission;
use App\Models\User;
use App\Models\Week3EconomicEvaluation;
use Brick\Math\BigDecimal;
use Brick\Math\RoundingMode;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

final readonly class Week3EconomicEvaluationService
{
    public const PACKAGE_IDENTIFIER = 'halden-week3-data-package';

    public function __construct(
        private Week3EconomicEngine $engine,
    ) {}

    public function evaluate(
        DecisionSubmission $submission,
        User $actor,
        ?Week3ReferencePackage $package = null,
        string $process = 'week3_economic_evaluation_service',
    ): Week3EconomicEvaluation {
        $submission->loadMissing(['runtimeWeek.definition']);
        $this->assertCanEvaluate($actor, $submission);

        return DB::transaction(function () use ($submission, $actor, $package, $process): Week3EconomicEvaluation {
            $existing = Week3EconomicEvaluation::query()
                ->where('tenant_id', $submission->tenant_id)
                ->where('decision_submission_id', $submission->id)
                ->where('engine_identifier', Week3EconomicEngine::ENGINE_IDENTIFIER)
                ->lockForUpdate()
                ->first();

            if ($existing instanceof Week3EconomicEvaluation) {
                return $existing;
            }

            $package ??= Week3ReferencePackage::fromRepository();

            if (! $package->isAvailable()) {
                return $this->createUnavailableEvaluation($submission, $actor, $package, $process);
            }

            $inputs = $package->inputs();
            $result = $this->engine->calculate($inputs);
            $rotterdam = $result->refineryResults['rotterdam'];

            return Week3EconomicEvaluation::query()->create([
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
                'status' => Week3EconomicEvaluation::STATUS_CALCULATED,
                'rotterdam_contribution' => $this->decimal($rotterdam->contribution),
                'rotterdam_net' => $this->decimal($rotterdam->net),
                'rotterdam_shutdown_crack' => $this->decimal($rotterdam->shutdownCrack),
                'rotterdam_idle_delta' => $this->decimal($result->rotterdamIdleDelta),
                'window1_snapshot' => $result->windowResults,
                'decision_snapshot' => [
                    'id' => $submission->id,
                    'answers' => $this->submissionAnswers($submission),
                ],
                'input_snapshot' => $result->inputSnapshot,
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
        Week3ReferencePackage $package,
        string $process,
    ): Week3EconomicEvaluation {
        $reason = $package->unavailableReason() ?? Week3ReferencePackage::MISSING_REASON;

        return Week3EconomicEvaluation::query()->create([
            'tenant_id' => $submission->tenant_id,
            'section_simulation_id' => $submission->section_simulation_id,
            'section_simulation_week_id' => $submission->section_simulation_week_id,
            'team_simulation_id' => $submission->team_simulation_id,
            'team_id' => $submission->team_id,
            'decision_submission_id' => $submission->id,
            'engine_identifier' => Week3EconomicEngine::ENGINE_IDENTIFIER,
            'engine_version' => Week3EconomicEngine::ENGINE_VERSION,
            'package_identifier' => self::PACKAGE_IDENTIFIER,
            'package_version' => $package->version(),
            'status' => Week3EconomicEvaluation::STATUS_UNAVAILABLE_REFERENCE_PACKAGE,
            'input_snapshot' => ['status' => Week3EconomicEvaluation::STATUS_UNAVAILABLE_REFERENCE_PACKAGE],
            'output_snapshot' => ['status' => Week3EconomicEvaluation::STATUS_UNAVAILABLE_REFERENCE_PACKAGE, 'reason' => $reason],
            'unavailable_reason' => $reason,
            'evaluated_by_user_id' => $actor->id,
            'evaluated_by_process' => $process,
            'evaluated_at' => Carbon::now(),
        ]);
    }

    private function decimal(BigDecimal $value): string
    {
        return (string) $value->toScale(6, RoundingMode::HalfUp);
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
        if ($submission->runtimeWeek->definition->week_number !== 3) {
            throw new InvalidArgumentException('Week 3 economic evaluation can only evaluate Week 3 decision submissions.');
        }

        if ($submission->statusValue() !== SubmissionStatus::Submitted->value) {
            throw new InvalidArgumentException('Week 3 economic evaluation requires a submitted decision.');
        }

        if ($actor->tenant_id !== $submission->tenant_id) {
            throw new InvalidArgumentException('Actor cannot evaluate Week 3 economics for another tenant.');
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

        throw new InvalidArgumentException('Only authorized faculty can evaluate Week 3 economics.');
    }
}
