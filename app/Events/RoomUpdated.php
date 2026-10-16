<?php

namespace App\Events;

use App\Models\Game;
use App\Support\RoomState;
use Illuminate\Broadcasting\PresenceChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Support\Facades\DB;

/**
 * Sends the room's full public state to everyone in it.
 *
 * Broadcast immediately (not queued) to keep latency low. Use send() rather
 * than dispatching it directly.
 */
class RoomUpdated implements ShouldBroadcastNow
{
    public function __construct(public Game $game) {}

    /**
     * Broadcast the room's state once the current transaction commits, so
     * clients never see state that was rolled back.
     *
     * A broadcasting failure (e.g. Reverb is down) is reported but never
     * fails the game or the request: the change is already saved, and
     * clients catch up by reloading the room state.
     */
    public static function send(Game $game): void
    {
        DB::afterCommit(fn () => rescue(fn () => event(new self($game))));
    }

    public function broadcastOn(): PresenceChannel
    {
        return new PresenceChannel("room.{$this->game->code}");
    }

    public function broadcastAs(): string
    {
        return 'room.updated';
    }

    /**
     * @return array<string, mixed>
     */
    public function broadcastWith(): array
    {
        return RoomState::of($this->game->fresh() ?? $this->game);
    }
}
