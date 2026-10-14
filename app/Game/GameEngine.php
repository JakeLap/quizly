<?php

namespace App\Game;

use App\Enums\GameStatus;
use App\Enums\RoundStatus;
use App\Events\RoomUpdated;
use App\Models\Answer;
use App\Models\Game;
use App\Models\Round;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\Database\LostConnectionDetector;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * The game's state machine. The server alone decides when a game moves on:
 *
 *     Intro → Question → Reveal → Scoreboard → Question → … → Reveal → Finished
 *
 * Each phase stores when it ends in `next_transition_at`; the game clock
 * (`quizly:clock`) calls advanceDue() to move games whose time is up. A round
 * also ends early once every player has answered, and the host can skip ahead.
 *
 * The questions are only shown on the host screen, so a game pauses when the
 * screen stops sending heartbeats, and resumes where it left off when the
 * screen is back. A paused game's `next_transition_at` is its deadline: if the
 * screen hasn't returned by then, the room is closed.
 *
 * Methods that take a Game expect it to be locked (selected FOR UPDATE) inside
 * the caller's transaction, so phases never change concurrently.
 */
class GameEngine
{
    /** How often a game may fail to advance before it is abandoned. */
    public const int MAX_FAILURES = 3;

    /** How long to wait before retrying a game that failed to advance. */
    public const int RETRY_SECONDS = 5;

    public function __construct(
        private QuestionPicker $questions,
        private LostConnectionDetector $lostConnection,
        private RoomPresence $presence,
    ) {}

    /**
     * Leave the lobby and show the intro.
     */
    public function start(Game $game): void
    {
        $game->status = GameStatus::Intro;
        $game->started_at = now();
        $game->expires_at = now()->addMinutes(config('quizly.room_lifetime_minutes'));
        $this->transitioned($game, $this->after('intro'));
    }

    /**
     * Move the game to its next phase.
     */
    public function advance(Game $game): void
    {
        // The host screen didn't come back in time.
        if ($game->isPaused()) {
            $this->close($game);

            return;
        }

        match ($game->status) {
            GameStatus::Intro, GameStatus::Scoreboard => $this->openNextRound($game),
            GameStatus::Question => $this->revealRound($game),
            GameStatus::Reveal => $game->current_round >= $game->settings->rounds
                ? $this->finish($game)
                : $this->showScoreboard($game),
            default => null,
        };
    }

    /**
     * The host skipping the rest of the current phase.
     */
    public function skip(Game $game): void
    {
        DB::transaction(function () use ($game) {
            $game = Game::query()->lockForUpdate()->findOrFail($game->id);

            if (! $game->isPaused() && in_array($game->status, [GameStatus::Intro, GameStatus::Question, GameStatus::Reveal, GameStatus::Scoreboard], true)) {
                $this->advance($game);
            }
        });
    }

    /**
     * End the current question early if every player who is still connected
     * has answered, instead of waiting out the timer for players whose phones
     * went away. Nothing happens if nobody has answered yet.
     *
     * Asks Reverb who is connected before locking the game, so the lock is
     * never held while waiting on the network.
     */
    public function endQuestionIfEveryoneAnswered(Game $game): void
    {
        if ($game->status !== GameStatus::Question) {
            return;
        }

        $connected = $this->presence->connectedPlayerIds($game);

        DB::transaction(function () use ($game, $connected) {
            $game = Game::query()->lockForUpdate()->findOrFail($game->id);
            $round = $this->currentRound($game);

            if ($game->status !== GameStatus::Question || $game->isPaused() || $round?->status !== RoundStatus::Open) {
                return;
            }

            $waitingFor = $game->participants()->whereNotIn('id', $round->answers->pluck('player_id'));

            // Without presence information, wait for everyone in the room.
            if ($connected !== null) {
                $waitingFor->whereIn('id', $connected);
            }

            if ($round->answers->isNotEmpty() && ! $waitingFor->exists()) {
                $this->advance($game);
            }
        });
    }

    /**
     * Freeze the game where it is: the host screen has gone away.
     */
    public function pause(Game $game): void
    {
        if ($game->isPaused() || ! $game->status->isActive()) {
            return;
        }

        $game->paused_remaining_ms = $game->next_transition_at
            ? (int) max(0, $game->next_transition_at->getPreciseTimestamp(3) - now()->getPreciseTimestamp(3))
            : null;
        $game->paused_at = now();

        $this->transitioned($game, now()->addSeconds((int) config('quizly.host.return_seconds')));
    }

    /**
     * Carry on where the game paused, with the time that was left. An open
     * question's times move forward by the length of the pause, so players get
     * their full time and response times don't include the pause.
     */
    public function resume(Game $game): void
    {
        if (! $game->isPaused() || ! $game->status->isActive()) {
            return;
        }

        $pausedForMs = (int) max(0, now()->getPreciseTimestamp(3) - ($game->paused_at?->getPreciseTimestamp(3) ?? 0));
        $round = $this->currentRound($game);

        if ($game->status === GameStatus::Question && $round?->status === RoundStatus::Open) {
            $round->update([
                'shown_at' => $round->shown_at?->addMilliseconds($pausedForMs),
                'opens_at' => $round->opens_at?->addMilliseconds($pausedForMs),
                'ends_at' => $round->ends_at?->addMilliseconds($pausedForMs),
            ]);
        }

        $nextAt = $game->paused_remaining_ms !== null ? now()->addMilliseconds($game->paused_remaining_ms) : null;

        $game->paused_at = null;
        $game->paused_remaining_ms = null;
        $this->transitioned($game, $nextAt);
    }

    /**
     * Pause the games whose host screen has stopped sending heartbeats.
     *
     * @return int How many games were paused.
     */
    public function pauseGamesWithoutHost(): int
    {
        $silentSince = $this->dbNow(now()->subSeconds((int) config('quizly.host.timeout_seconds')));

        $silent = Game::query()
            ->active()
            ->whereNull('paused_at')
            ->where('host_seen_at', '<=', $silentSince)
            ->limit(100)
            ->pluck('id');

        $paused = 0;

        foreach ($silent as $id) {
            $paused += DB::transaction(function () use ($id) {
                $game = Game::query()->whereKey($id)->lock('for update skip locked')->first();

                // A heartbeat may have arrived in the meantime.
                if ($game === null || $game->isPaused() || $game->hostIsPresent()) {
                    return 0;
                }

                $this->pause($game);

                return 1;
            });
        }

        return $paused;
    }

    /**
     * Advance every game whose current phase has run out.
     *
     * Safe to run from several processes at once: each game is only ever
     * advanced by whichever process locks it first.
     *
     * @return int How many games were advanced.
     *
     * @throws Throwable When the database can't be reached.
     */
    public function advanceDue(): int
    {
        $this->pauseGamesWithoutHost();

        $due = Game::query()
            ->active()
            ->where('next_transition_at', '<=', $this->dbNow())
            ->orderBy('next_transition_at')
            ->limit(100)
            ->pluck('id');

        $advanced = 0;

        foreach ($due as $id) {
            try {
                $advanced += DB::transaction(function () use ($id) {
                    // SKIP LOCKED: if another clock process (or a request, like the
                    // last answer of a round) holds this game, skip it instead of
                    // waiting. Parallel clocks then share the due games between
                    // them, and a skipped game is simply picked up on a later tick.
                    $game = Game::query()->whereKey($id)->lock('for update skip locked')->first();

                    // Already moved on by whoever held it.
                    if (! $game?->next_transition_at || $game->next_transition_at->isFuture()) {
                        return 0;
                    }

                    $this->advance($game);

                    return 1;
                });

                Cache::forget($this->failuresKey($id));
            } catch (Throwable $e) {
                // The database being unreachable isn't this game's fault; let the
                // clock back off and retry rather than count it against the game.
                if ($this->lostConnection->causedByLostConnection($e)) {
                    throw $e;
                }

                $this->failed($id, $e);
            }
        }

        return $advanced;
    }

    /**
     * A game failed to advance: retry it a little later, or give up on it
     * after a few attempts so its screens show the room as closed instead
     * of freezing on the current phase.
     */
    private function failed(int $id, Throwable $e): void
    {
        $failures = (int) Cache::get($this->failuresKey($id), 0) + 1;
        Cache::put($this->failuresKey($id), $failures, now()->addHour());

        if ($failures >= self::MAX_FAILURES) {
            Log::error("Game {$id} failed to advance {$failures} times and was abandoned.", ['exception' => $e]);

            Cache::forget($this->failuresKey($id));
            $this->abandon($id);

            return;
        }

        Log::warning("Game {$id} failed to advance (attempt {$failures}), retrying.", ['exception' => $e]);

        Game::query()->whereKey($id)->update(['next_transition_at' => $this->dbNow(now()->addSeconds(self::RETRY_SECONDS))]);
    }

    private function abandon(int $id): void
    {
        DB::transaction(function () use ($id) {
            $game = Game::query()->lockForUpdate()->whereKey($id)->first();

            if ($game !== null && $game->status->isActive()) {
                $this->close($game);
            }
        });
    }

    private function close(Game $game): void
    {
        $game->status = GameStatus::Abandoned;
        $game->finished_at = now();
        $game->paused_at = null;
        $game->paused_remaining_ms = null;
        $this->transitioned($game, null);
    }

    private function failuresKey(int $id): string
    {
        return "quizly:game:{$id}:advance-failures";
    }

    /**
     * The current round, with what the room state needs to show it.
     */
    public function currentRound(Game $game): ?Round
    {
        if ($game->current_round === 0) {
            return null;
        }

        return $game->rounds()
            ->where('number', $game->current_round)
            ->with(['question.translations', 'category.translations', 'answers'])
            ->first();
    }

    private function openNextRound(Game $game): void
    {
        $question = $this->questions->pick($game);

        // Questions can be unpublished mid-game; end gracefully if we run out.
        if ($question === null || $game->current_round >= $game->settings->rounds) {
            $this->finish($game);

            return;
        }

        $shownAt = now();
        $opensAt = $shownAt->addMilliseconds((int) config('quizly.timing.reading'));
        $endsAt = $opensAt->addSeconds($game->settings->secondsPerRound);

        $game->current_round++;
        $game->rounds()->create([
            'number' => $game->current_round,
            'question_id' => $question->id,
            'category_id' => $question->category_id,
            'option_order' => collect(range(0, config('quizly.options_per_question') - 1))->shuffle()->all(),
            'status' => RoundStatus::Open,
            'shown_at' => $shownAt,
            'opens_at' => $opensAt,
            'ends_at' => $endsAt,
        ]);

        $game->status = GameStatus::Question;
        $this->transitioned($game, $endsAt->addMilliseconds((int) config('quizly.timing.grace')));
    }

    private function revealRound(Game $game): void
    {
        $round = $this->currentRound($game);

        if ($round !== null) {
            $round->update(['status' => RoundStatus::Revealed, 'closed_at' => now()]);

            // Points were worked out when each answer arrived but only count now,
            // so nobody can see who was right before the reveal.
            $round->answers
                ->filter(fn (Answer $answer) => $answer->points > 0)
                ->each(fn (Answer $answer) => $answer->player()->increment('score', $answer->points));

            $round->question->increment('times_asked', $round->answers->count());
            $round->question->increment('times_correct', $round->answers->where('is_correct', true)->count());
        }

        $game->status = GameStatus::Reveal;
        $this->transitioned($game, $this->after('reveal'));
    }

    private function showScoreboard(Game $game): void
    {
        $game->status = GameStatus::Scoreboard;
        $this->transitioned($game, $this->after('scoreboard'));
    }

    private function finish(Game $game): void
    {
        $game->status = GameStatus::Finished;
        $game->finished_at = now();
        $this->transitioned($game, null);
    }

    private function transitioned(Game $game, ?CarbonImmutable $nextAt): void
    {
        $game->next_transition_at = $nextAt;
        $game->bumpVersion();
        $game->save();

        RoomUpdated::send($game);
    }

    private function after(string $phase): CarbonImmutable
    {
        return now()->addMilliseconds((int) config("quizly.timing.{$phase}"));
    }

    /**
     * Query bindings drop milliseconds by default, which would make phases
     * end up to a second late.
     */
    private function dbNow(?CarbonImmutable $at = null): string
    {
        return ($at ?? now())->format('Y-m-d H:i:s.v');
    }
}
