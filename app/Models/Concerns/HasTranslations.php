<?php

namespace App\Models\Concerns;

use Illuminate\Database\Eloquent\Model;

/**
 * For models with a `translations` has-many relation keyed by `locale`.
 *
 * @template TTranslation of \App\Models\CategoryTranslation|\App\Models\QuestionTranslation
 */
trait HasTranslations
{
    /**
     * The translation for the given locale, falling back to the app's fallback locale.
     *
     * @return TTranslation|null
     */
    public function translation(?string $locale = null): ?Model
    {
        $locale ??= app()->getLocale();

        return $this->translations->firstWhere('locale', $locale)
            ?? $this->translations->firstWhere('locale', config('app.fallback_locale'));
    }

    /**
     * @return list<string>
     */
    public function translatedLocales(): array
    {
        return array_values($this->translations->map(fn ($translation) => $translation->locale)->all());
    }
}
