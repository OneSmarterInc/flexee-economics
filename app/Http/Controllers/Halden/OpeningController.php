<?php

namespace App\Http\Controllers\Halden;

use App\Halden\Content\ContentPack;
use App\Http\Controllers\Controller;
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
        $team = TeamMember::query()->where('user_id', $user->id)->with('team')->first()?->team;

        return Inertia::render('halden/Opening', [
            'opening' => $content->opening(),
            'team' => $team === null ? null : [
                'name' => $team->name,
                'firstMeeting' => $team->first_meeting,
                'become' => $team->strategy_become,
                'by' => $team->strategy_by,
            ],
            'replay' => $user->opening_seen_at !== null,
        ]);
    }

    public function finish(Request $request): RedirectResponse
    {
        /** @var User $user */
        $user = $request->user();
        $data = $request->validate([
            'first_meeting' => ['nullable', 'in:marcus,ingrid'],
            'become' => ['nullable', 'string', 'max:300'],
            'by' => ['nullable', 'string', 'max:300'],
        ]);
        $team = TeamMember::query()->where('user_id', $user->id)->with('team')->first()?->team;
        if ($team !== null) {
            $updates = [];
            if ($team->first_meeting === null && ! empty($data['first_meeting'])) {
                $updates['first_meeting'] = $data['first_meeting'];
            }
            if ($team->strategy_become === null && ! empty($data['become']) && ! empty($data['by'])) {
                $updates['strategy_become'] = trim($data['become']);
                $updates['strategy_by'] = trim($data['by']);
            }
            if ($updates !== []) {
                $team->update($updates);
            }
        }
        if ($user->opening_seen_at === null) {
            $user->forceFill(['opening_seen_at' => now()])->save();
        }

        return redirect()->route('play.home');
    }
}
