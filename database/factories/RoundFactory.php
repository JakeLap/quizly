<?php

namespace Database\Factories;

use App\Enums\RoundStatus;
use App\Models\Game;
use App\Models\Question;
use App\Models\Round;
use DateTimeInterface;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Carbon;

/**
 * @extends Factory<Round>
 */
class RoundFactory extends Factory
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
            'number' => 1,
            'question_id' => Question::factory(),
            'category_id' => fn (array $attributes) => Question::query()->whereKey($attributes['question_id'])->value('category_id'),
            'option_order' => fake()->shuffleArray(range(0, config('quizly.options_per_question') - 1)),
            'status' => RoundStatus::Pending,
        ];
    }

    /**
     * A round whose answers unlocked at the given moment (now by default).
     */
    public function open(?DateTimeInterface $opensAt = null, int $seconds = 20): static
    {
        return $this->state(function (array $attributes) use ($opensAt, $seconds) {
            $opensAt = Carbon::instance($opensAt ?? now());

            return [
                'status' => RoundStatus::Open,
                'shown_at' => $opensAt->copy()->subSeconds(3),
                'opens_at' => $opensAt,
                'ends_at' => $opensAt->copy()->addSeconds($seconds),
            ];
        });
    }
}
