<?php

namespace App\Domain\CohortFeedback;

use App\Enums\SectionSimulationWeekStatus;
use App\Enums\SubmissionStatus;
use App\Models\CohortDecisionAggregate;
use App\Models\CohortFeedbackEffect;
use App\Models\CohortResponseFunction;
use App\Models\DecisionSubmission;
use App\Models\SectionSimulationWeek;
use App\Models\User;
use Brick\Math\BigDecimal;
use Brick\Math\RoundingMode;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use JsonException;

final class CohortFeedbackService
{
    public function resolveForSectionWeek(CohortResponseFunction $function, SectionSimulationWeek $sourceRuntimeWeek, User $actor): CohortDecisionAggregate
    {
        $sourceRuntimeWeek->loadMissing(['definition', 'sectionSimulation.weeks.definition']);
        $this->assertCanResolve($actor, $sourceRuntimeWeek);
        $this->assertFunctionMatchesSource($function, $sourceRuntimeWeek);
        $this->assertSourceResolved($sourceRuntimeWeek);

        /** @var SectionSimulationWeek $targetRuntimeWeek */
        $targetRuntimeWeek = $sourceRuntimeWeek->sectionSimulation->weeks()
            ->whereHas('definition', fn ($query) => $query->where('week_number', $function->target_week_number))
            ->firstOrFail();

        return DB::transaction(function () use ($function, $sourceRuntimeWeek, $targetRuntimeWeek, $actor): CohortDecisionAggregate {
            $existing = CohortDecisionAggregate::query()
                ->where('tenant_id', $sourceRuntimeWeek->tenant_id)
                ->where('section_simulation_id', $sourceRuntimeWeek->section_simulation_id)
                ->where('source_section_simulation_week_id', $sourceRuntimeWeek->id)
                ->where('cohort_response_function_id', $function->id)
                ->lockForUpdate()
                ->first();

            if ($existing instanceof CohortDecisionAggregate) {
                return $existing;
            }

            $decisions = $this->decisionSnapshot($function, $sourceRuntimeWeek);
            $aggregateSnapshot = $this->aggregateSnapshot($function, $decisions);
            $responseSnapshot = $this->responseSnapshot($function, $aggregateSnapshot);

            $aggregate = CohortDecisionAggregate::query()->create([
                'tenant_id' => $sourceRuntimeWeek->tenant_id,
                'section_simulation_id' => $sourceRuntimeWeek->section_simulation_id,
                'source_section_simulation_week_id' => $sourceRuntimeWeek->id,
                'target_section_simulation_week_id' => $targetRuntimeWeek->id,
                'cohort_response_function_id' => $function->id,
                'function_key' => $function->key,
                'function_version' => $function->version,
                'individual_decisions_snapshot' => $decisions,
                'aggregate_snapshot' => $aggregateSnapshot,
                'response_snapshot' => $responseSnapshot,
                'calculated_by_user_id' => $actor->id,
                'calculated_at' => Carbon::now(),
            ]);

            CohortFeedbackEffect::query()->create([
                'tenant_id' => $aggregate->tenant_id,
                'section_simulation_id' => $aggregate->section_simulation_id,
                'source_section_simulation_week_id' => $aggregate->source_section_simulation_week_id,
                'target_section_simulation_week_id' => $aggregate->target_section_simulation_week_id,
                'cohort_decision_aggregate_id' => $aggregate->id,
                'cohort_response_function_id' => $function->id,
                'effect_key' => (string) ($function->outputDefinition()['key'] ?? $function->key),
                'effect_version' => $function->version,
                'effect_snapshot' => [
                    'function' => [
                        'key' => $function->key,
                        'version' => $function->version,
                    ],
                    'source_week_number' => $function->source_week_number,
                    'target_week_number' => $function->target_week_number,
                    'aggregate' => $aggregateSnapshot,
                    'response' => $responseSnapshot,
                    'output_definition' => $function->outputDefinition(),
                ],
                'revealed_at' => Carbon::now(),
                'applied_at' => Carbon::now(),
            ]);

            return $aggregate->refresh();
        });
    }

    /**
     * @return Collection<int, CohortFeedbackEffect>
     */
    public function visibleEffectsForStudent(User $actor, SectionSimulationWeek $targetRuntimeWeek): Collection
    {
        if ($actor->tenant_id !== $targetRuntimeWeek->tenant_id || ! $actor->isStudent()) {
            return new Collection;
        }

        $targetRuntimeWeek->loadMissing('sectionSimulation');
        $enrolled = $actor->enrollments()
            ->where('section_id', $targetRuntimeWeek->sectionSimulation->section_id)
            ->where('status', 'active')
            ->exists();

        if (! $enrolled) {
            return new Collection;
        }

        return CohortFeedbackEffect::query()
            ->where('tenant_id', $targetRuntimeWeek->tenant_id)
            ->where('target_section_simulation_week_id', $targetRuntimeWeek->id)
            ->whereNotNull('revealed_at')
            ->orderBy('id')
            ->get();
    }

    public function assertHiddenDuringDecisionWindow(User $actor, SectionSimulationWeek $sourceRuntimeWeek): void
    {
        if (! $actor->isStudent()) {
            return;
        }

        if (in_array($sourceRuntimeWeek->statusEnum(), [
            SectionSimulationWeekStatus::Released,
            SectionSimulationWeekStatus::Open,
        ], true)) {
            throw new InvalidArgumentException('Cohort state is hidden until the source week is resolved.');
        }
    }

    private function assertCanResolve(User $actor, SectionSimulationWeek $sourceRuntimeWeek): void
    {
        if ($actor->tenant_id !== $sourceRuntimeWeek->tenant_id) {
            throw new InvalidArgumentException('Actor cannot resolve cohort feedback for another tenant.');
        }

        if ($actor->isAdministrator()) {
            return;
        }

        if ($actor->isFaculty()) {
            $sourceRuntimeWeek->loadMissing('sectionSimulation');
            $assigned = $actor->facultySections()
                ->wherePivot('tenant_id', $sourceRuntimeWeek->tenant_id)
                ->whereKey($sourceRuntimeWeek->sectionSimulation->section_id)
                ->exists();

            if ($assigned) {
                return;
            }
        }

        throw new InvalidArgumentException('Only authorized faculty can resolve cohort feedback.');
    }

    private function assertFunctionMatchesSource(CohortResponseFunction $function, SectionSimulationWeek $sourceRuntimeWeek): void
    {
        if (! $function->is_active) {
            throw new InvalidArgumentException('Inactive cohort response functions cannot be resolved.');
        }

        if ($sourceRuntimeWeek->definition->week_number !== $function->source_week_number) {
            throw new InvalidArgumentException('Cohort response function source week does not match runtime week.');
        }
    }

    private function assertSourceResolved(SectionSimulationWeek $sourceRuntimeWeek): void
    {
        if (! in_array($sourceRuntimeWeek->statusEnum(), [
            SectionSimulationWeekStatus::Closed,
            SectionSimulationWeekStatus::Published,
        ], true)) {
            throw new InvalidArgumentException('Cohort feedback can only be calculated after the source week is closed.');
        }
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function decisionSnapshot(CohortResponseFunction $function, SectionSimulationWeek $sourceRuntimeWeek): array
    {
        $inputDefinition = $function->inputDefinition();
        $field = (string) ($inputDefinition['decision_field'] ?? '');

        if ($field === '') {
            throw new InvalidArgumentException('Cohort response function input definition must declare decision_field.');
        }

        $items = [];

        foreach (DecisionSubmission::query()
            ->with('teamSimulation.team')
            ->where('tenant_id', $sourceRuntimeWeek->tenant_id)
            ->where('section_simulation_id', $sourceRuntimeWeek->section_simulation_id)
            ->where('section_simulation_week_id', $sourceRuntimeWeek->id)
            ->where('status', SubmissionStatus::Submitted->value)
            ->orderBy('team_simulation_id')
            ->get() as $submission) {
            $answers = $this->submissionAnswers($submission);

            if (! array_key_exists($field, $answers)) {
                continue;
            }

            $items[] = [
                'decision_submission_id' => $submission->id,
                'team_simulation_id' => $submission->team_simulation_id,
                'team_id' => $submission->team_id,
                'team_name' => $submission->teamSimulation->team->name,
                'field' => $field,
                'value' => (string) BigDecimal::of((string) $answers[$field]),
                'submitted_at' => $this->dateIso($submission->getAttribute('submitted_at')),
            ];
        }

        return $items;
    }

    /**
     * @param  list<array<string, mixed>>  $decisions
     * @return array<string, mixed>
     */
    private function aggregateSnapshot(CohortResponseFunction $function, array $decisions): array
    {
        $parameters = $function->parameterDefinition();
        $method = (string) ($parameters['aggregate'] ?? 'average');
        $count = count($decisions);
        $sum = BigDecimal::zero();

        foreach ($decisions as $decision) {
            $sum = $sum->plus((string) $decision['value']);
        }

        $value = match ($method) {
            'sum' => $sum,
            'count' => BigDecimal::of($count),
            'average' => $count > 0 ? $sum->dividedBy($count, 6, RoundingMode::HalfUp) : BigDecimal::zero(),
            default => throw new InvalidArgumentException("Unsupported cohort aggregate method [{$method}]."),
        };

        return [
            'method' => $method,
            'decision_count' => $count,
            'sum' => (string) $sum->toScale(6, RoundingMode::HalfUp),
            'value' => (string) $value->toScale(6, RoundingMode::HalfUp),
            'input_definition' => $function->inputDefinition(),
        ];
    }

    /**
     * @param  array<string, mixed>  $aggregateSnapshot
     * @return array<string, mixed>
     */
    private function responseSnapshot(CohortResponseFunction $function, array $aggregateSnapshot): array
    {
        $aggregateValue = BigDecimal::of((string) $aggregateSnapshot['value']);
        $parameters = $function->parameterDefinition();
        $intercept = BigDecimal::of((string) ($parameters['intercept'] ?? '0'));
        $slope = BigDecimal::of((string) ($parameters['slope'] ?? '1'));
        $raw = $intercept->plus($slope->multipliedBy($aggregateValue));
        $bounded = $this->applyBounds($raw, $function);

        return [
            'calculation' => 'linear_response_v1',
            'raw_value' => (string) $raw->toScale(6, RoundingMode::HalfUp),
            'bounded_value' => (string) $bounded->toScale(6, RoundingMode::HalfUp),
            'output_definition' => $function->outputDefinition(),
            'bounds' => $function->boundsDefinition(),
            'parameters' => $function->parameterDefinition(),
        ];
    }

    private function applyBounds(BigDecimal $value, CohortResponseFunction $function): BigDecimal
    {
        $bounds = $function->boundsDefinition();
        $bounded = $value;

        if (array_key_exists('min', $bounds)) {
            $min = BigDecimal::of((string) $bounds['min']);
            $bounded = $bounded->isLessThan($min) ? $min : $bounded;
        }

        if (array_key_exists('max', $bounds)) {
            $max = BigDecimal::of((string) $bounds['max']);
            $bounded = $bounded->isGreaterThan($max) ? $max : $bounded;
        }

        return $bounded;
    }

    /**
     * @return array<string, mixed>
     *
     * @throws JsonException
     */
    private function submissionAnswers(DecisionSubmission $submission): array
    {
        $answers = $submission->getAttribute('answers');

        if (is_array($answers)) {
            return $answers;
        }

        if (is_string($answers)) {
            $decoded = json_decode($answers, true, flags: JSON_THROW_ON_ERROR);

            if (is_array($decoded)) {
                return $decoded;
            }
        }

        return [];
    }

    private function dateIso(mixed $value): ?string
    {
        if ($value === null) {
            return null;
        }

        if ($value instanceof CarbonInterface) {
            return $value->toISOString();
        }

        return Carbon::parse((string) $value)->toISOString();
    }
}
