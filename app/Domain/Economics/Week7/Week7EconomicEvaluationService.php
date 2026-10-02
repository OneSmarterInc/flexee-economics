<?php

namespace App\Domain\Economics\Week7;

use App\Enums\SubmissionStatus;
use App\Models\DecisionSubmission;
use App\Models\User;
use App\Models\Week7EconomicEvaluation;
use Brick\Math\BigDecimal;
use Brick\Math\RoundingMode;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

final readonly class Week7EconomicEvaluationService
{
    public const PACKAGE_IDENTIFIER = 'halden-week7-data-package';

    public function __construct(
        private Week7EconomicEngine $engine,
    ) {}

    public function evaluate(
        DecisionSubmission $submission,
        User $actor,
        ?Week7ReferencePackage $package = null,
        string $process = 'week7_economic_evaluation_service',
    ): Week7EconomicEvaluation {
        $submission->loadMissing(['runtimeWeek.definition']);
        $this->assertCanEvaluate($actor, $submission);

        return DB::transaction(function () use ($submission, $actor, $package, $process): Week7EconomicEvaluation {
            $existing = Week7EconomicEvaluation::query()
                ->where('tenant_id', $submission->tenant_id)
                ->where('decision_submission_id', $submission->id)
                ->where('engine_identifier', Week7EconomicEngine::ENGINE_IDENTIFIER)
                ->lockForUpdate()
                ->first();

            if ($existing instanceof Week7EconomicEvaluation) {
                return $existing;
            }

            $package ??= Week7ReferencePackage::fromRepository();

            if (! $package->isAvailable()) {
                return $this->createUnavailableEvaluation($submission, $actor, $package, $process);
            }

            $result = $this->engine->calculate($package->inputs());

            return Week7EconomicEvaluation::query()->create([
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
                'status' => Week7EconomicEvaluation::STATUS_CALCULATED,
                'ev_hold_musd' => $this->decimal($result->evHoldMusd),
                'ev_match_musd' => $this->decimal($result->evMatchMusd),
                'breakeven_build_probability' => $this->decimal($result->breakevenBuildProbability),
                'capacity_decision' => $result->capacityDecision,
                'cluster_results' => array_map(fn (Week7ClusterResult $cluster): array => $cluster->snapshot(), $result->clusterResults),
                'window3_snapshot' => $result->windowResults,
                'worked_example_snapshot' => $this->formatDecimals($result->workedExample),
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
        Week7ReferencePackage $package,
        string $process,
    ): Week7EconomicEvaluation {
        $reason = $package->unavailableReason() ?? Week7ReferencePackage::MISSING_REASON;

        return Week7EconomicEvaluation::query()->create([
            'tenant_id' => $submission->tenant_id,
            'section_simulation_id' => $submission->section_simulation_id,
            'section_simulation_week_id' => $submission->section_simulation_week_id,
            'team_simulation_id' => $submission->team_simulation_id,
            'team_id' => $submission->team_id,
            'decision_submission_id' => $submission->id,
            'engine_identifier' => Week7EconomicEngine::ENGINE_IDENTIFIER,
            'engine_version' => Week7EconomicEngine::ENGINE_VERSION,
            'package_identifier' => self::PACKAGE_IDENTIFIER,
            'package_version' => $package->version(),
            'status' => Week7EconomicEvaluation::STATUS_UNAVAILABLE_REFERENCE_PACKAGE,
            'input_snapshot' => ['status' => Week7EconomicEvaluation::STATUS_UNAVAILABLE_REFERENCE_PACKAGE],
            'output_snapshot' => ['status' => Week7EconomicEvaluation::STATUS_UNAVAILABLE_REFERENCE_PACKAGE, 'reason' => $reason],
            'unavailable_reason' => $reason,
            'evaluated_by_user_id' => $actor->id,
            'evaluated_by_process' => $process,
            'evaluated_at' => Carbon::now(),
        ]);
    }

    /**
     * @param  array<string, BigDecimal>  $values
     * @return array<string, string>
     */
    private function formatDecimals(array $values): array
    {
        return array_map(fn (BigDecimal $value): string => $this->decimal($value), $values);
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
        if ($submission->runtimeWeek->definition->week_number !== 7) {
            throw new InvalidArgumentException('Week 7 economic evaluation can only evaluate Week 7 decision submissions.');
        }

        if ($submission->statusValue() !== SubmissionStatus::Submitted->value) {
            throw new InvalidArgumentException('Week 7 economic evaluation requires a submitted decision.');
        }

        if ($actor->tenant_id !== $submission->tenant_id) {
            throw new InvalidArgumentException('Actor cannot evaluate Week 7 economics for another tenant.');
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

        throw new InvalidArgumentException('Only authorized faculty can evaluate Week 7 economics.');
    }
}
