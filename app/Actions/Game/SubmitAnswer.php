<?php

namespace App\Actions\Game;

use App\Enums\GameStatus;
use App\Enums\RoundStatus;
use App\Events\RoomUpdated;
use App\Game\GameEngine;
use App\Game\ScoreCalculator;
use App\Models\Answer;
use App\Models\Game;
use App\Models\Player;
use Carbon\CarbonImmutable;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class SubmitAnswer
{
    public function __construct(
        private GameEngine $engine,
        private ScoreCalculator $scores,
    ) {}

    /**
     * Record a player's answer to the current round.
     *
     * The response time is measured on the server, from when the answers
     * unlocked to when the request arrived, so a phone's clock can't affect it.
     *
     * Sending the same answer again (e.g. the phone retrying after a network
     * hiccup) returns the answer already recorded rather than an error.
     *
     * @param  int  $slot  The answer button the player tapped (0–3, in display order).
     *
     * @throws ValidationException
     */
    public function handle(Player $player, int $roundNumber, int $slot, CarbonImmutable $receivedAt): Answer
    {
        try {
            $answer = DB::transaction(fn () => $this->record($player, $roundNumber, $slot, $receivedAt));
        } catch (UniqueConstraintViolationException) {
            // The phone sent its answer twice at the same moment.
            $answer = $this->existingAnswer($player, $roundNumber, $slot);
        }

        // Players whose phones went away shouldn't keep everyone else waiting.
        $game = Game::query()->find($player->game_id);

        if ($game !== null) {
            $this->engine->endQuestionIfEveryoneAnswered($game);
        }

        return $answer;
    }

    private function record(Player $player, int $roundNumber, int $slot, CarbonImmutable $receivedAt): Answer
    {
        $game = Game::query()->lockForUpdate()->findOrFail($player->game_id);
        $round = $this->engine->currentRound($game);

        if ($round?->number !== $roundNumber) {
            throw $this->rejected(__('This question is over.'));
        }

        $optionIndex = $round->optionIndexForSlot($slot) ?? throw $this->rejected(__('That is not one of the answers.'));
        $existing = $round->answers->firstWhere('player_id', $player->id);

        if ($existing !== null) {
            return $existing->option_index === $optionIndex
                ? $existing
                : throw $this->rejected(__('You have already answered.'));
        }

        if ($game->status !== GameStatus::Question || $round->status !== RoundStatus::Open) {
            throw $this->rejected(__('This question is over.'));
        }

        // Nobody can see the question while the screen is away.
        if ($game->isPaused()) {
            throw $this->rejected(__('The game is paused until the screen is back.'));
        }

        $opensAtMs = $round->opens_at?->getPreciseTimestamp(3) ?? 0;
        $receivedAtMs = $receivedAt->getPreciseTimestamp(3);

        if ($receivedAtMs < $opensAtMs - config('quizly.timing.early_tolerance')) {
            throw $this->rejected(__('Wait for the answers to unlock.'));
        }

        if ($receivedAtMs > ($round->ends_at?->getPreciseTimestamp(3) ?? 0) + config('quizly.timing.grace')) {
            throw $this->rejected(__("Time's up!"));
        }

        $limitMs = $game->settings->secondsPerRound * 1000;
        $responseMs = (int) min(max($receivedAtMs - $opensAtMs, 0), $limitMs);
        $correct = $optionIndex === $round->question->correct_index;

        $answer = $round->answers()->create([
            'player_id' => $player->id,
            'option_index' => $optionIndex,
            'is_correct' => $correct,
            'response_ms' => $responseMs,
            'points' => $this->scores->points($correct, $responseMs, $limitMs),
            'received_at' => $receivedAt,
        ]);

        // Everyone in the room has answered: no need to ask who is connected.
        if ($round->answers()->count() >= $game->participants()->count()) {
            $this->engine->advance($game);
        } else {
            $game->bumpVersion();
            $game->save();

            RoomUpdated::send($game);
        }

        return $answer;
    }

    /**
     * The answer that won a race between two identical requests.
     */
    private function existingAnswer(Player $player, int $roundNumber, int $slot): Answer
    {
        $round = Game::query()->findOrFail($player->game_id)->rounds()->where('number', $roundNumber)->first();
        $answer = $round?->answers()->where('player_id', $player->id)->first();

        if ($round === null || $answer === null || $answer->option_index !== $round->optionIndexForSlot($slot)) {
            throw $this->rejected(__('You have already answered.'));
        }

        return $answer;
    }

    private function rejected(string $message): ValidationException
    {
        return ValidationException::withMessages(['answer' => $message]);
    }
}
