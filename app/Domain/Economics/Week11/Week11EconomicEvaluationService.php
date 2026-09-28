<?php

namespace App\Domain\Economics\Week11;

use App\Enums\SubmissionStatus;
use App\Models\DecisionSubmission;
use App\Models\User;
use App\Models\Week11EconomicEvaluation;
use Brick\Math\BigDecimal;
use Brick\Math\RoundingMode;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

final readonly class Week11EconomicEvaluationService
{
    public function __construct(
        private Week11EconomicEngine $engine,
    ) {}

    public function evaluate(
        DecisionSubmission $submission,
        User $actor,
        ?Week11ReferencePackage $package = null,
        string $process = 'week11_economic_evaluation_service',
    ): Week11EconomicEvaluation {
        $submission->loadMissing(['runtimeWeek.definition', 'definition']);
        $this->assertCanEvaluate($actor, $submission);

        return DB::transaction(function () use ($submission, $actor, $package, $process): Week11EconomicEvaluation {
            $existing = Week11EconomicEvaluation::query()
                ->where('tenant_id', $submission->tenant_id)
                ->where('decision_submission_id', $submission->id)
                ->where('engine_identifier', Week11EconomicEngine::ENGINE_IDENTIFIER)
                ->lockForUpdate()
                ->first();

            if ($existing instanceof Week11EconomicEvaluation) {
                return $existing;
            }

            $package ??= Week11ReferencePackage::fromRepository();

            if (! $package->isAvailable()) {
                return $this->createUnavailableEvaluation($submission, $actor, $package, $process);
            }

            $inputs = $package->inputs();
            $result = $this->engine->calculate($inputs);

            return Week11EconomicEvaluation::query()->create([
                'tenant_id' => $submission->tenant_id,
                'section_simulation_id' => $submission->section_simulation_id,
                'section_simulation_week_id' => $submission->section_simulation_week_id,
                'team_simulation_id' => $submission->team_simulation_id,
                'team_id' => $submission->team_id,
                'decision_submission_id' => $submission->id,
                'engine_identifier' => $result->engineIdentifier,
                'engine_version' => $result->engineVersion,
                'package_version' => $result->packageVersion,
                'status' => Week11EconomicEvaluation::STATUS_CALCULATED,
                'realized_price' => $this->decimal($result->realizedPrice, 6),
                'profit_oil' => $this->decimal($result->profitOil, 6),
                'annual_mbbl' => $this->decimal($result->annualMbbl, 6),
                'annuity_factor' => $this->decimal($result->annuityFactor, 6),
                'margin_current' => $this->decimal($result->takeResult('current')->companyMarginPerBbl, 6),
                'pv_stay_current_musd' => $this->decimal($result->takeResult('current')->pvStayMusd, 6),
                'margin_mid' => $this->decimal($result->takeResult('mid')->companyMarginPerBbl, 6),
                'pv_stay_mid_musd' => $this->decimal($result->takeResult('mid')->pvStayMusd, 6),
                'margin_demanded' => $this->decimal($result->takeResult('demanded')->companyMarginPerBbl, 6),
                'pv_stay_demanded_musd' => $this->decimal($result->takeResult('demanded')->pvStayMusd, 6),
                'margin_harsh' => $this->decimal($result->takeResult('harsh')->companyMarginPerBbl, 6),
                'pv_stay_harsh_musd' => $this->decimal($result->takeResult('harsh')->pvStayMusd, 6),
                'exit_value_musd' => $this->decimal($result->exitValueMusd, 6),
                'stay_minus_exit_demanded_musd' => $this->decimal($result->takeResult('demanded')->stayMinusExitMusd, 6),
                'indifference_take' => $this->decimal($result->indifferenceTake, 6),
                'comparables_min' => $this->decimal($result->comparablesMin, 6),
                'comparables_max' => $this->decimal($result->comparablesMax, 6),
                'demanded_take' => $this->decimal($result->demandedTake, 6),
                'demanded_take_inside_comparables' => $result->demandedTakeInsideComparables,
                'staying_beats_exit_across_take_grid' => $result->stayingBeatsExitAcrossTakeGrid,
                'stay_value_falls_as_take_rises' => $result->stayValueFallsAsTakeRises,
                'sunk_invariant' => $result->sunkInvariant,
                'take_results' => array_map(fn (Week11TakeResult $takeResult): array => $takeResult->snapshot(), $result->takeResults),
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
        Week11ReferencePackage $package,
        string $process,
    ): Week11EconomicEvaluation {
        $reason = $package->unavailableReason() ?? Week11ReferencePackage::MISSING_REASON;

        return Week11EconomicEvaluation::query()->create([
            'tenant_id' => $submission->tenant_id,
            'section_simulation_id' => $submission->section_simulation_id,
            'section_simulation_week_id' => $submission->section_simulation_week_id,
            'team_simulation_id' => $submission->team_simulation_id,
            'team_id' => $submission->team_id,
            'decision_submission_id' => $submission->id,
            'engine_identifier' => Week11EconomicEngine::ENGINE_IDENTIFIER,
            'engine_version' => Week11EconomicEngine::ENGINE_VERSION,
            'package_version' => $package->version(),
            'status' => Week11EconomicEvaluation::STATUS_UNAVAILABLE_REFERENCE_PACKAGE,
            'input_snapshot' => [
                'status' => Week11EconomicEvaluation::STATUS_UNAVAILABLE_REFERENCE_PACKAGE,
                'decision_submission_id' => $submission->id,
            ],
            'output_snapshot' => [
                'status' => Week11EconomicEvaluation::STATUS_UNAVAILABLE_REFERENCE_PACKAGE,
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
        if ($submission->runtimeWeek->definition->week_number !== 11) {
            throw new InvalidArgumentException('Week 11 economic evaluation can only evaluate Week 11 decision submissions.');
        }

        if ($submission->statusValue() !== SubmissionStatus::Submitted->value) {
            throw new InvalidArgumentException('Week 11 economic evaluation requires a submitted decision.');
        }

        if ($actor->tenant_id !== $submission->tenant_id) {
            throw new InvalidArgumentException('Actor cannot evaluate Week 11 economics for another tenant.');
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

        throw new InvalidArgumentException('Only authorized faculty can evaluate Week 11 economics.');
    }
}
