<?php

declare(strict_types=1);

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// EF-1202: retention purge runs once a day (spec §8 "Conformité").
Schedule::command('applications:purge-expired')->daily();

// EF-1102: daily digest of the applications received in the last 24 hours.
Schedule::command('notifications:daily-digest')->dailyAt('07:00');
