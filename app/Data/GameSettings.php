<?php

namespace App\Data;

use App\Casts\AsGameSettings;
use Illuminate\Contracts\Database\Eloquent\Castable;
use Illuminate\Contracts\Support\Arrayable;
use InvalidArgumentException;
use JsonSerializable;

/**
 * The options a host picks for a room. Categories are stored separately,
 * on the game_category pivot.
 *
 * @implements Arrayable<string, int>
 */
final readonly class GameSettings implements Arrayable, Castable, JsonSerializable
{
    public function __construct(
        public int $rounds,
        public int $secondsPerRound,
        public int $maxPlayers,
    ) {
        self::assertWithin('rounds', $rounds);
        self::assertWithin('seconds_per_round', $secondsPerRound);
        self::assertWithin('players', $maxPlayers);
    }

    public static function defaults(): self
    {
        return new self(
            rounds: config('quizly.rounds.default'),
            secondsPerRound: config('quizly.seconds_per_round.default'),
            maxPlayers: config('quizly.players.default'),
        );
    }

    /**
     * Build settings from stored or submitted data, using defaults for missing keys.
     *
     * @param  array<string, mixed>  $data
     */
    public static function fromArray(array $data): self
    {
        $defaults = self::defaults();

        return new self(
            rounds: (int) ($data['rounds'] ?? $defaults->rounds),
            secondsPerRound: (int) ($data['seconds_per_round'] ?? $defaults->secondsPerRound),
            maxPlayers: (int) ($data['max_players'] ?? $defaults->maxPlayers),
        );
    }

    /**
     * @return array{rounds: int, seconds_per_round: int, max_players: int}
     */
    public function toArray(): array
    {
        return [
            'rounds' => $this->rounds,
            'seconds_per_round' => $this->secondsPerRound,
            'max_players' => $this->maxPlayers,
        ];
    }

    /**
     * @return array{rounds: int, seconds_per_round: int, max_players: int}
     */
    public function jsonSerialize(): array
    {
        return $this->toArray();
    }

    /**
     * @param  array<int, mixed>  $arguments
     */
    public static function castUsing(array $arguments): AsGameSettings
    {
        return new AsGameSettings;
    }

    private static function assertWithin(string $key, int $value): void
    {
        $min = config("quizly.{$key}.min");
        $max = config("quizly.{$key}.max");

        if ($value < $min || $value > $max) {
            throw new InvalidArgumentException("The {$key} setting must be between {$min} and {$max}, {$value} given.");
        }
    }
}
