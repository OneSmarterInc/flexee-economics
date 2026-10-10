<?php

namespace App\Http\Controllers\Halden;

use App\Halden\Ai\AdvisorRoom;
use App\Halden\Ai\HelpDesk;
use App\Halden\Content\ContentPack;
use App\Halden\Game\DecisionBook;
use App\Halden\Game\QuarterRunner;
use App\Halden\Game\QuarterView;
use App\Halden\OperatingModel\OperatingModel;
use App\Http\Controllers\Controller;
use App\Models\Quarter;
use App\Models\Team;
use App\Models\TeamMember;
use App\Models\TeamQuarter;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class PlayController extends Controller
{
    public function home(Request $request): RedirectResponse
    {
        $user = $this->user($request);
        if (! $user->isStudent()) {
            return redirect()->route('faculty.board');
        }
        $team = $this->teamOf($user);
        if ($user->opening_seen_at === null) {
            return redirect()->route('opening');
        }
        $quarter = $team->section->currentQuarter();
        abort_if($quarter === null, 404, 'This class has no quarters yet.');

        return redirect()->route('play.show', $quarter);
    }

    public function show(Request $request, Quarter $quarter, QuarterView $view): Response|RedirectResponse
    {
        $user = $this->user($request);
        $team = $this->teamOf($user);
        abort_unless($quarter->section_id === $team->section_id, 404);
        if ($user->opening_seen_at === null) {
            return redirect()->route('opening');
        }

        return Inertia::render('halden/Play', $view->build($team, $quarter, $user) + ['startPage' => (string) $request->query('page', '')]);
    }

    public function savePage(Request $request, Quarter $quarter, string $page, DecisionBook $book, QuarterRunner $runner, OperatingModel $model): RedirectResponse
    {
        $user = $this->user($request);
        $team = $this->teamOf($user);
        $this->assertOpen($team, $quarter);
        abort_unless(array_key_exists($page, QuarterView::PAGE_TITLES), 404);
        if ($quarter->isBoardQuarter()) {
            throw ValidationException::withMessages([$page => 'Nothing is set this quarter. Every page carries, and the board meets.']);
        }

        [$clean, $errors] = $book->validatePage($page, $request->all(), $quarter->number, $quarter->playEnd());
        if ($errors !== []) {
            throw ValidationException::withMessages($errors);
        }
        $tq = TeamQuarter::query()->firstOrCreate(['team_id' => $team->id, 'quarter_id' => $quarter->id]);
        if ($page === 'capital') {
            // A project under way never goes back to "hold" (it keeps going, pauses or is cancelled), a cancelled one
            // stays cancelled, and new commitments must fit this quarter's envelope.
            $previous = $book->previousEffective($team, $quarter);
            foreach ($previous as $key => $value) {
                if (! str_starts_with($key, 'proj_')) {
                    continue;
                }
                if ($value === 'cancel') {
                    $clean[$key] = 'cancel';
                } elseif (in_array($value, ['commit', 'pause'], true) && ! in_array($clean[$key] ?? 'hold', ['commit', 'pause', 'cancel'], true)) {
                    $clean[$key] = $value;
                }
            }
            $envelope = $runner->capitalTerms($quarter)['envelope'];
            $outlay = $book->newProjectOutlay(array_merge($tq->decisions ?? [], $clean), $previous);
            if ($outlay > $envelope + 1e-9) {
                throw ValidationException::withMessages(['capital' => sprintf(
                    "That's \$%sM of new projects. Ingrid can take up to \$%sM to the board this quarter, so hold one of them.",
                    number_format($outlay), number_format($envelope),
                )]);
            }
            if ($book->isOpen('port_helix_rotterdam', $quarter->number, $quarter->playEnd())) {
                $chosen = $book->portfolioChosen(array_merge($tq->decisions ?? [], $clean));
                $closed = $runner->startState($team, $quarter)->rotStatus === 'closed';
                $problems = $model->portfolioCheck($chosen, $closed);
                if ($problems !== []) {
                    throw ValidationException::withMessages(['capital' => $this->portfolioProblem($problems[0], $model)]);
                }
            }
        }
        $tq->decisions = array_merge($tq->decisions ?? [], $clean);
        $tq->saved_pages = array_merge($tq->saved_pages ?? [], [$page => ['by' => $user->name, 'at' => now()->toIso8601String()]]);
        $tq->save();

        return back()->with('saved', $page);
    }

    private function portfolioProblem(string $problem, OperatingModel $model): string
    {
        $data = $model->data;
        if ($problem === 'envelope') {
            return sprintf('That adds up to more than the $%sM you have to place. Selling the European stations adds $%sM; otherwise hold something.',
                number_format($model->portfolioDiscretionary()), number_format(-$data->portfolio['euro_retail_divest']['cost']));
        }
        if ($problem === 'rotterdam_closed') {
            return "Rotterdam is closed for good, so there's nothing there to convert to biofuels.";
        }
        $bucket = substr($problem, strlen('bucket:'));

        return sprintf("The board caps \"%s\" at \$%sM, and that's over it. Hold one of the projects in that group.",
            strtolower($data->buckets[$bucket]['label']), number_format($data->buckets[$bucket]['ceiling']));
    }

    /** The board defense (the last quarter): three parts, saved whole, by any teammate. Mirrored into the memo so the instructor's drafting tools read it. */
    public function saveDefense(Request $request, Quarter $quarter, ContentPack $content): RedirectResponse
    {
        $user = $this->user($request);
        $team = $this->teamOf($user);
        $this->assertOpen($team, $quarter);
        // In the last week of a 7-week course the board quarter is the week's second half; the defense lives there.
        $board = $quarter->boardQuarter();
        abort_unless($board !== null, 404);
        $parts = $content->quarter($board->number)['defense']['parts'];
        $rules = [];
        foreach ($parts as $p) {
            $rules[$p['key']] = ['nullable', 'string', 'max:40000'];
        }
        $data = $request->validate($rules, ['*.max' => 'That part is too long for the box. A few pages in total is plenty.']);
        $defense = [];
        $memo = [];
        foreach ($parts as $p) {
            $defense[$p['key']] = (string) ($data[$p['key']] ?? '');
            $memo[] = $p['title'].":\n".$defense[$p['key']];
        }
        $tq = TeamQuarter::query()->firstOrCreate(['team_id' => $team->id, 'quarter_id' => $board->id]);
        $tq->update(['defense' => $defense, 'defense_saved_by' => $user->id, 'defense_saved_at' => now(),
            'memo' => implode("\n\n", $memo), 'memo_saved_by' => $user->id, 'memo_saved_at' => now()]);

        return back()->with('saved', 'defense');
    }

    public function saveMemo(Request $request, Quarter $quarter): RedirectResponse
    {
        $user = $this->user($request);
        $team = $this->teamOf($user);
        $this->assertOpen($team, $quarter);
        $data = $request->validate(['memo' => ['nullable', 'string', 'max:20000']], ['memo.max' => 'Your memo is too long for the box. Aim for about 250 words.']);

        $tq = TeamQuarter::query()->firstOrCreate(['team_id' => $team->id, 'quarter_id' => $quarter->id]);
        $tq->update(['memo' => $data['memo'] ?? '', 'memo_saved_by' => $user->id, 'memo_saved_at' => now()]);

        return back()->with('saved', 'memo');
    }

    public function toggleReady(Request $request, Quarter $quarter): RedirectResponse
    {
        $user = $this->user($request);
        $team = $this->teamOf($user);
        $this->assertOpen($team, $quarter);
        $member = TeamMember::query()->where('team_id', $team->id)->where('user_id', $user->id)->firstOrFail();
        if ($member->seat !== 'evp') {
            throw ValidationException::withMessages(['ready' => 'Only the EVP on your team can do this.']);
        }
        $tq = TeamQuarter::query()->firstOrCreate(['team_id' => $team->id, 'quarter_id' => $quarter->id]);
        $tq->update($tq->ready_at === null ? ['ready_at' => now(), 'ready_by' => $user->id] : ['ready_at' => null, 'ready_by' => null]);

        return back();
    }

    /** The help button: one question, one answer, nothing stored. */
    public function help(Request $request, HelpDesk $desk): JsonResponse
    {
        $this->user($request);
        $data = $request->validate(['question' => ['required', 'string', 'max:2000']]);

        return response()->json(['answer' => $desk->answer(trim($data['question']))]);
    }

    /** A teammate asks an advisor a question. The whole team shares the conversation. */
    public function ask(Request $request, Quarter $quarter, string $advisor, AdvisorRoom $room): RedirectResponse
    {
        $user = $this->user($request);
        $team = $this->teamOf($user);
        abort_unless($quarter->section_id === $team->section_id, 404);
        $data = $request->validate(
            ['question' => ['required', 'string', 'max:4000']],
            ['question.required' => 'Type a question first.', 'question.max' => 'That question is too long. Try asking it in two parts.'],
        );
        try {
            $room->ask($team, $quarter, $advisor, $user, trim($data['question']));
        } catch (\RuntimeException $e) {
            // Out of answers or not allowed: refuse with 403 so a blocked team can't keep spending.
            if ($request->header('X-Inertia')) {
                throw ValidationException::withMessages(['question' => $e->getMessage()]);
            }
            abort(403, $e->getMessage());
        }

        return back();
    }

    /** A meeting: one question to two to four advisors at once. */
    public function meet(Request $request, Quarter $quarter, AdvisorRoom $room): RedirectResponse
    {
        $user = $this->user($request);
        $team = $this->teamOf($user);
        abort_unless($quarter->section_id === $team->section_id, 404);
        $data = $request->validate(
            ['question' => ['required', 'string', 'max:4000'], 'invited' => ['required', 'array'], 'invited.*' => ['string', 'max:40']],
            ['question.required' => 'Type a question first.', 'question.max' => 'That question is too long. Try asking it in two parts.', 'invited.required' => 'Pick who is in the meeting first.'],
        );
        try {
            $room->meet($team, $quarter, array_values(array_filter($data['invited'], 'is_string')), $user, trim($data['question']));
        } catch (\RuntimeException $e) {
            if ($request->header('X-Inertia')) {
                throw ValidationException::withMessages(['question' => $e->getMessage()]);
            }
            abort(403, $e->getMessage());
        }

        return back();
    }

    /** Students only get a quarter's reading once that quarter has opened for their class. */
    public function exhibit(Request $request, string $path, ContentPack $content): BinaryFileResponse
    {
        $n = $content->exhibitQuarter($path);
        abort_if($n === null, 404);
        $user = $this->user($request);
        if ($user->isStudent()) {
            $quarter = $this->teamOf($user)->section->quarters()->where('number', $n)->first();
            abort_if($quarter === null || $quarter->status === Quarter::UPCOMING, 404);
        }
        try {
            $file = ContentPack::exhibitPath($path);
        } catch (\RuntimeException) {
            abort(404);
        }

        return response()->download($file, basename($file));
    }

    private function user(Request $request): User
    {
        /** @var User $user */
        $user = $request->user();

        return $user;
    }

    private function teamOf(User $user): Team
    {
        $member = TeamMember::query()->where('user_id', $user->id)->with('team.section')->first();
        abort_if($member === null, 403, "You haven't been put on a team yet. Your instructor will add you.");

        return $member->team;
    }

    private function assertOpen(Team $team, Quarter $quarter): void
    {
        abort_unless($quarter->section_id === $team->section_id, 404);
        if ($quarter->status !== Quarter::OPEN) {
            throw ValidationException::withMessages(['quarter' => 'This quarter is closed, so it can\'t be changed.']);
        }
    }
}
