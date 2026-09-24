<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// NFR-5.4 / UC-07. Scheduled automated database backup by System Clock actor.
Schedule::command('payroll:backup')->dailyAt('02:00');

// FR-6.3 / UC-I6. Transactional anchor outbox processor runs every 5 minutes.
Schedule::command('integrity:process-outbox')->everyFiveMinutes();

