<?php

namespace App\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

/**
 * A question's answer options must all be different (ignoring case and
 * surrounding spaces), or players couldn't tell the right one apart.
 */
final class DistinctOptions implements ValidationRule
{
    /**
     * @param  bool  $nameLocale  Say which language's answers clash (e.g. for imports).
     */
    public function __construct(private bool $nameLocale = true) {}

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        $options = array_map(
            fn (mixed $option) => is_string($option) ? mb_strtolower(trim($option)) : $option,
            (array) $value,
        );

        if (count($options) === count(array_unique($options, SORT_REGULAR))) {
            return;
        }

        if (! $this->nameLocale) {
            $fail(__('The answers must all be different.'));

            return;
        }

        // e.g. translations.el.options
        $locale = explode('.', $attribute)[1] ?? '';

        $fail(__('The :locale options must all be different.', ['locale' => $locale]));
    }
}
