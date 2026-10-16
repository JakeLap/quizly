<?php

use App\Models\Game;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Housekeeping. Games themselves are timed by the game clock (quizly:clock),
// not by the scheduler, which only runs to the minute.
Schedule::command('quizly:expire-rooms')->everyMinute()->withoutOverlapping();
Schedule::command('model:prune', ['--model' => [Game::class]])->daily()->withoutOverlapping();
