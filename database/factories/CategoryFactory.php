<?php

namespace Database\Factories;

use App\Models\Category;
use App\Models\CategoryTranslation;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Category>
 */
class CategoryFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'slug' => fake()->unique()->slug(2),
            'icon' => 'lightbulb',
            'color' => fake()->hexColor(),
            'sort_order' => 0,
            'is_active' => true,
        ];
    }

    public function inactive(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_active' => false,
        ]);
    }

    /**
     * Give the category a name in every content locale.
     */
    public function configure(): static
    {
        return $this->afterCreating(function (Category $category) {
            foreach (config('quizly.locales') as $locale) {
                CategoryTranslation::factory()->for($category)->create(['locale' => $locale]);
            }
        });
    }
}
