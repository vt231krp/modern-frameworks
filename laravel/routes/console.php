<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote')->hourly();

// Cron operation: flip pending payments whose due date has passed to "overdue".
Schedule::command('payments:mark-overdue')->dailyAt('00:10');
