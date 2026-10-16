<?php

namespace App\Actions\Rooms;

use App\Enums\GameStatus;
use App\Events\RoomUpdated;
use App\Models\Game;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Takes a finished room back to its lobby for another game with the same
 * players: scores start again from zero, and the host can change the
 * settings before starting. The finished game's rounds are kept.
 */
class PlayAgain
{
    /**
     * @throws ValidationException
     */
    public function handle(Game $game): void
    {
        DB::transaction(function () use ($game) {
            $game = Game::query()->lockForUpdate()->findOrFail($game->id);

            if ($game->status !== GameStatus::Finished) {
                throw ValidationException::withMessages(['room' => __('The game is not over yet.')]);
            }

            // While the room was finished its code was free to reuse; very
            // rarely, another room may have taken it in the meantime.
            if (Game::query()->active()->where('code', $game->code)->exists()) {
                throw ValidationException::withMessages(['room' => __('This room code is now used by another room. Start a new room instead.')]);
            }

            $game->participants()->update(['score' => 0]);

            $game->forceFill([
                'status' => GameStatus::Lobby,
                'match_number' => $game->match_number + 1,
                'current_round' => 0,
                'started_at' => null,
                'finished_at' => null,
                'next_transition_at' => null,
                'paused_at' => null,
                'paused_remaining_ms' => null,
                'expires_at' => now()->addMinutes(config('quizly.room_lifetime_minutes')),
            ]);
            $game->bumpVersion();
            $game->save();

            RoomUpdated::send($game);
        });
    }
}
