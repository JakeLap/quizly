<?php

namespace Database\Factories;

use App\Models\Game;
use App\Models\Player;
use App\Support\Token;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Player>
 */
class PlayerFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'game_id' => Game::factory(),
            'token_hash' => Token::hash(Token::generate()),
            'nickname' => fake()->unique()->firstName(),
            'color' => fake()->hexColor(),
            'is_vip' => false,
        ];
    }

    /**
     * Use a known player token, e.g. to act as the player in a test.
     */
    public function withToken(string $token): static
    {
        return $this->state(fn (array $attributes) => [
            'token_hash' => Token::hash($token),
        ]);
    }

    public function vip(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_vip' => true,
        ]);
    }
}
