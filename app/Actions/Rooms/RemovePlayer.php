<?php

namespace App\Actions\Rooms;

use App\Enums\GameStatus;
use App\Events\RoomUpdated;
use App\Models\Game;
use App\Models\Player;
use Illuminate\Support\Facades\DB;

/**
 * Removes a player who left or was kicked by the host.
 */
class RemovePlayer
{
    public function handle(Player $player, bool $kicked = false): void
    {
        DB::transaction(function () use ($player, $kicked) {
            $game = Game::query()->lockForUpdate()->findOrFail($player->game_id);

            // In the lobby the player simply disappears. Once the game has
            // started they're only marked, so their answers keep counting
            // for the round statistics.
            if ($game->status === GameStatus::Lobby && ! $kicked) {
                $player->delete();
            } else {
                $player->forceFill(['kicked_at' => now(), 'is_vip' => false])->save();
            }

            $this->handOverVip($game);

            $game->bumpVersion();
            $game->save();

            RoomUpdated::send($game);
        });
    }

    /**
     * Make the longest-waiting player the VIP if the room has none.
     */
    private function handOverVip(Game $game): void
    {
        if ($game->participants()->where('is_vip', true)->exists()) {
            return;
        }

        $game->participants()->first()?->forceFill(['is_vip' => true])->save();
    }
}
