<?php

use App\Domain\Demo\DemoHealthCheckService;
use App\Models\Tenant;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Artisan::command('halden:demo-health', function (DemoHealthCheckService $health): int {
    $checks = $health->checks();

    foreach ($checks as $check) {
        $line = "{$check['name']}: {$check['status']} - {$check['detail']}";

        if ($check['status'] === 'ok') {
            $this->info($line);
        } else {
            $this->error($line);
        }
    }

    return $health->healthy() ? 0 : 1;
})->purpose('Check the Halden demo Week 4 runtime baseline');

Artisan::command('halden:demo-reset {--fresh : Run migrate:fresh before seeding}', function (): int {
    if ($this->option('fresh')) {
        $this->call('migrate:fresh', ['--force' => true]);
    } else {
        Tenant::query()
            ->where('slug', DemoHealthCheckService::DEMO_TENANT_SLUG)
            ->delete();
    }

    $this->call('db:seed', [
        '--class' => DatabaseSeeder::class,
        '--force' => true,
    ]);

    $exitCode = $this->call('halden:demo-health');

    if ($exitCode === 0) {
        $this->info('Halden demo reset complete.');
    }

    return $exitCode;
})->purpose('Rebuild the Halden demo tenant and Week 4 vertical-slice baseline');
