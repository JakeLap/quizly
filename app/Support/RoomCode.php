<?php

namespace App\Support;

/**
 * Short codes players type on their phones to join a room.
 */
final class RoomCode
{
    /**
     * Uppercase letters without I and O, which are easily mistaken for 1 and 0.
     */
    public const string ALPHABET = 'ABCDEFGHJKLMNPQRSTUVWXYZ';

    public const int LENGTH = 4;

    public static function generate(): string
    {
        $code = '';

        for ($i = 0; $i < self::LENGTH; $i++) {
            $code .= self::ALPHABET[random_int(0, strlen(self::ALPHABET) - 1)];
        }

        return $code;
    }

    /**
     * Normalize user input ("ab cd" → "ABCD"); null if it can't be a valid code.
     */
    public static function normalize(?string $input): ?string
    {
        $code = strtoupper(preg_replace('/\s+/', '', (string) $input) ?? '');

        return preg_match('/^['.self::ALPHABET.']{'.self::LENGTH.'}$/', $code) === 1 ? $code : null;
    }
}
