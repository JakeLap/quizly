<?php

namespace App\Actions\Rooms;

use App\Data\GameSettings;
use App\Enums\GameStatus;
use App\Events\RoomUpdated;
use App\Models\Game;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class UpdateRoomSettings
{
    /**
     * @param  list<int>  $categoryIds
     *
     * @throws ValidationException
     */
    public function handle(Game $game, GameSettings $settings, string $locale, array $categoryIds): void
    {
        DB::transaction(function () use ($game, $settings, $locale, $categoryIds) {
            $game = Game::query()->lockForUpdate()->findOrFail($game->id);

            if ($game->status !== GameStatus::Lobby) {
                throw ValidationException::withMessages(['settings' => __('Settings can only be changed in the lobby.')]);
            }

            $playerCount = $game->participants()->count();

            if ($settings->maxPlayers < $playerCount) {
                throw ValidationException::withMessages([
                    'max_players' => __('There are already :count players in the room.', ['count' => $playerCount]),
                ]);
            }

            $game->settings = $settings;
            $game->locale = $locale;
            $game->bumpVersion();
            $game->save();

            $game->categories()->sync($categoryIds);

            RoomUpdated::send($game);
        });
    }
}
