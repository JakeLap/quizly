<?php

namespace Database\Factories;

use App\Models\Answer;
use App\Models\Player;
use App\Models\Round;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Answer>
 */
class AnswerFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'round_id' => Round::factory(),
            'player_id' => Player::factory(),
            'option_index' => 0,
            'is_correct' => false,
            'response_ms' => fake()->numberBetween(300, 15000),
            'points' => 0,
            'received_at' => now(),
        ];
    }
}
