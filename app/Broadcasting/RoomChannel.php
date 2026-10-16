<?php

namespace App\Broadcasting;

use App\Auth\Host;
use App\Enums\GameStatus;
use App\Models\Game;
use App\Models\Player;

/**
 * presence-room.{code}: the host screen and the room's players.
 *
 * The host joins through /host/broadcasting/auth and players through
 * /play/broadcasting/auth, each authenticated by their own cookie.
 */
class RoomChannel
{
    /**
     * @return array{id: string, role: string, playerId?: int}|false
     */
    public function join(Host|Player $user, string $code): array|false
    {
        if ($user instanceof Host) {
            return $user->game->code === $code && $this->isOpen($user->game)
                ? ['id' => $user->getAuthIdentifierForBroadcasting(), 'role' => 'host']
                : false;
        }

        $game = $user->game;

        if ($game->code !== $code || ! $this->isOpen($game) || ! $user->isParticipating()) {
            return false;
        }

        return ['id' => $user->getAuthIdentifierForBroadcasting(), 'role' => 'player', 'playerId' => $user->id];
    }

    /**
     * Rooms in use, and finished ones whose results are still on screen.
     */
    private function isOpen(Game $game): bool
    {
        return $game->status->isActive() || $game->status === GameStatus::Finished;
    }
}
