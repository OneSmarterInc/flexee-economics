<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Quarters close on their own at the deadline. Needs one cron line on the server:
// * * * * * cd ~/public_html/economics.flexee.org && php artisan schedule:run >> /dev/null 2>&1
Schedule::command('halden:close-due')->everyMinute()->withoutOverlapping();
// Deadline reminders (24 h, 4 h, 1 h) when outgoing mail is on.
Schedule::command('halden:remind')->everyFiveMinutes()->withoutOverlapping();
