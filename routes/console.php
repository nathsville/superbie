<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

/*
|--------------------------------------------------------------------------
| Scheduled tasks
|--------------------------------------------------------------------------
|
| Complaint retention purge (FINAL business rule, Prompt 5B.1):
| permanently delete complaints 5 years after complaints.submitted_at.
| The scheduler only triggers the command; it does not change retention rules.
|
| Production note: `schedule:run` must be driven by the Laravel scheduler
| (e.g. a single cron entry: `* * * * * php artisan schedule:run`). Merely
| having this schedule defined does NOT make it run on its own.
|
*/
Schedule::command('complaints:purge-expired')->dailyAt('02:00');
