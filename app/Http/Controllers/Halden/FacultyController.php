<?php

namespace App\Http\Controllers\Halden;

use App\Halden\Ai\Carrying;
use App\Halden\Ai\FacultyDrafts;
use App\Halden\Content\ContentPack;
use App\Halden\Game\BoardVerdict;
use App\Halden\Game\DecisionBook;
use App\Halden\Game\QuarterRunner;
use App\Halden\Game\QuarterView;
use App\Http\Controllers\Controller;
use App\Models\AdvisorMessage;
use App\Models\FacultyDraft;
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

use function Illuminate\Support\defer;

class FacultyController extends Controller
{
    public function board(Request $request, DecisionBook $book, QuarterRunner $runner, ContentPack $content): Response
    {
        $section = $this->section($request);
        $quarter = $section->currentQuarter();
        $openPages = $quarter === null ? [] : array_values(array_intersect(array_keys(QuarterView::PAGE_TITLES), $book->openPages($quarter->number)));

        // Feedback is written on the latest quarter that has been run: this one once it closes, otherwise the one before.
        $fbQuarter = $quarter === null ? null : (in_array($quarter->status, [Quarter::CLOSED, Quarter::PUBLISHED], true) ? $quarter : $quarter->previous());

        $teams = [];
        foreach ($section->teams()->with('members.user')->orderBy('name')->get() as $team) {
            $tq = $quarter === null ? null : TeamQuarter::query()->where('team_id', $team->id)->where('quarter_id', $quarter->id)->first();
            $fbTq = $fbQuarter === null ? null : TeamQuarter::query()->where('team_id', $team->id)->where('quarter_id', $fbQuarter->id)->first();
            $saved = $tq->saved_pages ?? [];
            $times = array_filter([...array_column($saved, 'at'), $tq?->memo_saved_at?->toIso8601String(), $tq?->ready_at?->toIso8601String()]);
            rsort($times);
            $words = $tq?->memo ? str_word_count(strip_tags($tq->memo)) : 0;
            $teams[] = [
                'id' => $team->id,
                'name' => $team->name,
                'members' => $team->members->count(),
                'pages' => array_map(fn (string $p) => ['page' => $p, 'title' => QuarterView::PAGE_TITLES[$p], 'changed' => isset($saved[$p])], $openPages),
                'memoWords' => $words,
                'ready' => $tq?->ready_at !== null,
                'feedback' => $fbTq?->feedback_published_at !== null ? 'published' : ($fbTq !== null && FacultyDraft::query()->where('team_quarter_id', $fbTq->id)->exists() ? 'drafted' : 'none'),
                'defense' => $tq?->defense_saved_at !== null,
                'verdict' => $tq?->verdict_published_at !== null ? 'published' : ($tq?->verdict !== null ? 'decided' : 'none'),
                'advisorAnswers' => $quarter === null ? 0 : AdvisorMessage::billable($team->id, $quarter->id),
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
                'isBoard' => $quarter->isBoardQuarter(), 'world' => $quarter->world,
                'deadline' => $quarter->deadline_at?->toIso8601String(),
                'deadlineText' => $quarter->deadline_at?->setTimezone('America/New_York')->format('l j M, g:i a'),
            ],
            'feedbackQuarter' => $fbQuarter === null ? null : ['id' => $fbQuarter->id, 'label' => $fbQuarter->label()],
            'next' => $next === null ? null : ['id' => $next->id, 'label' => $next->label(), 'buildable' => $runner->hasMarket($next) && $content->hasQuarter($next->number)],
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
                'open' => $this->openAndWriteNotes($runner, $quarter),
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

    public function feedback(Request $request, Team $team, Quarter $quarter, FacultyDrafts $drafts, BoardVerdict $verdict, ContentPack $content): Response
    {
        $tq = $this->teamQuarter($request, $team, $quarter);
        $board = null;
        if ($quarter->isBoardQuarter() && $content->hasQuarter($quarter->number)) {
            $outcomes = $verdict->outcomes($team, $quarter);
            $c = $content->quarter($quarter->number);
            $board = [
                'defense' => $tq->defense ?? [],
                'parts' => $c['defense']['parts'],
                'reasoning' => $tq->reasoning,
                'outcomes' => $outcomes,
                'suggested' => $verdict->suggested($tq->reasoning, $outcomes['strong']),
                'tier' => $verdict->tierLabel($tq->reasoning, $outcomes['strong']),
                'verdict' => $tq->verdict,
                'publishedAt' => $tq->verdict_published_at?->toIso8601String(),
                'endings' => array_map(fn (string $k) => ['key' => $k, 'title' => $c['endings'][$k]['title']], BoardVerdict::ENDINGS),
                'resultsPublished' => $quarter->status === Quarter::PUBLISHED,
            ];
        }
        $latest = [];
        foreach (FacultyDraft::KINDS as $kind) {
            $d = FacultyDraft::query()->where('team_quarter_id', $tq->id)->where('kind', $kind)->latest('id')->first();
            $latest[$kind] = $d === null ? null : [
                'status' => $d->status, 'text' => $d->text, 'data' => $d->data, 'reason' => $d->dropped_reason,
                'at' => $d->created_at?->toIso8601String(),
            ];
        }
        $section = $this->section($request);

        return Inertia::render('halden/FacultyFeedback', [
            'section' => ['id' => $section->id, 'name' => $section->name],
            'team' => ['id' => $team->id, 'name' => $team->name],
            'quarter' => ['id' => $quarter->id, 'number' => $quarter->number, 'label' => $quarter->label(), 'status' => $quarter->status],
            'enabled' => $drafts->enabled(),
            'text' => $drafts->screenText(),
            'labels' => $drafts->labels(),
            'inputs' => $drafts->inputs($tq),
            'drafts' => $latest,
            'board' => $board,
            'feedback' => [
                'text' => (string) $tq->feedback,
                'writingScoreAi' => $tq->writing_score_ai,
                'writingAdjustment' => $tq->writing_adjustment,
                'writingScore' => $tq->writing_score,
                'publishedAt' => $tq->feedback_published_at?->toIso8601String(),
            ],
        ]);
    }

    /** The board's verdict on one team: the instructor's reasoning call, the ending, and whether students can see it. */
    public function saveVerdict(Request $request, Team $team, Quarter $quarter, BoardVerdict $verdict): RedirectResponse
    {
        $tq = $this->teamQuarter($request, $team, $quarter);
        abort_unless($quarter->isBoardQuarter(), 404);
        $data = $request->validate([
            'reasoning' => ['nullable', 'in:strong,weak'],
            'verdict' => ['nullable', 'in:'.implode(',', BoardVerdict::ENDINGS)],
            'publish' => ['boolean'],
        ]);
        $tq->reasoning = $data['reasoning'] ?? null;
        $tq->verdict = $data['verdict'] ?? $verdict->suggested($tq->reasoning, $verdict->outcomes($team, $quarter)['strong']);
        if ($request->boolean('publish')) {
            if ($tq->verdict === null) {
                throw ValidationException::withMessages(['verdict' => 'Decide whether the reasoning was strong before publishing the verdict.']);
            }
            if ($quarter->status !== Quarter::PUBLISHED) {
                throw ValidationException::withMessages(['verdict' => 'Show the quarter\'s results first; the verdict goes out after them.']);
            }
            $tq->verdict_published_at = now();
        }
        $tq->save();

        return back();
    }

    public function draftFeedback(Request $request, Team $team, Quarter $quarter, FacultyDrafts $drafts): RedirectResponse
    {
        $tq = $this->teamQuarter($request, $team, $quarter);
        /** @var User $user */
        $user = $request->user();
        try {
            $drafts->draftAll($tq, $user);
        } catch (RuntimeException $e) {
            throw ValidationException::withMessages(['draft' => $e->getMessage()]);
        }

        return back();
    }

    public function saveFeedback(Request $request, Team $team, Quarter $quarter): RedirectResponse
    {
        $tq = $this->teamQuarter($request, $team, $quarter);
        $data = $request->validate([
            'feedback' => ['nullable', 'string', 'max:20000'],
            'writing_adjustment' => ['nullable', 'integer', 'min:-4', 'max:4'],
            'writing_score' => ['nullable', 'integer', 'min:1', 'max:5'],
            'publish' => ['boolean'],
        ]);
        $tq->feedback = $data['feedback'] ?? null;
        // With an AI proposal, faculty adjust it up or down (starting at zero). Without one, they score it themselves.
        if ($tq->writing_score_ai !== null) {
            $tq->writing_adjustment = (int) ($data['writing_adjustment'] ?? 0);
        } else {
            $tq->writing_score = $data['writing_score'] ?? null;
        }
        $tq->writing_score = $tq->finalWritingScore();
        if ($request->boolean('publish')) {
            if (trim((string) $tq->feedback) === '') {
                throw ValidationException::withMessages(['feedback' => 'Write or paste the feedback before publishing it.']);
            }
            $tq->feedback_published_at = now();
        }
        $tq->save();

        return back();
    }

    /** Opens the quarter, then writes each team's "What you're carrying" note after the page has been sent. */
    private function openAndWriteNotes(QuarterRunner $runner, Quarter $quarter): void
    {
        $runner->open($quarter);
        defer(fn () => app(Carrying::class)->writeFor($quarter->refresh()));
    }

    private function teamQuarter(Request $request, Team $team, Quarter $quarter): TeamQuarter
    {
        $section = $this->section($request);
        abort_unless($team->section_id === $section->id && $quarter->section_id === $section->id, 404);
        $tq = TeamQuarter::query()->where('team_id', $team->id)->where('quarter_id', $quarter->id)->with(['quarter', 'team'])->first();
        abort_if($tq === null || $tq->results === null, 404, 'This quarter has not been run for this team yet.');

        return $tq;
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
