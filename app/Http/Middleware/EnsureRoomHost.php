<?php

namespace App\Http\Middleware;

use App\Auth\Host;
use App\Models\Game;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

/**
 * Only the screen that created a room may run it.
 */
class EnsureRoomHost
{
    public function handle(Request $request, Closure $next): Response
    {
        $room = $request->route('room');
        $host = Auth::guard('host')->user();

        abort_unless($room instanceof Game && $host instanceof Host && $host->game->is($room), 403);

        return $next($request);
    }
}
