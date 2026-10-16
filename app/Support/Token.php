<?php

namespace App\Support;

use Illuminate\Support\Str;

/**
 * Bearer tokens that identify guests (hosts and players) without accounts.
 * Only the SHA-256 hash is stored, so a database leak can't be replayed.
 */
final class Token
{
    public static function generate(): string
    {
        return Str::random(40);
    }

    public static function hash(string $token): string
    {
        return hash('sha256', $token);
    }
}
