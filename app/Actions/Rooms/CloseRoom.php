<?php

namespace App\Actions\Rooms;

use App\Enums\GameStatus;
use App\Events\RoomUpdated;
use App\Models\Game;
use Illuminate\Support\Facades\DB;

class CloseRoom
{
    /**
     * End the room early; its code becomes free again.
     */
    public function handle(Game $game): void
    {
        DB::transaction(function () use ($game) {
            $game = Game::query()->lockForUpdate()->findOrFail($game->id);

            if (! $game->status->isActive()) {
                return;
            }

            $game->status = GameStatus::Abandoned;
            $game->finished_at = now();
            $game->bumpVersion();
            $game->save();

            RoomUpdated::send($game);
        });
    }
}
