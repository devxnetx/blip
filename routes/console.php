<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Schedule::command('pins:import-sporten')
    ->dailyAt('04:30')
    ->timezone('Europe/Sofia')
    ->withoutOverlapping(180)
    ->appendOutputTo(storage_path('logs/sporten-import.log'));

Schedule::command('pins:import-eventibg')
    ->dailyAt('05:10')
    ->timezone('Europe/Sofia')
    ->withoutOverlapping(180)
    ->appendOutputTo(storage_path('logs/eventibg-import.log'));

Schedule::command('pins:import-kupibileti')
    ->dailyAt('06:00')
    ->timezone('Europe/Sofia')
    ->withoutOverlapping(180)
    ->appendOutputTo(storage_path('logs/kupibileti-import.log'));

Schedule::command('pins:promote-started')
    ->everyMinute()
    ->withoutOverlapping();
