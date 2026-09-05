<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Schedule::command('trends:collect')->everyFifteenMinutes()->withoutOverlapping(10);
Schedule::command('automation:midnight')->dailyAt('00:01')->withoutOverlapping(60);
Schedule::command('automation:monthly-special-days')->monthlyOn(1, '00:01')->withoutOverlapping(60);
Schedule::command('schedules:run')->everyMinute()->withoutOverlapping(5);
Schedule::command('analytics:aggregate')->hourlyAt(5)->withoutOverlapping(10);
Schedule::command('news:import')->hourly()->withoutOverlapping(55);
Schedule::command('news:pipeline')->everyMinute()->withoutOverlapping(5);
Schedule::command('app:retry-failed-automation')->everyTwoMinutes()->withoutOverlapping(5);
Schedule::command('news:purge-expired')->hourly()->withoutOverlapping(10);
Schedule::command('app:generate-daily-menu')->dailyAt('00:01')->withoutOverlapping(10);
Schedule::command('database:backup')->dailyAt('03:30')->withoutOverlapping(60);
