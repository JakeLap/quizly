<?php

namespace App\Support;

use Illuminate\Support\Facades\Config;

/**
 * Typed access to the list settings in config/quizly.php.
 */
final class QuizlyConfig
{
    /**
     * The languages content can be written and played in.
     *
     * @return list<string>
     */
    public static function locales(): array
    {
        return self::strings('quizly.locales');
    }

    /**
     * @return list<string>
     */
    public static function playerColors(): array
    {
        return self::strings('quizly.player_colors');
    }

    /**
     * @return list<string>
     */
    private static function strings(string $key): array
    {
        return array_values(array_filter(Config::array($key), is_string(...)));
    }
}
