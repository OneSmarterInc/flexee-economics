<?php

namespace App\Http\Controllers\Halden;

use App\Halden\Content\ContentPack;
use App\Http\Controllers\Controller;
use App\Models\Quarter;
use App\Models\Team;
use App\Models\TeamMember;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class OpeningController extends Controller
{
    public function show(Request $request, ContentPack $content): Response
    {
        /** @var User $user */
        $user = $request->user();
        $member = TeamMember::query()->where('user_id', $user->id)->with('team.section')->first();
        $team = $member?->team;
        $opening = $content->opening();
        $weeks = (string) ($team?->section->weeks ?? 14);
        $byWeeks = (array) ($opening['screens']['quarter']['intro_by_weeks'] ?? []);
        $opening['screens']['quarter']['intro'] = (string) ($byWeeks[$weeks] ?? $byWeeks['14'] ?? '');

        return Inertia::render('halden/Opening', [
            'opening' => $opening,
            'team' => $team === null ? null : [
                'name' => $team->name,
                'firstMeeting' => $team->first_meeting,
                'become' => $team->strategy_become,
                'by' => $team->strategy_by,
            ],
            'isEvp' => $member?->seat === 'evp',
            'canChange' => $member !== null && $member->seat === 'evp' && $this->stillOpen($member->team),
            'replay' => $user->opening_seen_at !== null,
        ]);
    }

    /**
     * Everyone can finish the opening. Only the EVP records the team's first meeting and sentence,
     * and can change them until Quarter 1 closes.
     */
    public function finish(Request $request): RedirectResponse
    {
        /** @var User $user */
        $user = $request->user();
        $data = $request->validate([
            'first_meeting' => ['nullable', 'in:marcus,ingrid'],
            'become' => ['nullable', 'string', 'max:300'],
            'by' => ['nullable', 'string', 'max:300'],
        ]);
        $member = TeamMember::query()->where('user_id', $user->id)->with('team.section')->first();
        if ($member !== null && $member->seat === 'evp' && $this->stillOpen($member->team)) {
            $team = $member->team;
            if (! empty($data['first_meeting'])) {
                $team->first_meeting = $data['first_meeting'];
            }
            $become = trim((string) ($data['become'] ?? ''));
            $by = trim((string) ($data['by'] ?? ''));
            if ($become !== '' && $by !== '') {
                $team->strategy_become = $become;
                $team->strategy_by = $by;
            }
            $team->save();
        }
        if ($user->opening_seen_at === null) {
            $user->forceFill(['opening_seen_at' => now()])->save();
        }

        return redirect()->route('play.home');
    }

    /** The team's opening choices can change until Quarter 1 has been run. */
    private function stillOpen(Team $team): bool
    {
        $q1 = $team->section->quarters()->where('number', 1)->first();

        return $q1 === null || in_array($q1->status, [Quarter::UPCOMING, Quarter::OPEN], true);
    }
}
