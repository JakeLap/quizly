<?php

namespace App\Support;

use App\Models\Category;

/**
 * What the room settings form needs to offer: the categories, the limits
 * and the languages. Shown on the screen and on the VIP's phone.
 */
final class SettingsOptions
{
    /**
     * @return array{categoryOptions: array<int, array<string, mixed>>, limits: array<string, mixed>, locales: list<string>}
     */
    public static function all(): array
    {
        return [
            'categoryOptions' => self::categories(),
            'limits' => [
                'rounds' => config('quizly.rounds'),
                'secondsPerRound' => config('quizly.seconds_per_round'),
                'players' => config('quizly.players'),
            ],
            'locales' => QuizlyConfig::locales(),
        ];
    }

    /**
     * Every active category, with its names and how many questions it has per language.
     *
     * @return array<int, array<string, mixed>>
     */
    private static function categories(): array
    {
        $locales = QuizlyConfig::locales();

        $counts = collect($locales)->mapWithKeys(fn (string $locale) => [
            "questions as questions_{$locale}" => fn ($query) => $query->playableIn($locale),
        ])->all();

        return Category::query()
            ->active()
            ->ordered()
            ->with('translations')
            ->withCount($counts)
            ->get()
            ->map(fn (Category $category) => [
                'id' => $category->id,
                'slug' => $category->slug,
                'icon' => $category->icon,
                'color' => $category->color,
                'names' => collect($locales)->mapWithKeys(fn (string $locale) => [
                    $locale => $category->translation($locale)->name ?? $category->slug,
                ])->all(),
                'questionCounts' => collect($locales)->mapWithKeys(fn (string $locale) => [
                    $locale => (int) $category->getAttribute("questions_{$locale}"),
                ])->all(),
            ])
            ->values()
            ->all();
    }
}
