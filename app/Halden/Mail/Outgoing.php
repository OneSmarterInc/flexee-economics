<?php

namespace App\Halden\Mail;

use App\Mail\HaldenMail;
use App\Models\Enrolment;
use App\Models\Quarter;
use App\Models\Section;
use App\Models\TeamMember;
use App\Models\TeamQuarter;
use App\Models\User;
use Illuminate\Support\Facades\Mail;

/**
 * The emails Halden sends students: their login when an instructor adds them, a note when they're added with a
 * login they already have, and reminders before a deadline. Nothing goes out until the server has outgoing mail
 * switched on (`MAIL_MAILER` other than `log`); the screens say so. Wording lives in `packages/content/mail.json`.
 */
final class Outgoing
{
    /** @var array<string, array{subject: string, lines: list<string>}>|null */
    private static ?array $content = null;

    public static function enabled(): bool
    {
        return (bool) config('halden.mail_enabled');
    }

    public static function login(Section $section, User $student, string $password): void
    {
        self::send('login', $student, $section, ['password' => $password, 'login_url' => route('login')]);
    }

    public static function added(Section $section, User $student): void
    {
        self::send('added', $student, $section, ['login_url' => route('login')]);
    }

    /**
     * A reminder to every student on a team that hasn't pressed Ready for an open quarter.
     *
     * @return int how many were sent
     */
    public static function remind(Quarter $quarter, string $hours, string $kind = 'reminder'): int
    {
        if (! self::enabled() || $quarter->status !== Quarter::OPEN || $quarter->deadline_at === null) {
            return 0;
        }
        $section = $quarter->section;
        $readyTeams = TeamQuarter::query()->where('quarter_id', $quarter->id)->whereNotNull('ready_at')->pluck('team_id')->all();
        $members = TeamMember::query()->whereIn('team_id', $section->teams()->select('id'))->whereNotIn('team_id', $readyTeams)->with('user')->get();
        $active = Enrolment::query()->where('section_id', $section->id)->where('status', Enrolment::ACTIVE)->pluck('user_id')->all();
        $sent = 0;
        foreach ($members as $m) {
            if (! in_array($m->user_id, $active, true)) {
                continue;
            }
            self::send($kind, $m->user, $section, [
                'quarter' => $quarter->weekLabel(), 'hours' => $hours,
                'deadline' => $quarter->deadline_at->setTimezone('America/New_York')->format('l j F, g:i a'),
                'play_url' => route('play.show', $quarter),
            ]);
            $sent++;
        }

        return $sent;
    }

    /** @param  array<string, string>  $fill */
    private static function send(string $kind, User $to, Section $section, array $fill): void
    {
        if (! self::enabled()) {
            return;
        }
        $c = self::content()[$kind];
        $fill += ['name' => $to->name, 'class' => $section->name, 'course' => $section->course_name, 'instructor' => $section->faculty->name, 'email' => $to->email];
        $swap = fn (string $s): string => strtr($s, array_combine(array_map(fn ($k) => '{'.$k.'}', array_keys($fill)), array_values($fill)));
        Mail::to($to->email, $to->name)->send(new HaldenMail($swap($c['subject']), array_map($swap, $c['lines'])));
    }

    /** @return array<string, array{subject: string, lines: list<string>}> */
    private static function content(): array
    {
        if (self::$content === null) {
            $raw = json_decode((string) file_get_contents(base_path('packages/content/mail.json')), true, flags: JSON_THROW_ON_ERROR);
            unset($raw['_note']);
            self::$content = $raw;
        }

        return self::$content;
    }
}
