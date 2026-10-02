<?php

namespace App\Domain\Consequences;

use App\Models\ConsequenceDefinition;
use App\Models\ConsequenceLink;
use App\Models\SectionSimulationWeek;
use App\Models\TeamMember;
use App\Models\TeamSimulation;
use App\Models\User;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

final class ConsequenceService
{
    /**
     * @param  array<string, mixed>  $metadata
     */
    public function createLink(
        TeamSimulation $teamSimulation,
        ConsequenceDefinition $definition,
        Model $source,
        Model $target,
        string $explanation,
        ?SectionSimulationWeek $sourceWeek = null,
        ?SectionSimulationWeek $targetWeek = null,
        ?User $actor = null,
        array $metadata = [],
    ): ConsequenceLink {
        $this->validateDefinition($definition, $source, $target);
        $this->validateRuntimeWeek($teamSimulation, $sourceWeek);
        $this->validateRuntimeWeek($teamSimulation, $targetWeek);
        $this->validateEntityContext($teamSimulation, $source, 'source');
        $this->validateEntityContext($teamSimulation, $target, 'target');

        if ($actor !== null && $actor->tenant_id !== $teamSimulation->tenant_id) {
            throw new InvalidArgumentException('Consequence actor must belong to the team simulation tenant.');
        }

        return DB::transaction(fn (): ConsequenceLink => ConsequenceLink::query()->create([
            'tenant_id' => $teamSimulation->tenant_id,
            'section_simulation_id' => $teamSimulation->section_simulation_id,
            'team_simulation_id' => $teamSimulation->id,
            'team_id' => $teamSimulation->team_id,
            'source_section_simulation_week_id' => $sourceWeek?->id,
            'target_section_simulation_week_id' => $targetWeek?->id,
            'consequence_definition_id' => $definition->id,
            'definition_key' => $definition->key,
            'definition_version' => $definition->version,
            'source_type' => $source->getMorphClass(),
            'source_id' => $source->getKey(),
            'target_type' => $target->getMorphClass(),
            'target_id' => $target->getKey(),
            'effect_type' => $definition->effect_type,
            'explanation' => $explanation,
            'metadata' => $metadata,
            'created_by_user_id' => $actor?->id,
            'occurred_at' => Carbon::now(),
        ]));
    }

    /**
     * @return Collection<int, ConsequenceLink>
     */
    public function forwardFrom(Model $source, ?TeamSimulation $teamSimulation = null): Collection
    {
        $query = ConsequenceLink::query()
            ->where('source_type', $source->getMorphClass())
            ->where('source_id', $source->getKey())
            ->orderBy('occurred_at')
            ->orderBy('id');

        if ($teamSimulation !== null) {
            $query->where('tenant_id', $teamSimulation->tenant_id)
                ->where('team_simulation_id', $teamSimulation->id);
        }

        return $query->get();
    }

    /**
     * @return Collection<int, ConsequenceLink>
     */
    public function backwardTo(Model $target, ?TeamSimulation $teamSimulation = null): Collection
    {
        $query = ConsequenceLink::query()
            ->where('target_type', $target->getMorphClass())
            ->where('target_id', $target->getKey())
            ->orderBy('occurred_at')
            ->orderBy('id');

        if ($teamSimulation !== null) {
            $query->where('tenant_id', $teamSimulation->tenant_id)
                ->where('team_simulation_id', $teamSimulation->id);
        }

        return $query->get();
    }

    public function assertCanView(User $actor, ConsequenceLink $link): void
    {
        if ($actor->tenant_id !== $link->tenant_id) {
            throw new InvalidArgumentException('Actor cannot access consequence links for another tenant.');
        }

        if ($actor->isAdministrator()) {
            return;
        }

        if ($actor->isFaculty()) {
            $assigned = $actor->facultySections()
                ->wherePivot('tenant_id', $link->tenant_id)
                ->whereHas('sectionSimulations', fn ($query) => $query->whereKey($link->section_simulation_id))
                ->exists();

            if ($assigned) {
                return;
            }
        }

        if ($actor->isStudent()) {
            $member = TeamMember::query()
                ->where('tenant_id', $link->tenant_id)
                ->where('team_id', $link->team_id)
                ->where('user_id', $actor->id)
                ->exists();

            if ($member) {
                return;
            }
        }

        throw new InvalidArgumentException('Actor cannot access this team consequence link.');
    }

    private function validateDefinition(ConsequenceDefinition $definition, Model $source, Model $target): void
    {
        if (! $definition->is_active) {
            throw new InvalidArgumentException('Inactive consequence definitions cannot create links.');
        }

        if ($definition->source_type !== $source->getMorphClass() || $definition->target_type !== $target->getMorphClass()) {
            throw new InvalidArgumentException('Consequence definition source and target types must match linked entities.');
        }
    }

    private function validateRuntimeWeek(TeamSimulation $teamSimulation, ?SectionSimulationWeek $runtimeWeek): void
    {
        if ($runtimeWeek === null) {
            return;
        }

        if ($runtimeWeek->tenant_id !== $teamSimulation->tenant_id || $runtimeWeek->section_simulation_id !== $teamSimulation->section_simulation_id) {
            throw new InvalidArgumentException('Consequence runtime week must match team simulation.');
        }
    }

    private function validateEntityContext(TeamSimulation $teamSimulation, Model $entity, string $role): void
    {
        $tenantId = $entity->getAttribute('tenant_id');

        if ($tenantId !== null && $tenantId !== $teamSimulation->tenant_id) {
            throw new InvalidArgumentException("Consequence {$role} tenant must match team simulation.");
        }

        $sectionSimulationId = $entity->getAttribute('section_simulation_id');

        if ($sectionSimulationId !== null && $sectionSimulationId !== $teamSimulation->section_simulation_id) {
            throw new InvalidArgumentException("Consequence {$role} section simulation must match team simulation.");
        }

        $teamSimulationId = $entity->getAttribute('team_simulation_id');

        if ($teamSimulationId !== null && $teamSimulationId !== $teamSimulation->id) {
            throw new InvalidArgumentException("Consequence {$role} team simulation must match link team simulation.");
        }

        $teamId = $entity->getAttribute('team_id');

        if ($teamId !== null && $teamId !== $teamSimulation->team_id) {
            throw new InvalidArgumentException("Consequence {$role} team must match link team.");
        }
    }
}
