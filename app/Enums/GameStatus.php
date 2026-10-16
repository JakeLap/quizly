<?php

namespace App\Enums;

enum GameStatus: string
{
    case Lobby = 'lobby';
    case Intro = 'intro';
    case Question = 'question';
    case Reveal = 'reveal';
    case Scoreboard = 'scoreboard';
    case Finished = 'finished';
    case Abandoned = 'abandoned';

    /**
     * Whether the room is still in use and holds on to its code.
     */
    public function isActive(): bool
    {
        return ! in_array($this, [self::Finished, self::Abandoned], true);
    }

    /**
     * @return list<string>
     */
    public static function activeValues(): array
    {
        return array_values(array_map(
            fn (self $status) => $status->value,
            array_filter(self::cases(), fn (self $status) => $status->isActive()),
        ));
    }
}
