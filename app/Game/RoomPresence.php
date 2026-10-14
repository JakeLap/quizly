<?php

namespace App\Game;

use App\Models\Game;
use Illuminate\Broadcasting\Broadcasters\PusherBroadcaster;
use Illuminate\Support\Facades\Broadcast;
use Throwable;

/**
 * Who is connected to a room right now, according to Reverb.
 *
 * Reverb drops a connection as soon as a tab closes, but a phone that
 * silently loses its connection can take up to a couple of minutes to be
 * dropped, so treat this as a hint for ending rounds early, never as a
 * reason to end them late.
 */
class RoomPresence
{
    /**
     * The ids of the room's players currently connected to its presence
     * channel, or null when that can't be found out (e.g. Reverb is down).
     *
     * @return list<int>|null
     */
    public function connectedPlayerIds(Game $game): ?array
    {
        $broadcaster = Broadcast::driver();

        if (! $broadcaster instanceof PusherBroadcaster) {
            return null;
        }

        try {
            $response = $broadcaster->getPusher()->getPresenceUsers("presence-room.{$game->code}");
        } catch (Throwable $e) {
            report($e);

            return null;
        }

        $ids = [];

        foreach ((array) ($response->users ?? []) as $user) {
            if (is_object($user) && is_string($user->id ?? null) && preg_match('/^player:(\d+)$/', $user->id, $match)) {
                $ids[] = (int) $match[1];
            }
        }

        return $ids;
    }
}
