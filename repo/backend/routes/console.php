<?php

use App\Jobs\ComputeRecommendations;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Schedule::command('ride:auto-cancel-unmatched')->everyMinute();
Schedule::command('ride:auto-revert-no-show')->everyMinute();
Schedule::command('ride:disband-stale-exception-chats')->everyMinute();
Schedule::job(new ComputeRecommendations())->dailyAt('02:00');
