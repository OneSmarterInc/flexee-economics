<?php

namespace App\Domain\Execution;

use App\Domain\Content\SimulationContentResolver;
use App\Domain\Economics\Resolution\WeekResolutionService;
use App\Domain\Ranking\RankingCalculationService;
use App\Domain\Scoring\Week4KpiPopulationService;
use App\Enums\SubmissionStatus;
use App\Models\DecisionSubmission;
use App\Models\EconomicResolution;
use App\Models\SectionSimulationWeek;
use App\Models\User;
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
        private WeekResolutionService $weekResolution,
        private Week4KpiPopulationService $week4Kpis,
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
                throw new InvalidArgumentException('This runtime week has already been executed for the requested execution version.');
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
            'apply_cohort_effects' => $this->deferred('Cohort effects require configured cohort response functions.'),
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

        return [
            'status' => 'completed',
            'summary' => 'Submission set counted for execution.',
            'outputs' => ['submitted_decision_count' => $submittedCount],
        ];
    }

    /**
     * @return array{status: string, summary: string, outputs: array<string, mixed>}
     */
    private function resolveDecisions(SectionSimulationWeek $runtimeWeek, User $actor): array
    {
        if ($runtimeWeek->definition->week_number !== 4) {
            return $this->deferred('Decision resolution for this week is not implemented yet.');
        }

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
    private function applyKpis(SectionSimulationWeek $runtimeWeek): array
    {
        if ($runtimeWeek->definition->week_number !== 4) {
            return $this->deferred('KPI population for this week is not implemented yet.');
        }

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
    private function calculateRankings(SectionSimulationWeek $runtimeWeek): array
    {
        if ($runtimeWeek->definition->week_number !== 4) {
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
}
