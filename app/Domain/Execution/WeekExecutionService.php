<?php

namespace App\Domain\Execution;

use App\Domain\Capital\Week6\Week6CapitalEconomicsService;
use App\Domain\CohortFeedback\CohortFeedbackService;
use App\Domain\Content\SimulationContentResolver;
use App\Domain\Economics\Resolution\WeekResolutionService;
use App\Domain\Economics\Week1\Week1EconomicEvaluationService;
use App\Domain\Economics\Week10\Week10EconomicEvaluationService;
use App\Domain\Economics\Week11\Week11EconomicEvaluationService;
use App\Domain\Economics\Week12\Week12EconomicEvaluationService;
use App\Domain\Economics\Week13\Week13EconomicEvaluationService;
use App\Domain\Economics\Week2\Week2EconomicEvaluationService;
use App\Domain\Economics\Week3\Week3EconomicEvaluationService;
use App\Domain\Economics\Week5\Week5EconomicEvaluationService;
use App\Domain\Economics\Week7\Week7EconomicEvaluationService;
use App\Domain\Economics\Week8\Week8EconomicEvaluationService;
use App\Domain\Economics\Week9\Week9EconomicEvaluationService;
use App\Domain\Ranking\RankingCalculationService;
use App\Domain\Scoring\Week10KpiPopulationService;
use App\Domain\Scoring\Week11KpiPopulationService;
use App\Domain\Scoring\Week4KpiPopulationService;
use App\Domain\Scoring\Week8KpiPopulationService;
use App\Enums\SubmissionStatus;
use App\Models\CapitalAllocationDecision;
use App\Models\CohortResponseFunction;
use App\Models\DecisionSubmission;
use App\Models\EconomicResolution;
use App\Models\SectionSimulationWeek;
use App\Models\User;
use App\Models\Week10EconomicEvaluation;
use App\Models\Week11EconomicEvaluation;
use App\Models\Week8EconomicEvaluation;
use App\Models\WeekExecutionRecord;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use Throwable;

final readonly class WeekExecutionService
{
    public const EXECUTION_VERSION = 'week_execution_v1';

    /**
     * @var list<string>
     */
    private const STEPS = [
        'validate_content_package',
        'lock_submissions',
        'resolve_decisions',
        'apply_kpi_calculations',
        'calculate_rankings',
        'generate_consequences',
        'apply_cohort_effects',
        'publish_allowed_outputs',
    ];

    public function __construct(
        private SimulationContentResolver $contentResolver,
        private CohortFeedbackService $cohortFeedback,
        private WeekResolutionService $weekResolution,
        private Week1EconomicEvaluationService $week1Economics,
        private Week2EconomicEvaluationService $week2Economics,
        private Week3EconomicEvaluationService $week3Economics,
        private Week6CapitalEconomicsService $week6CapitalEconomics,
        private Week5EconomicEvaluationService $week5Economics,
        private Week7EconomicEvaluationService $week7Economics,
        private Week8EconomicEvaluationService $week8Economics,
        private Week9EconomicEvaluationService $week9Economics,
        private Week10EconomicEvaluationService $week10Economics,
        private Week11EconomicEvaluationService $week11Economics,
        private Week12EconomicEvaluationService $week12Economics,
        private Week13EconomicEvaluationService $week13Economics,
        private Week4KpiPopulationService $week4Kpis,
        private Week8KpiPopulationService $week8Kpis,
        private Week10KpiPopulationService $week10Kpis,
        private Week11KpiPopulationService $week11Kpis,
        private RankingCalculationService $rankings,
    ) {}

    /**
     * @param  array<string, string>  $forcedFailures
     */
    public function execute(
        SectionSimulationWeek $runtimeWeek,
        User $actor,
        string $packageType = 'reference_package',
        string $executionVersion = self::EXECUTION_VERSION,
        array $forcedFailures = [],
    ): WeekExecutionRecord {
        $runtimeWeek->loadMissing(['definition', 'sectionSimulation']);
        $this->assertCanExecute($actor, $runtimeWeek);

        return DB::transaction(function () use ($runtimeWeek, $actor, $packageType, $executionVersion, $forcedFailures): WeekExecutionRecord {
            $existing = WeekExecutionRecord::query()
                ->where('tenant_id', $runtimeWeek->tenant_id)
                ->where('section_simulation_week_id', $runtimeWeek->id)
                ->where('execution_version', $executionVersion)
                ->lockForUpdate()
                ->first();

            if ($existing instanceof WeekExecutionRecord) {
                if ($existing->status === WeekExecutionRecord::STATUS_FAILED) {
                    $executionVersion = $this->retryExecutionVersion($runtimeWeek, $executionVersion);
                } else {
                    throw new InvalidArgumentException('This runtime week has already been executed for the requested execution version.');
                }
            }

            $record = WeekExecutionRecord::query()->create([
                'tenant_id' => $runtimeWeek->tenant_id,
                'section_simulation_id' => $runtimeWeek->section_simulation_id,
                'section_simulation_week_id' => $runtimeWeek->id,
                'simulation_week_id' => $runtimeWeek->simulation_week_id,
                'execution_version' => $executionVersion,
                'status' => WeekExecutionRecord::STATUS_RUNNING,
                'steps' => [],
                'outputs' => [],
                'started_by_user_id' => $actor->id,
                'started_at' => Carbon::now(),
            ]);

            $steps = [];
            $outputs = [];

            try {
                foreach (self::STEPS as $step) {
                    if (isset($forcedFailures[$step])) {
                        throw new InvalidArgumentException($forcedFailures[$step]);
                    }

                    $result = $this->runStep($step, $runtimeWeek, $actor, $packageType);
                    $steps[] = [
                        'key' => $step,
                        'status' => $result['status'],
                        'summary' => $result['summary'],
                        'completed_at' => Carbon::now()->toISOString(),
                    ];
                    $outputs[$step] = $result['outputs'];
                }

                $record->forceFill([
                    'status' => WeekExecutionRecord::STATUS_COMPLETED,
                    'steps' => $steps,
                    'outputs' => $outputs,
                    'completed_at' => Carbon::now(),
                ])->save();
            } catch (Throwable $throwable) {
                $steps[] = [
                    'key' => $this->failedStep($steps),
                    'status' => 'failed',
                    'summary' => $throwable->getMessage(),
                    'completed_at' => Carbon::now()->toISOString(),
                ];

                $record->forceFill([
                    'status' => WeekExecutionRecord::STATUS_FAILED,
                    'steps' => $steps,
                    'outputs' => $outputs,
                    'failure_message' => $throwable->getMessage(),
                    'failed_at' => Carbon::now(),
                ])->save();
            }

            return $record->refresh();
        });
    }

    /**
     * @return array{status: string, summary: string, outputs: array<string, mixed>}
     */
    private function runStep(string $step, SectionSimulationWeek $runtimeWeek, User $actor, string $packageType): array
    {
        return match ($step) {
            'validate_content_package' => $this->validateContentPackage($runtimeWeek, $actor, $packageType),
            'lock_submissions' => $this->lockSubmissions($runtimeWeek),
            'resolve_decisions' => $this->resolveDecisions($runtimeWeek, $actor),
            'apply_kpi_calculations' => $this->applyKpis($runtimeWeek),
            'calculate_rankings' => $this->calculateRankings($runtimeWeek),
            'generate_consequences' => $this->deferred('Consequences are generated by supported week resolution services.'),
            'apply_cohort_effects' => $this->applyCohortEffects($runtimeWeek, $actor),
            'publish_allowed_outputs' => $this->deferred('Publication rules remain a future visibility layer.'),
            default => throw new InvalidArgumentException("Unknown week execution step [{$step}]."),
        };
    }

    /**
     * @return array{status: string, summary: string, outputs: array<string, mixed>}
     */
    private function validateContentPackage(SectionSimulationWeek $runtimeWeek, User $actor, string $packageType): array
    {
        $artifacts = $this->contentResolver->authorizedArtifactsFor($actor, $runtimeWeek, $packageType);
        $package = $this->contentResolver->activePackageFor($runtimeWeek, $packageType);

        return [
            'status' => 'completed',
            'summary' => 'Validated active content package.',
            'outputs' => [
                'package_id' => $package->id,
                'package_type' => $package->package_type,
                'package_version' => $package->version,
                'artifact_count' => $artifacts->count(),
            ],
        ];
    }

    /**
     * @return array{status: string, summary: string, outputs: array<string, mixed>}
     */
    private function lockSubmissions(SectionSimulationWeek $runtimeWeek): array
    {
        $submittedCount = DecisionSubmission::query()
            ->where('tenant_id', $runtimeWeek->tenant_id)
            ->where('section_simulation_week_id', $runtimeWeek->id)
            ->where('status', SubmissionStatus::Submitted->value)
            ->count();
        $capitalAllocationCount = CapitalAllocationDecision::query()
            ->where('tenant_id', $runtimeWeek->tenant_id)
            ->where('section_simulation_week_id', $runtimeWeek->id)
            ->count();

        return [
            'status' => 'completed',
            'summary' => 'Submission set counted for execution.',
            'outputs' => [
                'submitted_decision_count' => $submittedCount,
                'capital_allocation_decision_count' => $capitalAllocationCount,
            ],
        ];
    }

    /**
     * @return array{status: string, summary: string, outputs: array<string, mixed>}
     */
    private function resolveDecisions(SectionSimulationWeek $runtimeWeek, User $actor): array
    {
        if ($runtimeWeek->definition->week_number === 1) {
            return $this->evaluateWeek1SubmittedDecisions($runtimeWeek, $actor);
        }

        if ($runtimeWeek->definition->week_number === 2) {
            return $this->evaluateWeek2SubmittedDecisions($runtimeWeek, $actor);
        }

        if ($runtimeWeek->definition->week_number === 6) {
            return $this->evaluateWeek6CapitalAllocations($runtimeWeek, $actor);
        }

        if ($runtimeWeek->definition->week_number === 3) {
            return $this->evaluateWeek3SubmittedDecisions($runtimeWeek, $actor);
        }

        if ($runtimeWeek->definition->week_number === 5) {
            return $this->evaluateWeek5SubmittedDecisions($runtimeWeek, $actor);
        }

        if ($runtimeWeek->definition->week_number === 7) {
            return $this->evaluateWeek7SubmittedDecisions($runtimeWeek, $actor);
        }

        if ($runtimeWeek->definition->week_number === 8) {
            return $this->evaluateWeek8SubmittedDecisions($runtimeWeek, $actor);
        }

        if ($runtimeWeek->definition->week_number === 9) {
            return $this->evaluateWeek9SubmittedDecisions($runtimeWeek, $actor);
        }

        if ($runtimeWeek->definition->week_number === 10) {
            return $this->evaluateWeek10SubmittedDecisions($runtimeWeek, $actor);
        }

        if ($runtimeWeek->definition->week_number === 11) {
            return $this->evaluateWeek11SubmittedDecisions($runtimeWeek, $actor);
        }

        if ($runtimeWeek->definition->week_number === 12) {
            return $this->evaluateWeek12SubmittedDecisions($runtimeWeek, $actor);
        }

        if ($runtimeWeek->definition->week_number === 13) {
            return $this->evaluateWeek13SubmittedDecisions($runtimeWeek, $actor);
        }

        if ($runtimeWeek->definition->week_number === 4) {
            return $this->resolveWeek4SubmittedDecisions($runtimeWeek, $actor);
        }

        return $this->deferred('Decision resolution for this week is not implemented yet.');
    }

    /**
     * @return array{status: string, summary: string, outputs: array<string, mixed>}
     */
    private function evaluateWeek1SubmittedDecisions(SectionSimulationWeek $runtimeWeek, User $actor): array
    {
        $evaluated = 0;
        $statuses = [];

        DecisionSubmission::query()
            ->where('tenant_id', $runtimeWeek->tenant_id)
            ->where('section_simulation_week_id', $runtimeWeek->id)
            ->where('status', SubmissionStatus::Submitted->value)
            ->orderBy('id')
            ->get()
            ->each(function (DecisionSubmission $submission) use ($actor, &$evaluated, &$statuses): void {
                $evaluation = $this->week1Economics->evaluate($submission, $actor, process: 'week_execution_service');
                $evaluated++;
                $statuses[$evaluation->status] = ($statuses[$evaluation->status] ?? 0) + 1;
            });

        return [
            'status' => 'completed',
            'summary' => 'Evaluated Week 1 asset-register decisions.',
            'outputs' => [
                'week1_economic_evaluation_count' => $evaluated,
                'evaluation_status_counts' => $statuses,
            ],
        ];
    }

    /**
     * @return array{status: string, summary: string, outputs: array<string, mixed>}
     */
    private function evaluateWeek2SubmittedDecisions(SectionSimulationWeek $runtimeWeek, User $actor): array
    {
        $evaluated = 0;
        $statuses = [];

        DecisionSubmission::query()
            ->where('tenant_id', $runtimeWeek->tenant_id)
            ->where('section_simulation_week_id', $runtimeWeek->id)
            ->where('status', SubmissionStatus::Submitted->value)
            ->orderBy('id')
            ->get()
            ->each(function (DecisionSubmission $submission) use ($actor, &$evaluated, &$statuses): void {
                $evaluation = $this->week2Economics->evaluate($submission, $actor, process: 'week_execution_service');
                $evaluated++;
                $statuses[$evaluation->status] = ($statuses[$evaluation->status] ?? 0) + 1;
            });

        return [
            'status' => 'completed',
            'summary' => 'Evaluated Week 2 elasticity-estimation decisions.',
            'outputs' => [
                'week2_economic_evaluation_count' => $evaluated,
                'evaluation_status_counts' => $statuses,
            ],
        ];
    }

    /**
     * @return array{status: string, summary: string, outputs: array<string, mixed>}
     */
    private function resolveWeek4SubmittedDecisions(SectionSimulationWeek $runtimeWeek, User $actor): array
    {
        $resolved = 0;
        DecisionSubmission::query()
            ->where('tenant_id', $runtimeWeek->tenant_id)
            ->where('section_simulation_week_id', $runtimeWeek->id)
            ->where('status', SubmissionStatus::Submitted->value)
            ->orderBy('id')
            ->get()
            ->each(function (DecisionSubmission $submission) use ($actor, &$resolved): void {
                $this->weekResolution->resolveSubmittedDecision($submission, $actor, 'week_execution_service');
                $resolved++;
            });

        return [
            'status' => 'completed',
            'summary' => 'Resolved submitted decisions for supported week.',
            'outputs' => ['resolved_decision_count' => $resolved],
        ];
    }

    /**
     * @return array{status: string, summary: string, outputs: array<string, mixed>}
     */
    private function evaluateWeek5SubmittedDecisions(SectionSimulationWeek $runtimeWeek, User $actor): array
    {
        $evaluated = 0;
        $statuses = [];

        DecisionSubmission::query()
            ->where('tenant_id', $runtimeWeek->tenant_id)
            ->where('section_simulation_week_id', $runtimeWeek->id)
            ->where('status', SubmissionStatus::Submitted->value)
            ->orderBy('id')
            ->get()
            ->each(function (DecisionSubmission $submission) use ($actor, &$evaluated, &$statuses): void {
                $evaluation = $this->week5Economics->evaluate($submission, $actor, process: 'week_execution_service');
                $evaluated++;
                $statuses[$evaluation->status] = ($statuses[$evaluation->status] ?? 0) + 1;
            });

        return [
            'status' => 'completed',
            'summary' => 'Evaluated Week 5 currency exposure decisions.',
            'outputs' => [
                'week5_economic_evaluation_count' => $evaluated,
                'evaluation_status_counts' => $statuses,
            ],
        ];
    }

    /**
     * @return array{status: string, summary: string, outputs: array<string, mixed>}
     */
    private function evaluateWeek3SubmittedDecisions(SectionSimulationWeek $runtimeWeek, User $actor): array
    {
        $evaluated = 0;
        $statuses = [];

        DecisionSubmission::query()
            ->where('tenant_id', $runtimeWeek->tenant_id)
            ->where('section_simulation_week_id', $runtimeWeek->id)
            ->where('status', SubmissionStatus::Submitted->value)
            ->orderBy('id')
            ->get()
            ->each(function (DecisionSubmission $submission) use ($actor, &$evaluated, &$statuses): void {
                $evaluation = $this->week3Economics->evaluate($submission, $actor, process: 'week_execution_service');
                $evaluated++;
                $statuses[$evaluation->status] = ($statuses[$evaluation->status] ?? 0) + 1;
            });

        return [
            'status' => 'completed',
            'summary' => 'Evaluated Week 3 shutdown-point decisions.',
            'outputs' => [
                'week3_economic_evaluation_count' => $evaluated,
                'evaluation_status_counts' => $statuses,
            ],
        ];
    }

    /**
     * @return array{status: string, summary: string, outputs: array<string, mixed>}
     */
    private function evaluateWeek7SubmittedDecisions(SectionSimulationWeek $runtimeWeek, User $actor): array
    {
        $evaluated = 0;
        $statuses = [];

        DecisionSubmission::query()
            ->where('tenant_id', $runtimeWeek->tenant_id)
            ->where('section_simulation_week_id', $runtimeWeek->id)
            ->where('status', SubmissionStatus::Submitted->value)
            ->orderBy('id')
            ->get()
            ->each(function (DecisionSubmission $submission) use ($actor, &$evaluated, &$statuses): void {
                $evaluation = $this->week7Economics->evaluate($submission, $actor, process: 'week_execution_service');
                $evaluated++;
                $statuses[$evaluation->status] = ($statuses[$evaluation->status] ?? 0) + 1;
            });

        return [
            'status' => 'completed',
            'summary' => 'Evaluated Week 7 competitive-response decisions.',
            'outputs' => [
                'week7_economic_evaluation_count' => $evaluated,
                'evaluation_status_counts' => $statuses,
            ],
        ];
    }

    /**
     * @return array{status: string, summary: string, outputs: array<string, mixed>}
     */
    private function evaluateWeek8SubmittedDecisions(SectionSimulationWeek $runtimeWeek, User $actor): array
    {
        $evaluated = 0;
        $statuses = [];

        DecisionSubmission::query()
            ->where('tenant_id', $runtimeWeek->tenant_id)
            ->where('section_simulation_week_id', $runtimeWeek->id)
            ->where('status', SubmissionStatus::Submitted->value)
            ->orderBy('id')
            ->get()
            ->each(function (DecisionSubmission $submission) use ($actor, &$evaluated, &$statuses): void {
                $evaluation = $this->week8Economics->evaluate($submission, $actor, process: 'week_execution_service');
                $evaluated++;
                $statuses[$evaluation->status] = ($statuses[$evaluation->status] ?? 0) + 1;
            });

        return [
            'status' => 'completed',
            'summary' => 'Evaluated Week 8 OPEC scenario decisions.',
            'outputs' => [
                'week8_economic_evaluation_count' => $evaluated,
                'evaluation_status_counts' => $statuses,
            ],
        ];
    }

    /**
     * @return array{status: string, summary: string, outputs: array<string, mixed>}
     */
    private function evaluateWeek9SubmittedDecisions(SectionSimulationWeek $runtimeWeek, User $actor): array
    {
        $evaluated = 0;
        $statuses = [];

        DecisionSubmission::query()
            ->where('tenant_id', $runtimeWeek->tenant_id)
            ->where('section_simulation_week_id', $runtimeWeek->id)
            ->where('status', SubmissionStatus::Submitted->value)
            ->orderBy('id')
            ->get()
            ->each(function (DecisionSubmission $submission) use ($actor, &$evaluated, &$statuses): void {
                $evaluation = $this->week9Economics->evaluate($submission, $actor, process: 'week_execution_service');
                $evaluated++;
                $statuses[$evaluation->status] = ($statuses[$evaluation->status] ?? 0) + 1;
            });

        return [
            'status' => 'completed',
            'summary' => 'Evaluated Week 9 Cordell rebrand decisions.',
            'outputs' => [
                'week9_economic_evaluation_count' => $evaluated,
                'evaluation_status_counts' => $statuses,
            ],
        ];
    }

    /**
     * @return array{status: string, summary: string, outputs: array<string, mixed>}
     */
    private function evaluateWeek10SubmittedDecisions(SectionSimulationWeek $runtimeWeek, User $actor): array
    {
        $evaluated = 0;
        $statuses = [];

        DecisionSubmission::query()
            ->where('tenant_id', $runtimeWeek->tenant_id)
            ->where('section_simulation_week_id', $runtimeWeek->id)
            ->where('status', SubmissionStatus::Submitted->value)
            ->orderBy('id')
            ->get()
            ->each(function (DecisionSubmission $submission) use ($actor, &$evaluated, &$statuses): void {
                $evaluation = $this->week10Economics->evaluate($submission, $actor, process: 'week_execution_service');
                $evaluated++;
                $statuses[$evaluation->status] = ($statuses[$evaluation->status] ?? 0) + 1;
            });

        return [
            'status' => 'completed',
            'summary' => 'Evaluated Week 10 convergence decisions.',
            'outputs' => [
                'week10_economic_evaluation_count' => $evaluated,
                'evaluation_status_counts' => $statuses,
            ],
        ];
    }

    /**
     * @return array{status: string, summary: string, outputs: array<string, mixed>}
     */
    private function evaluateWeek11SubmittedDecisions(SectionSimulationWeek $runtimeWeek, User $actor): array
    {
        $evaluated = 0;
        $statuses = [];

        DecisionSubmission::query()
            ->where('tenant_id', $runtimeWeek->tenant_id)
            ->where('section_simulation_week_id', $runtimeWeek->id)
            ->where('status', SubmissionStatus::Submitted->value)
            ->orderBy('id')
            ->get()
            ->each(function (DecisionSubmission $submission) use ($actor, &$evaluated, &$statuses): void {
                $evaluation = $this->week11Economics->evaluate($submission, $actor, process: 'week_execution_service');
                $evaluated++;
                $statuses[$evaluation->status] = ($statuses[$evaluation->status] ?? 0) + 1;
            });

        return [
            'status' => 'completed',
            'summary' => 'Evaluated Week 11 Kessana fiscal decisions.',
            'outputs' => [
                'week11_economic_evaluation_count' => $evaluated,
                'evaluation_status_counts' => $statuses,
            ],
        ];
    }

    /**
     * @return array{status: string, summary: string, outputs: array<string, mixed>}
     */
    private function evaluateWeek12SubmittedDecisions(SectionSimulationWeek $runtimeWeek, User $actor): array
    {
        $evaluated = 0;
        $statuses = [];

        DecisionSubmission::query()
            ->where('tenant_id', $runtimeWeek->tenant_id)
            ->where('section_simulation_week_id', $runtimeWeek->id)
            ->where('status', SubmissionStatus::Submitted->value)
            ->orderBy('id')
            ->get()
            ->each(function (DecisionSubmission $submission) use ($actor, &$evaluated, &$statuses): void {
                $evaluation = $this->week12Economics->evaluate($submission, $actor, process: 'week_execution_service');
                $evaluated++;
                $statuses[$evaluation->status] = ($statuses[$evaluation->status] ?? 0) + 1;
            });

        return [
            'status' => 'completed',
            'summary' => 'Evaluated Week 12 transition portfolio decisions.',
            'outputs' => [
                'week12_economic_evaluation_count' => $evaluated,
                'evaluation_status_counts' => $statuses,
            ],
        ];
    }

    /**
     * @return array{status: string, summary: string, outputs: array<string, mixed>}
     */
    private function evaluateWeek13SubmittedDecisions(SectionSimulationWeek $runtimeWeek, User $actor): array
    {
        $evaluated = 0;
        $statuses = [];

        DecisionSubmission::query()
            ->where('tenant_id', $runtimeWeek->tenant_id)
            ->where('section_simulation_week_id', $runtimeWeek->id)
            ->where('status', SubmissionStatus::Submitted->value)
            ->orderBy('id')
            ->get()
            ->each(function (DecisionSubmission $submission) use ($actor, &$evaluated, &$statuses): void {
                $evaluation = $this->week13Economics->evaluate($submission, $actor, process: 'week_execution_service');
                $evaluated++;
                $statuses[$evaluation->status] = ($statuses[$evaluation->status] ?? 0) + 1;
            });

        return [
            'status' => 'completed',
            'summary' => 'Evaluated Week 13 factor-market decisions.',
            'outputs' => [
                'week13_economic_evaluation_count' => $evaluated,
                'evaluation_status_counts' => $statuses,
            ],
        ];
    }

    /**
     * @return array{status: string, summary: string, outputs: array<string, mixed>}
     */
    private function evaluateWeek6CapitalAllocations(SectionSimulationWeek $runtimeWeek, User $actor): array
    {
        $evaluated = 0;
        $statuses = [];

        CapitalAllocationDecision::query()
            ->where('tenant_id', $runtimeWeek->tenant_id)
            ->where('section_simulation_week_id', $runtimeWeek->id)
            ->orderBy('id')
            ->get()
            ->each(function (CapitalAllocationDecision $decision) use ($actor, &$evaluated, &$statuses): void {
                $evaluation = $this->week6CapitalEconomics->evaluate($decision, $actor, process: 'week_execution_service');
                $evaluated++;
                $statuses[$evaluation->status] = ($statuses[$evaluation->status] ?? 0) + 1;
            });

        return [
            'status' => 'completed',
            'summary' => 'Evaluated Week 6 capital allocation decisions.',
            'outputs' => [
                'capital_allocation_evaluation_count' => $evaluated,
                'evaluation_status_counts' => $statuses,
            ],
        ];
    }

    /**
     * @return array{status: string, summary: string, outputs: array<string, mixed>}
     */
    private function applyKpis(SectionSimulationWeek $runtimeWeek): array
    {
        if ($runtimeWeek->definition->week_number === 8) {
            return $this->applyWeek8Kpis($runtimeWeek);
        }

        if ($runtimeWeek->definition->week_number === 10) {
            return $this->applyWeek10Kpis($runtimeWeek);
        }

        if ($runtimeWeek->definition->week_number === 11) {
            return $this->applyWeek11Kpis($runtimeWeek);
        }

        if ($runtimeWeek->definition->week_number === 4) {
            return $this->applyWeek4Kpis($runtimeWeek);
        }

        return $this->deferred('KPI population for this week is not implemented yet.');
    }

    /**
     * @return array{status: string, summary: string, outputs: array<string, mixed>}
     */
    private function applyWeek4Kpis(SectionSimulationWeek $runtimeWeek): array
    {
        $snapshotCount = 0;
        EconomicResolution::query()
            ->where('tenant_id', $runtimeWeek->tenant_id)
            ->where('section_simulation_week_id', $runtimeWeek->id)
            ->orderBy('id')
            ->get()
            ->each(function (EconomicResolution $resolution) use (&$snapshotCount): void {
                $snapshotCount += count($this->week4Kpis->populate($resolution));
            });

        return [
            'status' => 'completed',
            'summary' => 'Applied KPI calculations for supported week.',
            'outputs' => ['kpi_snapshot_count' => $snapshotCount],
        ];
    }

    /**
     * @return array{status: string, summary: string, outputs: array<string, mixed>}
     */
    private function applyWeek8Kpis(SectionSimulationWeek $runtimeWeek): array
    {
        $snapshotCount = 0;
        Week8EconomicEvaluation::query()
            ->where('tenant_id', $runtimeWeek->tenant_id)
            ->where('section_simulation_week_id', $runtimeWeek->id)
            ->orderBy('id')
            ->get()
            ->each(function (Week8EconomicEvaluation $evaluation) use (&$snapshotCount): void {
                $snapshotCount += count($this->week8Kpis->populate($evaluation));
            });

        return [
            'status' => 'completed',
            'summary' => 'Applied KPI calculations for supported week.',
            'outputs' => ['kpi_snapshot_count' => $snapshotCount],
        ];
    }

    /**
     * @return array{status: string, summary: string, outputs: array<string, mixed>}
     */
    private function applyWeek10Kpis(SectionSimulationWeek $runtimeWeek): array
    {
        $snapshotCount = 0;
        Week10EconomicEvaluation::query()
            ->where('tenant_id', $runtimeWeek->tenant_id)
            ->where('section_simulation_week_id', $runtimeWeek->id)
            ->where('status', Week10EconomicEvaluation::STATUS_CALCULATED)
            ->orderBy('id')
            ->get()
            ->each(function (Week10EconomicEvaluation $evaluation) use (&$snapshotCount): void {
                $snapshotCount += count($this->week10Kpis->populate($evaluation));
            });

        return [
            'status' => 'completed',
            'summary' => 'Applied KPI calculations for supported week.',
            'outputs' => ['kpi_snapshot_count' => $snapshotCount],
        ];
    }

    /**
     * @return array{status: string, summary: string, outputs: array<string, mixed>}
     */
    private function applyWeek11Kpis(SectionSimulationWeek $runtimeWeek): array
    {
        $snapshotCount = 0;
        Week11EconomicEvaluation::query()
            ->where('tenant_id', $runtimeWeek->tenant_id)
            ->where('section_simulation_week_id', $runtimeWeek->id)
            ->where('status', Week11EconomicEvaluation::STATUS_CALCULATED)
            ->orderBy('id')
            ->get()
            ->each(function (Week11EconomicEvaluation $evaluation) use (&$snapshotCount): void {
                $snapshotCount += count($this->week11Kpis->populate($evaluation));
            });

        return [
            'status' => 'completed',
            'summary' => 'Applied KPI calculations for supported week.',
            'outputs' => ['kpi_snapshot_count' => $snapshotCount],
        ];
    }

    /**
     * @return array{status: string, summary: string, outputs: array<string, mixed>}
     */
    private function calculateRankings(SectionSimulationWeek $runtimeWeek): array
    {
        if (! in_array($runtimeWeek->definition->week_number, [4, 8, 10, 11], true)) {
            return $this->deferred('Ranking calculation for this week is not implemented yet.');
        }

        $snapshots = $this->rankings->calculateForSectionWeek($runtimeWeek);

        return [
            'status' => 'completed',
            'summary' => 'Calculated ranking snapshots.',
            'outputs' => ['ranking_snapshot_count' => count($snapshots)],
        ];
    }

    /**
     * @return array{status: string, summary: string, outputs: array<string, mixed>}
     */
    private function applyCohortEffects(SectionSimulationWeek $runtimeWeek, User $actor): array
    {
        $functions = CohortResponseFunction::query()
            ->where('source_week_number', $runtimeWeek->definition->week_number)
            ->where('is_active', true)
            ->orderByDesc('id')
            ->get();

        $function = $functions->first(fn (CohortResponseFunction $candidate): bool => ! $this->cohortFunctionExcludedForRuntime($candidate, $runtimeWeek));

        if (! $function instanceof CohortResponseFunction) {
            return $this->deferred('Cohort effects require configured cohort response functions.');
        }

        $aggregate = $this->cohortFeedback->resolveForSectionWeek($function, $runtimeWeek, $actor);

        return [
            'status' => 'completed',
            'summary' => 'Applied configured cohort feedback effect.',
            'outputs' => [
                'cohort_decision_aggregate_id' => $aggregate->id,
                'cohort_response_function_key' => $aggregate->function_key,
                'cohort_response_function_version' => $aggregate->function_version,
                'target_section_simulation_week_id' => $aggregate->target_section_simulation_week_id,
            ],
        ];
    }

    private function cohortFunctionExcludedForRuntime(CohortResponseFunction $function, SectionSimulationWeek $runtimeWeek): bool
    {
        $runtimeWeek->loadMissing('sectionSimulation.version.variant');
        $parameters = $function->parameterDefinition();
        $durationWeeks = $runtimeWeek->sectionSimulation->version->variant?->duration_weeks;
        $excludedDurations = $parameters['excluded_variant_duration_weeks'] ?? [];

        if (is_array($excludedDurations) && $durationWeeks !== null && in_array((int) $durationWeeks, array_map('intval', $excludedDurations), true)) {
            return true;
        }

        $variantSlug = $runtimeWeek->sectionSimulation->version->variant?->slug;
        $excludedSlugs = $parameters['excluded_variant_slugs'] ?? [];

        return is_string($variantSlug) && is_array($excludedSlugs) && in_array($variantSlug, array_map('strval', $excludedSlugs), true);
    }

    /**
     * @return array{status: string, summary: string, outputs: array<string, mixed>}
     */
    private function deferred(string $summary): array
    {
        return [
            'status' => 'deferred',
            'summary' => $summary,
            'outputs' => [],
        ];
    }

    private function assertCanExecute(User $actor, SectionSimulationWeek $runtimeWeek): void
    {
        if ($actor->tenant_id !== $runtimeWeek->tenant_id) {
            throw new InvalidArgumentException('Actor cannot execute another tenant runtime week.');
        }

        if ($actor->isAdministrator()) {
            return;
        }

        if ($actor->isFaculty()) {
            $assigned = $actor->facultySections()
                ->wherePivot('tenant_id', $runtimeWeek->tenant_id)
                ->whereKey($runtimeWeek->sectionSimulation->section_id)
                ->exists();

            if ($assigned) {
                return;
            }
        }

        throw new InvalidArgumentException('Only authorized faculty can execute simulation weeks.');
    }

    /**
     * @param  list<array<string, mixed>>  $steps
     */
    private function failedStep(array $steps): string
    {
        return self::STEPS[count($steps)] ?? 'unknown';
    }

    private function retryExecutionVersion(SectionSimulationWeek $runtimeWeek, string $baseExecutionVersion): string
    {
        $attemptCount = WeekExecutionRecord::query()
            ->where('tenant_id', $runtimeWeek->tenant_id)
            ->where('section_simulation_week_id', $runtimeWeek->id)
            ->where(function ($query) use ($baseExecutionVersion): void {
                $query
                    ->where('execution_version', $baseExecutionVersion)
                    ->orWhere('execution_version', 'like', $baseExecutionVersion.'_retry_%');
            })
            ->count();

        return $baseExecutionVersion.'_retry_'.($attemptCount + 1);
    }
}
