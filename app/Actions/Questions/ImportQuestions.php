<?php

namespace App\Actions\Questions;

use App\Data\ImportResult;
use App\Enums\Difficulty;
use App\Enums\QuestionSource;
use App\Enums\QuestionStatus;
use App\Models\Category;
use App\Models\Question;
use App\Support\QuizlyConfig;
use Closure;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

/**
 * Imports questions from plain arrays, e.g. a decoded JSON file:
 *
 *     {
 *       "ref": "seed-geography-001",
 *       "category": "geography",
 *       "difficulty": "easy",
 *       "correct": 0,
 *       "source": "manual",
 *       "license": null,
 *       "translations": {
 *         "el": { "text": "…;", "options": ["…", "…", "…", "…"], "explanation": null },
 *         "en": { "text": "…?", "options": ["…", "…", "…", "…"] }
 *       }
 *     }
 *
 * Items with a `ref` are matched on (source, ref), so importing the same file
 * again updates questions instead of duplicating them. The status is only set
 * for new questions, so a re-import never republishes a rejected question.
 */
class ImportQuestions
{
    /**
     * @var array<string, int>|null
     */
    private ?array $categoryIds = null;

    /**
     * @param  iterable<int, mixed>  $items
     */
    public function handle(iterable $items, QuestionStatus $status = QuestionStatus::Draft): ImportResult
    {
        $result = new ImportResult;

        foreach ($items as $index => $item) {
            $validator = Validator::make(is_array($item) ? $item : [], $this->rules());

            if ($validator->fails()) {
                $result->failed[] = [
                    'index' => $index,
                    'ref' => is_array($item) && is_string($item['ref'] ?? null) ? $item['ref'] : null,
                    'errors' => array_values($validator->errors()->all()),
                ];

                continue;
            }

            $this->importOne($validator->validated(), $status)
                ? $result->created++
                : $result->updated++;
        }

        return $result;
    }

    /**
     * @param  array<string, mixed>  $data
     * @return bool Whether a new question was created.
     */
    private function importOne(array $data, QuestionStatus $status): bool
    {
        return DB::transaction(function () use ($data, $status) {
            $source = QuestionSource::from($data['source'] ?? QuestionSource::Manual->value);
            $ref = $data['ref'] ?? null;

            $question = $ref === null
                ? new Question
                : Question::query()->firstOrNew(['source' => $source, 'source_ref' => $ref]);

            $isNew = ! $question->exists;

            $question->fill([
                'category_id' => $this->categoryIds()[$data['category']],
                'difficulty' => Difficulty::from($data['difficulty'] ?? Difficulty::Medium->value),
                'correct_index' => $data['correct'],
                'source' => $source,
                'source_ref' => $ref,
                'license' => $data['license'] ?? null,
            ]);

            if ($isNew) {
                $question->status = $status;
            }

            $question->save();

            foreach ($data['translations'] as $locale => $translation) {
                $question->translations()->updateOrCreate(['locale' => $locale], [
                    'text' => trim($translation['text']),
                    'options' => array_map(trim(...), $translation['options']),
                    'explanation' => $translation['explanation'] ?? null,
                ]);
            }

            return $isNew;
        });
    }

    /**
     * @return array<string, mixed>
     */
    private function rules(): array
    {
        $optionCount = config('quizly.options_per_question');

        return [
            'ref' => ['nullable', 'string', 'max:255'],
            'category' => ['required', 'string', Rule::in(array_keys($this->categoryIds()))],
            'difficulty' => ['nullable', Rule::enum(Difficulty::class)],
            'correct' => ['required', 'integer', 'between:0,'.($optionCount - 1)],
            'source' => ['nullable', Rule::enum(QuestionSource::class)],
            'license' => ['nullable', 'string', 'max:255'],
            'translations.*' => ['array:text,options,explanation'],
            'translations.*.text' => ['required', 'string', 'max:500'],
            'translations.*.options' => ['required', 'array', "size:{$optionCount}"],
            'translations.*.options.*' => ['required', 'string', 'max:120'],
            'translations.*.explanation' => ['nullable', 'string', 'max:1000'],
            'translations' => ['required', 'array', 'min:1', $this->validTranslations(...)],
        ];
    }

    /**
     * Translations must use supported locales and offer distinct options.
     */
    private function validTranslations(string $attribute, mixed $value, Closure $fail): void
    {
        $translations = (array) $value;
        $unknown = array_diff(array_keys($translations), QuizlyConfig::locales());

        if ($unknown !== []) {
            $fail('Unsupported locale(s): '.implode(', ', $unknown).'.');
        }

        foreach ($translations as $locale => $translation) {
            $options = array_map(
                fn ($option) => is_string($option) ? mb_strtolower(trim($option)) : $option,
                (array) ($translation['options'] ?? []),
            );

            if (count($options) !== count(array_unique($options, SORT_REGULAR))) {
                $fail("The {$locale} options must all be different.");
            }
        }
    }

    /**
     * @return array<string, int>
     */
    private function categoryIds(): array
    {
        return $this->categoryIds ??= Category::query()->pluck('id', 'slug')->all();
    }
}
