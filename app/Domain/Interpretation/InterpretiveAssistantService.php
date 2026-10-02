<?php

namespace App\Domain\Interpretation;

use App\Jobs\GenerateInterpretationJob;
use App\Models\InterpretationRequest;
use App\Models\SectionSimulationWeek;
use App\Models\TeamSimulation;
use App\Models\User;
use Illuminate\Support\Carbon;
use InvalidArgumentException;
use JsonException;

final class InterpretiveAssistantService
{
    public const PROMPT_VERSION = 'faculty_interpretive_assistant_prompt_v1';

    public function __construct(
        private readonly FacultyInterpretationContextBuilder $contextBuilder,
    ) {}

    public function requestInterpretation(User $actor, TeamSimulation $teamSimulation, SectionSimulationWeek $runtimeWeek, string $focus = 'faculty_debrief'): InterpretationRequest
    {
        $this->assertCanRequest($actor, $teamSimulation, $runtimeWeek);

        $context = $this->contextBuilder->build($teamSimulation, $runtimeWeek);
        $request = InterpretationRequest::query()->create([
            'tenant_id' => $teamSimulation->tenant_id,
            'section_simulation_id' => $teamSimulation->section_simulation_id,
            'section_simulation_week_id' => $runtimeWeek->id,
            'team_simulation_id' => $teamSimulation->id,
            'team_id' => $teamSimulation->team_id,
            'requested_by_user_id' => $actor->id,
            'focus' => $focus,
            'context_version' => FacultyInterpretationContextBuilder::CONTEXT_VERSION,
            'context_hash' => $this->contextHash($context),
            'prompt_version' => self::PROMPT_VERSION,
            'context_snapshot' => $context,
            'requested_at' => Carbon::now(),
        ]);

        GenerateInterpretationJob::dispatch($request->id);

        return $request;
    }

    /**
     * @return array<string, mixed>
     */
    public function buildContextFor(User $actor, TeamSimulation $teamSimulation, SectionSimulationWeek $runtimeWeek): array
    {
        $this->assertCanRequest($actor, $teamSimulation, $runtimeWeek);

        return $this->contextBuilder->build($teamSimulation, $runtimeWeek);
    }

    public function assertCanRequest(User $actor, TeamSimulation $teamSimulation, SectionSimulationWeek $runtimeWeek): void
    {
        if ($runtimeWeek->tenant_id !== $teamSimulation->tenant_id || $runtimeWeek->section_simulation_id !== $teamSimulation->section_simulation_id) {
            throw new InvalidArgumentException('Interpretation runtime week must match team simulation.');
        }

        if ($actor->tenant_id !== $teamSimulation->tenant_id) {
            throw new InvalidArgumentException('Actor cannot request interpretation for another tenant.');
        }

        if ($actor->isAdministrator()) {
            return;
        }

        if ($actor->isFaculty()) {
            $assigned = $actor->facultySections()
                ->wherePivot('tenant_id', $teamSimulation->tenant_id)
                ->whereHas('sectionSimulations', fn ($query) => $query->whereKey($teamSimulation->section_simulation_id))
                ->exists();

            if ($assigned) {
                return;
            }
        }

        throw new InvalidArgumentException('Only authorized faculty can request interpretive assistant output.');
    }

    /**
     * @param  array<string, mixed>  $context
     *
     * @throws JsonException
     */
    public function contextHash(array $context): string
    {
        return hash('sha256', json_encode($context, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));
    }
}
