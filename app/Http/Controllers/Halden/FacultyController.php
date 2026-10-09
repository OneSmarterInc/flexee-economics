<?php

namespace App\Http\Controllers\Halden;

use App\Halden\Game\DecisionBook;
use App\Halden\Game\QuarterRunner;
use App\Halden\Game\QuarterView;
use App\Http\Controllers\Controller;
use App\Models\Quarter;
use App\Models\Section;
use App\Models\Team;
use App\Models\TeamQuarter;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;
use RuntimeException;

class FacultyController extends Controller
{
    public function board(Request $request, DecisionBook $book, QuarterRunner $runner): Response
    {
        $section = $this->section($request);
        $quarter = $section->currentQuarter();
        $openPages = $quarter === null ? [] : array_values(array_intersect(array_keys(QuarterView::PAGE_TITLES), $book->openPages($quarter->number)));

        $teams = [];
        foreach ($section->teams()->with('members.user')->orderBy('name')->get() as $team) {
            $tq = $quarter === null ? null : TeamQuarter::query()->where('team_id', $team->id)->where('quarter_id', $quarter->id)->first();
            $saved = $tq->saved_pages ?? [];
            $times = array_filter([...array_column($saved, 'at'), $tq?->memo_saved_at?->toIso8601String()]);
            rsort($times);
            $words = $tq?->memo ? str_word_count(strip_tags($tq->memo)) : 0;
            $teams[] = [
                'id' => $team->id,
                'name' => $team->name,
                'members' => $team->members->count(),
                'pages' => array_map(fn (string $p) => ['page' => $p, 'title' => QuarterView::PAGE_TITLES[$p], 'changed' => isset($saved[$p])], $openPages),
                'memoWords' => $words,
                'ready' => $tq?->ready_at !== null,
                'lastActivity' => $times[0] ?? null,
                'score' => $tq?->score,
                'rank' => $tq?->rank,
                'flag' => $words === 0 ? 'No memo yet' : null,
            ];
        }
        $next = $quarter === null ? null : $section->quarters()->where('number', $quarter->number + 1)->first();

        return Inertia::render('halden/FacultyBoard', [
            'section' => ['id' => $section->id, 'name' => $section->name, 'course' => $section->course_name],
            'quarter' => $quarter === null ? null : [
                'id' => $quarter->id, 'number' => $quarter->number, 'label' => $quarter->label(), 'status' => $quarter->status,
                'deadline' => $quarter->deadline_at?->toIso8601String(),
                'deadlineText' => $quarter->deadline_at?->setTimezone('America/New_York')->format('l j M, g:i a'),
            ],
            'next' => $next === null ? null : ['id' => $next->id, 'label' => $next->label(), 'buildable' => $runner->hasMarket($next)],
            'pages' => array_map(fn (string $p) => ['page' => $p, 'title' => QuarterView::PAGE_TITLES[$p]], $openPages),
            'teams' => $teams,
            'quarters' => $section->quarters()->get()->map(fn (Quarter $q) => ['number' => $q->number, 'label' => $q->label(), 'status' => $q->status])->values(),
        ]);
    }

    public function action(Request $request, Quarter $quarter, string $action, QuarterRunner $runner): RedirectResponse
    {
        $section = $this->section($request);
        abort_unless($quarter->section_id === $section->id, 404);
        try {
            match ($action) {
                'open' => $runner->open($quarter),
                'close' => $runner->close($quarter),
                'publish' => $runner->publish($quarter),
                'extend' => $quarter->update(['deadline_at' => ($quarter->deadline_at ?? now())->addDay()]),
                default => abort(404),
            };
        } catch (RuntimeException $e) {
            throw ValidationException::withMessages(['action' => $e->getMessage()]);
        }

        return back()->with('done', $action);
    }

    public function viewTeam(Request $request, Team $team, Quarter $quarter, QuarterView $view): Response
    {
        $section = $this->section($request);
        abort_unless($team->section_id === $section->id && $quarter->section_id === $section->id, 404);

        return Inertia::render('halden/Play', $view->build($team, $quarter, null, readOnly: true) + ['startPage' => (string) $request->query('page', '')]);
    }

    private function section(Request $request): Section
    {
        /** @var User $user */
        $user = $request->user();
        abort_unless($user->isFaculty() || $user->isAdmin(), 403);
        $query = Section::query()->orderBy('id');
        if (! $user->isAdmin()) {
            $query->where('faculty_user_id', $user->id);
        }
        $section = $request->query('section') ? (clone $query)->whereKey((int) $request->query('section'))->first() : $query->first();
        abort_if($section === null, 404, 'You have no class yet.');

        return $section;
    }
}
