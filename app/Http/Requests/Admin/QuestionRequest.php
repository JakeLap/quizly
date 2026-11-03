<?php

namespace App\Http\Requests\Admin;

use App\Enums\Difficulty;
use App\Enums\QuestionStatus;
use App\Rules\DistinctOptions;
use App\Support\QuizlyConfig;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * A question as edited in the admin: the shared details, and the wording in
 * each language. A language left completely empty isn't translated.
 */
class QuestionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('manage-questions') ?? false;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $optionCount = config('quizly.options_per_question');

        return [
            'category_id' => ['required', 'integer', Rule::exists('categories', 'id')],
            // Questions of the same type are spread out in a game (see QuestionPicker).
            'kind' => ['nullable', 'string', 'max:64'],
            'difficulty' => ['required', Rule::enum(Difficulty::class)],
            'status' => ['required', Rule::enum(QuestionStatus::class)],
            'correct_index' => ['required', 'integer', 'between:0,'.($optionCount - 1)],
            'translations' => ['required', 'array', 'min:1', 'array:'.implode(',', QuizlyConfig::locales())],
            'translations.*' => ['array:text,options,explanation'],
            'translations.*.text' => ['required', 'string', 'max:500'],
            'translations.*.options' => ['required', 'array', "size:{$optionCount}", new DistinctOptions(nameLocale: false)],
            'translations.*.options.*' => ['required', 'string', 'max:120'],
            'translations.*.explanation' => ['nullable', 'string', 'max:1000'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'translations.required' => __('Write the question in at least one language.'),
            'translations.min' => __('Write the question in at least one language.'),
            'translations.*.text.required' => __('Write the question.'),
            'translations.*.options.*.required' => __('Fill in every answer.'),
        ];
    }

    /**
     * Leave out languages with nothing filled in, and tidy up spaces.
     */
    protected function prepareForValidation(): void
    {
        $translations = [];

        foreach ((array) $this->input('translations', []) as $locale => $translation) {
            $translation = (array) $translation;
            $text = trim((string) ($translation['text'] ?? ''));
            $options = array_map(fn (mixed $option) => trim((string) $option), array_values((array) ($translation['options'] ?? [])));
            $explanation = trim((string) ($translation['explanation'] ?? ''));

            if ($text === '' && implode('', $options) === '' && $explanation === '') {
                continue;
            }

            $translations[$locale] = [
                'text' => $text,
                'options' => $options,
                'explanation' => $explanation === '' ? null : $explanation,
            ];
        }

        // One spelling per type: "Flags " and "flags" are the same.
        $kind = trim(mb_strtolower((string) $this->input('kind', '')));

        $this->merge([
            'translations' => $translations,
            'kind' => $kind === '' ? null : preg_replace('/\s+/u', '-', $kind),
        ]);
    }

    /**
     * The wording per language, as validated.
     *
     * @return array<string, array{text: string, options: list<string>, explanation: string|null}>
     */
    public function translations(): array
    {
        return $this->validated('translations');
    }
}
