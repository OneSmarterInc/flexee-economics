<?php

namespace App\Domain\Advisors;

use App\Models\Advisor;
use App\Models\AdvisorConsultationSession;
use App\Models\AdvisorResponse;
use App\Models\SectionSimulationWeek;
use App\Models\TeamMember;
use App\Models\TeamSimulation;
use App\Models\User;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

final class AdvisorConsultationService
{
    public const WEEKLY_SLOT_LIMIT = 3;

    public function __construct(
        private readonly AdvisorCatalog $catalog,
    ) {}

    /**
     * @return Collection<int, Advisor>
     */
    public function availableAdvisors(): Collection
    {
        return $this->catalog->ensureHaldenAdvisors()
            ->filter(fn (Advisor $advisor): bool => $advisor->is_active)
            ->sortBy('sort_order')
            ->values();
    }

    /**
     * @param  array<string, mixed>  $context
     */
    public function requestConsultation(
        User $actor,
        SectionSimulationWeek $runtimeWeek,
        TeamSimulation $teamSimulation,
        Advisor $advisor,
        string $question,
        array $context = [],
    ): AdvisorConsultationSession {
        $this->assertCanConsult($actor, $runtimeWeek, $teamSimulation);
        $this->validateAdvisor($advisor);

        return DB::transaction(function () use ($actor, $runtimeWeek, $teamSimulation, $advisor, $question, $context): AdvisorConsultationSession {
            $existing = AdvisorConsultationSession::query()
                ->where('tenant_id', $teamSimulation->tenant_id)
                ->where('section_simulation_week_id', $runtimeWeek->id)
                ->where('team_simulation_id', $teamSimulation->id)
                ->where('advisor_id', $advisor->id)
                ->with('response')
                ->lockForUpdate()
                ->first();

            if ($existing instanceof AdvisorConsultationSession) {
                return $existing;
            }

            $usedSlots = AdvisorConsultationSession::query()
                ->where('tenant_id', $teamSimulation->tenant_id)
                ->where('section_simulation_week_id', $runtimeWeek->id)
                ->where('team_simulation_id', $teamSimulation->id)
                ->lockForUpdate()
                ->count();

            if ($usedSlots >= self::WEEKLY_SLOT_LIMIT) {
                throw new InvalidArgumentException('This team has used all advisor consultation slots for the week.');
            }

            $session = AdvisorConsultationSession::query()->create([
                'tenant_id' => $teamSimulation->tenant_id,
                'section_simulation_id' => $teamSimulation->section_simulation_id,
                'section_simulation_week_id' => $runtimeWeek->id,
                'team_simulation_id' => $teamSimulation->id,
                'team_id' => $teamSimulation->team_id,
                'advisor_id' => $advisor->id,
                'question' => $question,
                'context_snapshot' => $context,
                'requested_by_user_id' => $actor->id,
                'requested_at' => Carbon::now(),
            ]);

            AdvisorResponse::query()->create([
                'tenant_id' => $session->tenant_id,
                'advisor_consultation_session_id' => $session->id,
                'advisor_id' => $advisor->id,
                'content_version' => $advisor->content_version,
                'response' => $this->responseText($advisor),
                'response_snapshot' => [
                    'advisor_key' => $advisor->key,
                    'advisor_name' => $advisor->name,
                    'advisor_title' => $advisor->title,
                    'perspective' => $advisor->perspective,
                    'content_version' => $advisor->content_version,
                ],
                'responded_at' => Carbon::now(),
            ]);

            return $session->refresh()->load('response');
        });
    }

    /**
     * @return array{advisor: array{key: string, name: string, title: string, perspective: string}, question: string, response: string|null, content_version: string|null, requested_at: string|null, responded_at: string|null}
     */
    public function studentView(AdvisorConsultationSession $session): array
    {
        $session->loadMissing(['advisor', 'response']);
        $advisor = $session->advisor;
        $response = $session->response;

        return [
            'advisor' => [
                'key' => $advisor->key,
                'name' => $advisor->name,
                'title' => $advisor->title,
                'perspective' => $advisor->perspective,
            ],
            'question' => $session->question,
            'response' => $response?->response,
            'content_version' => $response?->content_version,
            'requested_at' => $session->requestedAtIso(),
            'responded_at' => $response?->respondedAtIso(),
        ];
    }

    public function assertCanView(User $actor, AdvisorConsultationSession $session): void
    {
        if ($actor->tenant_id !== $session->tenant_id) {
            throw new InvalidArgumentException('Actor cannot access advisor consultations for another tenant.');
        }

        if ($actor->isAdministrator()) {
            return;
        }

        if ($actor->isFaculty()) {
            $assigned = $actor->facultySections()
                ->wherePivot('tenant_id', $session->tenant_id)
                ->whereHas('sectionSimulations', fn ($query) => $query->whereKey($session->section_simulation_id))
                ->exists();

            if ($assigned) {
                return;
            }
        }

        if ($actor->isStudent()) {
            $member = TeamMember::query()
                ->where('tenant_id', $session->tenant_id)
                ->where('team_id', $session->team_id)
                ->where('user_id', $actor->id)
                ->exists();

            if ($member) {
                return;
            }
        }

        throw new InvalidArgumentException('Actor cannot access this team advisor consultation.');
    }

    private function assertCanConsult(User $actor, SectionSimulationWeek $runtimeWeek, TeamSimulation $teamSimulation): void
    {
        if ($runtimeWeek->tenant_id !== $teamSimulation->tenant_id || $runtimeWeek->section_simulation_id !== $teamSimulation->section_simulation_id) {
            throw new InvalidArgumentException('Advisor consultation week must match team simulation.');
        }

        if ($actor->tenant_id !== $teamSimulation->tenant_id) {
            throw new InvalidArgumentException('Actor cannot request advisor consultations for another tenant.');
        }

        if ($actor->isAdministrator() || $actor->isFaculty()) {
            return;
        }

        if ($actor->isStudent()) {
            $member = TeamMember::query()
                ->where('tenant_id', $teamSimulation->tenant_id)
                ->where('team_id', $teamSimulation->team_id)
                ->where('user_id', $actor->id)
                ->exists();

            if ($member) {
                return;
            }
        }

        throw new InvalidArgumentException('Actor cannot request advisor consultations for this team.');
    }

    private function validateAdvisor(Advisor $advisor): void
    {
        if (! $advisor->is_active) {
            throw new InvalidArgumentException('Inactive advisors cannot be consulted.');
        }
    }

    private function responseText(Advisor $advisor): string
    {
        return $advisor->default_guidance;
    }
}
