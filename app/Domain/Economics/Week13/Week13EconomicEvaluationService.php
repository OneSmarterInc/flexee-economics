<?php

namespace App\Domain\Economics\Week13;

use App\Enums\SubmissionStatus;
use App\Models\DecisionSubmission;
use App\Models\User;
use App\Models\Week13EconomicEvaluation;
use Brick\Math\BigDecimal;
use Brick\Math\RoundingMode;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

final readonly class Week13EconomicEvaluationService
{
    public const PACKAGE_IDENTIFIER = 'halden-week13-data-package';

    public function __construct(
        private Week13EconomicEngine $engine,
    ) {}

    public function evaluate(
        DecisionSubmission $submission,
        User $actor,
        ?Week13ReferencePackage $package = null,
        string $process = 'week13_economic_evaluation_service',
    ): Week13EconomicEvaluation {
        $submission->loadMissing(['runtimeWeek.definition', 'definition']);
        $this->assertCanEvaluate($actor, $submission);

        return DB::transaction(function () use ($submission, $actor, $package, $process): Week13EconomicEvaluation {
            $existing = Week13EconomicEvaluation::query()
                ->where('tenant_id', $submission->tenant_id)
                ->where('decision_submission_id', $submission->id)
                ->where('engine_identifier', Week13EconomicEngine::ENGINE_IDENTIFIER)
                ->lockForUpdate()
                ->first();

            if ($existing instanceof Week13EconomicEvaluation) {
                return $existing;
            }

            $package ??= Week13ReferencePackage::fromRepository();

            if (! $package->isAvailable()) {
                return $this->createUnavailableEvaluation($submission, $actor, $package, $process);
            }

            $inputs = $package->inputs();
            $result = $this->engine->calculate($inputs);

            return Week13EconomicEvaluation::query()->create([
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
                'status' => Week13EconomicEvaluation::STATUS_CALCULATED,
                'norway_gross_cost_musd' => $this->decimal($result->norwayGrossCostMusd, 6),
                'norway_after_tax_cost_musd' => $this->decimal($result->norwayAfterTaxCostMusd, 6),
                'norway_after_tax_share' => $this->decimal($result->norwayAfterTaxShare, 6),
                'permian_mrp_k' => $this->decimal($result->permianMrpK, 6),
                'mrp_to_wage' => $this->decimal($result->mrpToWage, 6),
                'turnaround_peak_cost_musd' => $this->decimal($result->turnaroundPeakCostMusd, 6),
                'delay_expected_cost_musd' => $this->decimal($result->delayExpectedCostMusd, 6),
                'delay_saving_musd' => $this->decimal($result->delaySavingMusd, 6),
                'delay_saving_pct' => $this->decimal($result->delaySavingPct, 6),
                'asset_health_penalty_pts' => $this->decimal($result->assetHealthPenaltyPts, 6),
                'wage_benchmarks' => array_map(fn (array $benchmark): array => [
                    'structure' => $benchmark['structure'],
                    'benchmark_wage_k' => $this->decimal($benchmark['benchmark_wage_k'], 6),
                ], $result->wageBenchmarks),
                'ordering_assertions' => $result->orderingAssertions,
                'worked_example_snapshot' => $this->formatDecimals($result->workedExample, 6),
                'input_snapshot' => [
                    ...$result->inputSnapshot,
                    'decision_submission' => [
                        'id' => $submission->id,
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
        Week13ReferencePackage $package,
        string $process,
    ): Week13EconomicEvaluation {
        $reason = $package->unavailableReason() ?? Week13ReferencePackage::MISSING_REASON;

        return Week13EconomicEvaluation::query()->create([
            'tenant_id' => $submission->tenant_id,
            'section_simulation_id' => $submission->section_simulation_id,
            'section_simulation_week_id' => $submission->section_simulation_week_id,
            'team_simulation_id' => $submission->team_simulation_id,
            'team_id' => $submission->team_id,
            'decision_submission_id' => $submission->id,
            'engine_identifier' => Week13EconomicEngine::ENGINE_IDENTIFIER,
            'engine_version' => Week13EconomicEngine::ENGINE_VERSION,
            'package_identifier' => self::PACKAGE_IDENTIFIER,
            'package_version' => $package->version(),
            'status' => Week13EconomicEvaluation::STATUS_UNAVAILABLE_REFERENCE_PACKAGE,
            'input_snapshot' => [
                'status' => Week13EconomicEvaluation::STATUS_UNAVAILABLE_REFERENCE_PACKAGE,
                'decision_submission_id' => $submission->id,
            ],
            'output_snapshot' => [
                'status' => Week13EconomicEvaluation::STATUS_UNAVAILABLE_REFERENCE_PACKAGE,
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
     * @param  array<string, BigDecimal>  $values
     * @param  int<0, max>  $scale
     * @return array<string, string>
     */
    private function formatDecimals(array $values, int $scale): array
    {
        return array_map(
            fn (BigDecimal $value): string => $this->decimal($value, $scale),
            $values,
        );
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
        if ($submission->runtimeWeek->definition->week_number !== 13) {
            throw new InvalidArgumentException('Week 13 economic evaluation can only evaluate Week 13 decision submissions.');
        }

        if ($submission->statusValue() !== SubmissionStatus::Submitted->value) {
            throw new InvalidArgumentException('Week 13 economic evaluation requires a submitted decision.');
        }

        if ($actor->tenant_id !== $submission->tenant_id) {
            throw new InvalidArgumentException('Actor cannot evaluate Week 13 economics for another tenant.');
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

        throw new InvalidArgumentException('Only authorized faculty can evaluate Week 13 economics.');
    }
}
