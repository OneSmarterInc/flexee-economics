<?php

namespace App\Domain\Economics\Week2;

use App\Enums\SubmissionStatus;
use App\Models\DecisionSubmission;
use App\Models\User;
use App\Models\Week2EconomicEvaluation;
use Brick\Math\BigDecimal;
use Brick\Math\RoundingMode;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

final readonly class Week2EconomicEvaluationService
{
    public const PACKAGE_IDENTIFIER = 'halden-week2-data-package';

    public function __construct(
        private Week2EconomicEngine $engine,
    ) {}

    public function evaluate(
        DecisionSubmission $submission,
        User $actor,
        ?Week2ReferencePackage $package = null,
        string $process = 'week2_economic_evaluation_service',
    ): Week2EconomicEvaluation {
        $submission->loadMissing(['runtimeWeek.definition', 'definition']);
        $this->assertCanEvaluate($actor, $submission);

        return DB::transaction(function () use ($submission, $actor, $package, $process): Week2EconomicEvaluation {
            $existing = Week2EconomicEvaluation::query()
                ->where('tenant_id', $submission->tenant_id)
                ->where('decision_submission_id', $submission->id)
                ->where('engine_identifier', Week2EconomicEngine::ENGINE_IDENTIFIER)
                ->lockForUpdate()
                ->first();

            if ($existing instanceof Week2EconomicEvaluation) {
                return $existing;
            }

            $package ??= Week2ReferencePackage::fromRepository();

            if (! $package->isAvailable()) {
                return $this->createUnavailableEvaluation($submission, $actor, $package, $process);
            }

            $result = $this->engine->calculate($package->inputs());

            return Week2EconomicEvaluation::query()->create([
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
                'status' => Week2EconomicEvaluation::STATUS_CALCULATED,
                'cordell_weighted_est' => $this->decimal($result->cordellWeightedEstimate),
                'europe_weighted_est' => $this->decimal($result->europeWeightedEstimate),
                'cordell_weighted_passthrough' => $this->decimal($result->cordellWeightedPassthrough),
                'urban_high_comp_volume_response_pct' => $this->decimal($result->clusterResults['urban_high_comp']->volumeResponsePct),
                'rural_low_comp_volume_response_pct' => $this->decimal($result->clusterResults['rural_low_comp']->volumeResponsePct),
                'worked_elasticity' => $this->decimal($result->workedExample['worked_elasticity']),
                'worked_vol_response_pct' => $this->decimal($result->workedExample['worked_vol_response_pct']),
                'cluster_results' => array_map(fn (Week2ClusterResult $cluster): array => $cluster->snapshot(), $result->clusterResults),
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
        Week2ReferencePackage $package,
        string $process,
    ): Week2EconomicEvaluation {
        $reason = $package->unavailableReason() ?? Week2ReferencePackage::MISSING_REASON;

        return Week2EconomicEvaluation::query()->create([
            'tenant_id' => $submission->tenant_id,
            'section_simulation_id' => $submission->section_simulation_id,
            'section_simulation_week_id' => $submission->section_simulation_week_id,
            'team_simulation_id' => $submission->team_simulation_id,
            'team_id' => $submission->team_id,
            'decision_submission_id' => $submission->id,
            'engine_identifier' => Week2EconomicEngine::ENGINE_IDENTIFIER,
            'engine_version' => Week2EconomicEngine::ENGINE_VERSION,
            'package_identifier' => self::PACKAGE_IDENTIFIER,
            'package_version' => $package->version(),
            'status' => Week2EconomicEvaluation::STATUS_UNAVAILABLE_REFERENCE_PACKAGE,
            'input_snapshot' => [
                'status' => Week2EconomicEvaluation::STATUS_UNAVAILABLE_REFERENCE_PACKAGE,
                'decision_submission_id' => $submission->id,
            ],
            'output_snapshot' => [
                'status' => Week2EconomicEvaluation::STATUS_UNAVAILABLE_REFERENCE_PACKAGE,
                'reason' => $reason,
            ],
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
     * @param  array<string, BigDecimal>  $values
     * @return array<string, string>
     */
    private function formatDecimals(array $values): array
    {
        return array_map(fn (BigDecimal $value): string => $this->decimal($value), $values);
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
        if ($submission->runtimeWeek->definition->week_number !== 2) {
            throw new InvalidArgumentException('Week 2 economic evaluation can only evaluate Week 2 decision submissions.');
        }

        if ($submission->statusValue() !== SubmissionStatus::Submitted->value) {
            throw new InvalidArgumentException('Week 2 economic evaluation requires a submitted decision.');
        }

        if ($actor->tenant_id !== $submission->tenant_id) {
            throw new InvalidArgumentException('Actor cannot evaluate Week 2 economics for another tenant.');
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

        throw new InvalidArgumentException('Only authorized faculty can evaluate Week 2 economics.');
    }
}
