<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Scheduler (runs in the "scheduler" container: php artisan schedule:work).
Illuminate\Support\Facades\Schedule::command('horizon:snapshot')->everyFiveMinutes();
Illuminate\Support\Facades\Schedule::command('sanctum:prune-expired --hours=24')->daily();
Illuminate\Support\Facades\Schedule::command('queue:prune-failed --hours=720')->daily();
