<?php

namespace Database\Factories;

use App\Enums\Difficulty;
use App\Enums\QuestionSource;
use App\Enums\QuestionStatus;
use App\Models\Category;
use App\Models\Question;
use App\Models\QuestionTranslation;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Question>
 */
class QuestionFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'category_id' => Category::factory(),
            'difficulty' => fake()->randomElement(Difficulty::cases()),
            'correct_index' => fake()->numberBetween(0, config('quizly.options_per_question') - 1),
            'status' => QuestionStatus::Published,
            'source' => QuestionSource::Manual,
            'source_ref' => null,
            'license' => null,
        ];
    }

    public function draft(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => QuestionStatus::Draft,
        ]);
    }

    /**
     * Only keep translations for the given locales (by default it has all content locales).
     *
     * @param  list<string>  $locales
     */
    public function translatedInto(array $locales): static
    {
        return $this->afterCreating(function (Question $question) use ($locales) {
            $question->translations()->whereNotIn('locale', $locales)->delete();
            $question->unsetRelation('translations');
        });
    }

    public function configure(): static
    {
        return $this->afterCreating(function (Question $question) {
            foreach (config('quizly.locales') as $locale) {
                QuestionTranslation::factory()->for($question)->create(['locale' => $locale]);
            }
        });
    }
}
