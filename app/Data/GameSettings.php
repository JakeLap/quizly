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
 * @implements Arrayable<string, int|list<string>>
 */
final readonly class GameSettings implements Arrayable, Castable, JsonSerializable
{
    /** Every difficulty, easiest first (Difficulty values). */
    public const array ALL_DIFFICULTIES = ['easy', 'medium', 'hard'];

    /**
     * @param  list<string>  $difficulties  Which difficulties of question to ask (Difficulty values).
     */
    public function __construct(
        public int $rounds,
        public int $secondsPerRound,
        public int $maxPlayers,
        public array $difficulties = self::ALL_DIFFICULTIES,
    ) {
        self::assertWithin('rounds', $rounds);
        self::assertWithin('seconds_per_round', $secondsPerRound);
        self::assertWithin('players', $maxPlayers);

        if ($difficulties === [] || array_diff($difficulties, self::ALL_DIFFICULTIES) !== []) {
            throw new InvalidArgumentException('Pick at least one of the difficulties: '.implode(', ', self::ALL_DIFFICULTIES).'.');
        }
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
            // Rooms from before difficulties could be chosen play them all.
            difficulties: self::difficultiesFrom($data['difficulties'] ?? null),
        );
    }

    /**
     * The known difficulties among the given ones, in order, or all of them.
     *
     * @return list<string>
     */
    private static function difficultiesFrom(mixed $value): array
    {
        $chosen = is_array($value) ? $value : [];
        $known = array_values(array_filter(self::ALL_DIFFICULTIES, fn (string $difficulty) => in_array($difficulty, $chosen, true)));

        return $known === [] ? self::ALL_DIFFICULTIES : $known;
    }

    /**
     * @return array{rounds: int, seconds_per_round: int, max_players: int, difficulties: list<string>}
     */
    public function toArray(): array
    {
        return [
            'rounds' => $this->rounds,
            'seconds_per_round' => $this->secondsPerRound,
            'max_players' => $this->maxPlayers,
            'difficulties' => $this->difficulties,
        ];
    }

    /**
     * @return array{rounds: int, seconds_per_round: int, max_players: int, difficulties: list<string>}
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
