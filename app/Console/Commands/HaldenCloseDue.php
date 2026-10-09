<?php

namespace App\Console\Commands;

use App\Halden\Game\QuarterRunner;
use App\Models\Quarter;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Closes every open quarter whose deadline has passed (design D7). Results stay hidden until faculty
 * show them. Run every minute by the scheduler.
 */
class HaldenCloseDue extends Command
{
    protected $signature = 'halden:close-due';

    protected $description = 'Close and run every open quarter whose deadline has passed';

    public function handle(QuarterRunner $runner): int
    {
        $due = Quarter::query()->where('status', Quarter::OPEN)->whereNotNull('deadline_at')->where('deadline_at', '<=', now())->get();
        foreach ($due as $quarter) {
            try {
                $runner->close($quarter);
                $this->info("Closed {$quarter->label()} for section {$quarter->section_id}.");
            } catch (Throwable $e) {
                Log::error('Automatic close failed', ['quarter' => $quarter->id, 'error' => $e->getMessage()]);
                $this->error("Could not close {$quarter->label()}: {$e->getMessage()}");
            }
        }

        return self::SUCCESS;
    }
}
