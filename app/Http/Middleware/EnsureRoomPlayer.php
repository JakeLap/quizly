<?php

namespace App\Http\Middleware;

use App\Models\Game;
use App\Models\Player;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

/**
 * Only players taking part in the room may act in it.
 */
class EnsureRoomPlayer
{
    public function handle(Request $request, Closure $next): Response
    {
        $room = $request->route('room');
        $player = Auth::guard('player')->user();

        abort_unless(
            $room instanceof Game && $player instanceof Player && $player->game_id === $room->id && $player->isParticipating(),
            403,
        );

        return $next($request);
    }
}
