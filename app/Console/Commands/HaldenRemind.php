<?php

namespace App\Console\Commands;

use App\Halden\Mail\Outgoing;
use App\Models\Quarter;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Deadline reminders: 24 hours, 4 hours and 1 hour before an open quarter closes, to every student on a team that
 * hasn't pressed Ready. Each goes once; when a quarter opens inside a window, only the nearest one goes out. Runs
 * from the scheduler; does nothing while outgoing mail is off.
 */
class HaldenRemind extends Command
{
    protected $signature = 'halden:remind';

    protected $description = 'Send deadline reminders for open quarters';

    /** Hours before the deadline, largest first. */
    public const WINDOWS = ['24h' => 24, '4h' => 4, '1h' => 1];

    public function handle(): int
    {
        if (! Outgoing::enabled()) {
            return self::SUCCESS;
        }
        $now = now();
        foreach (Quarter::query()->where('status', Quarter::OPEN)->whereNotNull('deadline_at')->where('deadline_at', '>', $now)->get() as $quarter) {
            $due = [];
            foreach (self::WINDOWS as $kind => $hours) {
                if ($quarter->deadline_at->subHours($hours)->lessThanOrEqualTo($now)) {
                    $due[] = $kind;
                }
            }
            if ($due === []) {
                continue;
            }
            $already = DB::table('quarter_reminders')->where('quarter_id', $quarter->id)->pluck('kind')->all();
            $unsent = array_values(array_diff($due, $already));
            if ($unsent === []) {
                continue;
            }
            // The nearest window that is due goes out; the larger ones it overtook are marked so they never fire late.
            $send = end($unsent);
            $sent = Outgoing::remind($quarter, $this->hoursText($send));
            foreach ($unsent as $kind) {
                DB::table('quarter_reminders')->insert(['quarter_id' => $quarter->id, 'kind' => $kind, 'sent' => $kind === $send ? $sent : 0, 'created_at' => $now, 'updated_at' => $now]);
            }
            $this->info("{$quarter->section->name} {$quarter->label()}: $send reminder to $sent students.");
        }

        return self::SUCCESS;
    }

    private function hoursText(string $kind): string
    {
        return match ($kind) {
            '24h' => 'a day', '4h' => 'four hours', default => 'an hour',
        };
    }
}
