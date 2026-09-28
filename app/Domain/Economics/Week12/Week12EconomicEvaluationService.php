<?php

namespace App\Domain\Economics\Week12;

use App\Enums\SubmissionStatus;
use App\Models\DecisionSubmission;
use App\Models\User;
use App\Models\Week12EconomicEvaluation;
use Brick\Math\BigDecimal;
use Brick\Math\RoundingMode;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

final readonly class Week12EconomicEvaluationService
{
    public const PACKAGE_IDENTIFIER = 'halden-week12-data-package';

    public function __construct(
        private Week12EconomicEngine $engine,
    ) {}

    public function evaluate(
        DecisionSubmission $submission,
        User $actor,
        ?Week12ReferencePackage $package = null,
        string $process = 'week12_economic_evaluation_service',
    ): Week12EconomicEvaluation {
        $submission->loadMissing(['runtimeWeek.definition', 'definition']);
        $this->assertCanEvaluate($actor, $submission);

        return DB::transaction(function () use ($submission, $actor, $package, $process): Week12EconomicEvaluation {
            $existing = Week12EconomicEvaluation::query()
                ->where('tenant_id', $submission->tenant_id)
                ->where('decision_submission_id', $submission->id)
                ->where('engine_identifier', Week12EconomicEngine::ENGINE_IDENTIFIER)
                ->lockForUpdate()
                ->first();

            if ($existing instanceof Week12EconomicEvaluation) {
                return $existing;
            }

            $package ??= Week12ReferencePackage::fromRepository();

            if (! $package->isAvailable()) {
                return $this->createUnavailableEvaluation($submission, $actor, $package, $process);
            }

            $inputs = $package->inputs();
            $selectedProjects = $this->selectedProjectKeys($submission, array_keys($inputs->projects));

            if ($selectedProjects === []) {
                return $this->createInvalidSubmissionEvaluation($submission, $actor, $inputs, 'Week 12 submission must include at least one selected project.', $process);
            }

            $unknownProjects = array_values(array_diff($selectedProjects, array_keys($inputs->projects)));

            if ($unknownProjects !== []) {
                return $this->createInvalidSubmissionEvaluation($submission, $actor, $inputs, 'Week 12 submission contains unknown project keys: '.implode(', ', $unknownProjects).'.', $process);
            }

            $result = $this->engine->calculate($inputs);
            $selectedPortfolio = $result->portfolioContaining(...$selectedProjects);

            if ($selectedPortfolio === null) {
                return $this->createInvalidSubmissionEvaluation($submission, $actor, $inputs, 'Week 12 selected portfolio could not be evaluated.', $process);
            }

            $availableProjects = array_keys($inputs->projects);
            $rejectedProjects = array_values(array_diff($availableProjects, $selectedProjects));

            return Week12EconomicEvaluation::query()->create([
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
                'status' => Week12EconomicEvaluation::STATUS_CALCULATED,
                'selected_projects' => $selectedProjects,
                'rejected_projects' => $rejectedProjects,
                'available_projects' => $availableProjects,
                'selected_portfolio_feasible' => $selectedPortfolio->feasible,
                'selected_constraint_failures' => $selectedPortfolio->constraintFailures,
                'selected_includes_divestment' => $selectedPortfolio->divestmentProceedsMusd->isGreaterThan(BigDecimal::zero()),
                'selected_unlocked_by_divestment' => $selectedPortfolio->unlockedByDivestment,
                'selected_capital_required_musd' => $this->decimal($selectedPortfolio->capitalRequiredMusd, 6),
                'selected_available_envelope_musd' => $this->decimal($selectedPortfolio->availableEnvelopeMusd, 6),
                'discretionary_envelope_musd' => $this->decimal($result->discretionaryEnvelopeMusd, 6),
                'envelope_with_divestment_musd' => $this->decimal($result->envelopeWithDivestmentMusd, 6),
                'feasible_portfolio_count' => $result->feasiblePortfolioCount,
                'feasible_with_helix_rotterdam_count' => $result->feasibleWithHelixRotterdamCount,
                'portfolios_unlocked_by_divestment_count' => $result->portfoliosUnlockedByDivestmentCount,
                'portfolio_results' => array_map(fn (Week12PortfolioResult $portfolioResult): array => $portfolioResult->snapshot(), $result->portfolioResults),
                'worked_example_snapshot' => $this->formatDecimals($result->workedExample, 6),
                'input_snapshot' => [
                    ...$result->inputSnapshot,
                    'decision_submission' => [
                        'id' => $submission->id,
                        'answers' => $this->submissionAnswers($submission),
                    ],
                    'portfolio_context' => [
                        'selected_projects' => $selectedProjects,
                        'rejected_projects' => $rejectedProjects,
                        'available_projects' => $availableProjects,
                    ],
                ],
                'output_snapshot' => [
                    ...$result->outputSnapshot,
                    'selected_portfolio' => $selectedPortfolio->snapshot(),
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
        Week12ReferencePackage $package,
        string $process,
    ): Week12EconomicEvaluation {
        $reason = $package->unavailableReason() ?? Week12ReferencePackage::MISSING_REASON;

        return Week12EconomicEvaluation::query()->create([
            'tenant_id' => $submission->tenant_id,
            'section_simulation_id' => $submission->section_simulation_id,
            'section_simulation_week_id' => $submission->section_simulation_week_id,
            'team_simulation_id' => $submission->team_simulation_id,
            'team_id' => $submission->team_id,
            'decision_submission_id' => $submission->id,
            'engine_identifier' => Week12EconomicEngine::ENGINE_IDENTIFIER,
            'engine_version' => Week12EconomicEngine::ENGINE_VERSION,
            'package_identifier' => self::PACKAGE_IDENTIFIER,
            'package_version' => $package->version(),
            'status' => Week12EconomicEvaluation::STATUS_UNAVAILABLE_REFERENCE_PACKAGE,
            'selected_projects' => [],
            'rejected_projects' => [],
            'available_projects' => [],
            'input_snapshot' => [
                'status' => Week12EconomicEvaluation::STATUS_UNAVAILABLE_REFERENCE_PACKAGE,
                'decision_submission_id' => $submission->id,
            ],
            'output_snapshot' => [
                'status' => Week12EconomicEvaluation::STATUS_UNAVAILABLE_REFERENCE_PACKAGE,
                'reason' => $reason,
            ],
            'unavailable_reason' => $reason,
            'evaluated_by_user_id' => $actor->id,
            'evaluated_by_process' => $process,
            'evaluated_at' => Carbon::now(),
        ]);
    }

    private function createInvalidSubmissionEvaluation(
        DecisionSubmission $submission,
        User $actor,
        Week12EconomicInputs $inputs,
        string $reason,
        string $process,
    ): Week12EconomicEvaluation {
        return Week12EconomicEvaluation::query()->create([
            'tenant_id' => $submission->tenant_id,
            'section_simulation_id' => $submission->section_simulation_id,
            'section_simulation_week_id' => $submission->section_simulation_week_id,
            'team_simulation_id' => $submission->team_simulation_id,
            'team_id' => $submission->team_id,
            'decision_submission_id' => $submission->id,
            'engine_identifier' => Week12EconomicEngine::ENGINE_IDENTIFIER,
            'engine_version' => Week12EconomicEngine::ENGINE_VERSION,
            'package_identifier' => self::PACKAGE_IDENTIFIER,
            'package_version' => $inputs->packageVersion,
            'status' => Week12EconomicEvaluation::STATUS_INVALID_SUBMISSION,
            'selected_projects' => $this->selectedProjectKeys($submission, array_keys($inputs->projects), allowUnknown: true),
            'rejected_projects' => [],
            'available_projects' => array_keys($inputs->projects),
            'input_snapshot' => [
                'reference_package' => [
                    'version' => $inputs->packageVersion,
                    'source_hashes' => $inputs->sourceHashes,
                ],
                'decision_submission' => [
                    'id' => $submission->id,
                    'answers' => $this->submissionAnswers($submission),
                ],
            ],
            'output_snapshot' => [
                'status' => Week12EconomicEvaluation::STATUS_INVALID_SUBMISSION,
                'reason' => $reason,
            ],
            'unavailable_reason' => $reason,
            'evaluated_by_user_id' => $actor->id,
            'evaluated_by_process' => $process,
            'evaluated_at' => Carbon::now(),
        ]);
    }

    /**
     * @param  list<string>  $knownProjectKeys
     * @return list<string>
     */
    private function selectedProjectKeys(DecisionSubmission $submission, array $knownProjectKeys, bool $allowUnknown = false): array
    {
        $answers = $this->submissionAnswers($submission);
        $raw = $answers['selected_project_keys'] ?? $answers['portfolio_projects'] ?? $answers['selected_projects'] ?? null;

        if (is_string($raw)) {
            $keys = preg_split('/[\s,;]+/', $raw) ?: [];
        } elseif (is_array($raw)) {
            $keys = $raw;
        } else {
            return [];
        }

        $selected = [];
        foreach ($keys as $key) {
            if (! is_scalar($key)) {
                continue;
            }

            $projectKey = trim((string) $key);

            if ($projectKey === '') {
                continue;
            }

            if ($allowUnknown || in_array($projectKey, $knownProjectKeys, true)) {
                $selected[] = $projectKey;
            } else {
                $selected[] = $projectKey;
            }
        }

        return array_values(array_unique($selected));
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
        if ($submission->runtimeWeek->definition->week_number !== 12) {
            throw new InvalidArgumentException('Week 12 economic evaluation can only evaluate Week 12 decision submissions.');
        }

        if ($submission->statusValue() !== SubmissionStatus::Submitted->value) {
            throw new InvalidArgumentException('Week 12 economic evaluation requires a submitted decision.');
        }

        if ($actor->tenant_id !== $submission->tenant_id) {
            throw new InvalidArgumentException('Actor cannot evaluate Week 12 economics for another tenant.');
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

        throw new InvalidArgumentException('Only authorized faculty can evaluate Week 12 economics.');
    }
}
