<?php

namespace App\Http\Middleware;

use App\Models\Game;
use App\Support\QuizlyConfig;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Inside a room everyone sees the room's language (the questions exist in
 * that language only). Elsewhere it's the language picked with the language
 * switcher, or else the browser's preferred language if we support it.
 */
class SetLocale
{
    /** The cookie that remembers the language picked with the switcher. */
    public const string COOKIE = 'quizly_locale';

    public function handle(Request $request, Closure $next): Response
    {
        app()->setLocale(self::localeFor($request));

        return $next($request);
    }

    /**
     * The language for this request (also used for error pages, which can
     * happen before this middleware runs).
     */
    public static function localeFor(Request $request): string
    {
        $room = $request->route('room');
        $picked = $request->cookie(self::COOKIE);

        return match (true) {
            $room instanceof Game => $room->locale,
            in_array($picked, QuizlyConfig::locales(), true) => $picked,
            default => $request->getPreferredLanguage(QuizlyConfig::locales()) ?? config('quizly.default_locale'),
        };
    }
}
