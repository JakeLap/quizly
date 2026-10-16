<?php

namespace Database\Factories;

use App\Models\Question;
use App\Models\QuestionTranslation;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<QuestionTranslation>
 */
class QuestionTranslationFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'question_id' => Question::factory(),
            'locale' => 'en',
            'text' => rtrim(fake()->sentence(), '.').'?',
            'options' => array_map(
                fn () => ucfirst(fake()->unique()->word()),
                range(1, config('quizly.options_per_question')),
            ),
            'explanation' => null,
        ];
    }
}
