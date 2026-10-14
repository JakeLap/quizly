<?php

namespace App\Http\Middleware;

use App\Models\Game;
use App\Support\QuizlyConfig;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Inside a room everyone sees the room's language; elsewhere the browser's
 * preferred language is used if it's one we support.
 */
class SetLocale
{
    public function handle(Request $request, Closure $next): Response
    {
        $room = $request->route('room');

        app()->setLocale(match (true) {
            $room instanceof Game => $room->locale,
            default => $request->getPreferredLanguage(QuizlyConfig::locales()) ?? config('quizly.default_locale'),
        });

        return $next($request);
    }
}
