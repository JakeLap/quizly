<?php

namespace App\Actions\Rooms;

use App\Game\GameEngine;
use App\Models\Game;
use Illuminate\Support\Facades\DB;

/**
 * The host screen is still there. If the game had paused while it was away,
 * it carries on.
 */
class RecordHostHeartbeat
{
    public function __construct(private GameEngine $engine) {}

    public function handle(Game $game): void
    {
        // Every few seconds per room, so keep the common case to one small update.
        Game::query()->whereKey($game->id)->update(['host_seen_at' => now()]);

        if (! $game->isPaused()) {
            return;
        }

        DB::transaction(function () use ($game) {
            $game = Game::query()->lockForUpdate()->findOrFail($game->id);

            $this->engine->resume($game);
        });
    }
}
