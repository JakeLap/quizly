<?php

namespace App\Support;

use Illuminate\Support\Facades\Cookie as CookieFactory;
use Symfony\Component\HttpFoundation\Cookie;

/**
 * The cookies that identify hosts and players.
 *
 * Each cookie is scoped to its role's URL prefix (/host or /play), so one
 * browser can be the host in one tab and a player in another without the
 * identities getting mixed up, e.g. while testing.
 */
final class GuestCookies
{
    public const string HOST = 'quizly_host';

    public const string PLAYER = 'quizly_player';

    public static function host(string $token): Cookie
    {
        return CookieFactory::make(self::HOST, $token, self::minutes(), '/host');
    }

    public static function player(string $token): Cookie
    {
        return CookieFactory::make(self::PLAYER, $token, self::minutes(), '/play');
    }

    public static function forgetPlayer(): Cookie
    {
        return CookieFactory::forget(self::PLAYER, '/play');
    }

    private static function minutes(): int
    {
        return config('quizly.room_lifetime_minutes');
    }
}
