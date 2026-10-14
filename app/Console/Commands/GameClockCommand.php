<?php

namespace App\Console\Commands;

use App\Game\GameEngine;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Throwable;

#[Signature('quizly:clock {--interval=100 : Milliseconds between checks} {--once : Check once and exit}')]
#[Description('Move games on when their current phase (intro, question, reveal, scoreboard) runs out')]
class GameClockCommand extends Command
{
    /** The longest pause between attempts while something keeps failing. */
    public const int MAX_BACKOFF_MS = 5000;

    private bool $running = true;

    /**
     * Execute the console command.
     */
    public function handle(GameEngine $engine): int
    {
        $interval = max(10, (int) $this->option('interval'));

        if ($this->option('once')) {
            return $this->tick($engine) ? self::SUCCESS : self::FAILURE;
        }

        $this->trap([SIGINT, SIGTERM], function (): void {
            $this->running = false;
        });
        $this->components->info("Game clock running, checking every {$interval} ms.");

        $failures = 0;

        // The clock must outlive any error: if it stopped, every game would freeze.
        while ($this->running) {
            $failures = $this->tick($engine) ? 0 : $failures + 1;

            usleep($this->delay($interval, $failures) * 1000);
        }

        return self::SUCCESS;
    }

    /**
     * Wait the normal interval, or back off (doubling up to a few seconds)
     * while checks keep failing, e.g. because the database is down.
     */
    public function delay(int $interval, int $failures): int
    {
        if ($failures === 0) {
            return $interval;
        }

        return min(self::MAX_BACKOFF_MS, $interval * 2 ** min($failures, 10));
    }

    /**
     * @return bool Whether the check succeeded.
     */
    private function tick(GameEngine $engine): bool
    {
        try {
            $engine->advanceDue();

            // A long-running process shouldn't collect every query it ran.
            DB::flushQueryLog();

            return true;
        } catch (Throwable $e) {
            report($e);

            // Start from a fresh connection next time; Laravel connects again on demand.
            DB::purge();

            return false;
        }
    }
}
