<?php

namespace App\Actions\Rooms;

use App\Enums\GameStatus;
use App\Game\RoomPresence;
use App\Models\Game;
use App\Models\Player;

/**
 * Removes lobby players whose phones have gone, e.g. who closed the tab.
 *
 * Closing a tab isn't leaving: the player keeps their place for a short
 * grace period, so a refresh or an accidentally closed tab doesn't cost them
 * their nickname or VIP status. After that they're removed, freeing their
 * slot and handing VIP on. Once the game has started, players are kept
 * (with their score) however long they are gone.
 */
class RemoveDisconnectedPlayers
{
    public function __construct(
        private RoomPresence $presence,
        private RemovePlayer $removePlayer,
    ) {}

    /**
     * @param  list<int>  $playerIds  Players the host screen has seen gone for the grace period.
     *                                Only those Reverb also reports as gone are removed, so
     *                                someone who is just refreshing the page stays.
     * @return int How many players were removed.
     */
    public function handle(Game $game, array $playerIds): int
    {
        if ($playerIds === []) {
            return 0;
        }

        if ($game->status !== GameStatus::Lobby) {
            return 0;
        }

        $connected = $this->presence->connectedPlayerIds($game);

        // Without knowing who is connected, don't remove anyone.
        if ($connected === null) {
            return 0;
        }

        // Players who only just joined may not have connected yet.
        $gone = $game->participants()
            ->whereIn('id', $playerIds)
            ->whereNotIn('id', $connected)
            ->where('created_at', '<=', now()->subSeconds(config('quizly.lobby_disconnect_grace_seconds')))
            ->get();

        $gone->each(fn (Player $player) => $this->removePlayer->handle($player));

        return $gone->count();
    }
}
