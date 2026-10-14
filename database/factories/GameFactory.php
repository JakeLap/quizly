<?php

namespace Database\Factories;

use App\Data\GameSettings;
use App\Enums\GameStatus;
use App\Models\Game;
use App\Support\RoomCode;
use App\Support\Token;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Game>
 */
class GameFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'code' => fake()->unique()->regexify('['.RoomCode::ALPHABET.']{'.RoomCode::LENGTH.'}'),
            'host_token_hash' => Token::hash(Token::generate()),
            'locale' => 'el',
            'status' => GameStatus::Lobby,
            'settings' => GameSettings::defaults(),
            'expires_at' => now()->addHours(2),
            'host_seen_at' => now(),
        ];
    }

    /**
     * Use a known host token, e.g. to act as the host in a test.
     */
    public function withHostToken(string $token): static
    {
        return $this->state(fn (array $attributes) => [
            'host_token_hash' => Token::hash($token),
        ]);
    }

    public function status(GameStatus $status): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => $status,
            'started_at' => $status === GameStatus::Lobby ? null : now(),
            'finished_at' => $status->isActive() ? null : now(),
        ]);
    }
}
