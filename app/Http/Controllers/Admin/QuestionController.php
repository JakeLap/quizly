<?php

namespace App\Http\Controllers\Admin;

use App\Enums\Difficulty;
use App\Enums\QuestionSource;
use App\Enums\QuestionStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\QuestionRequest;
use App\Models\Category;
use App\Models\Question;
use App\Models\QuestionTranslation;
use App\Models\Round;
use App\Support\QuizlyConfig;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

/**
 * The question admin: browse, write, edit and review questions.
 */
class QuestionController extends Controller
{
    private const int PER_PAGE = 25;

    /** The type filter's value for questions without a type. */
    private const string NO_KIND = '__none';

    public function index(Request $request): Response
    {
        $filters = $request->validate([
            'search' => ['nullable', 'string', 'max:100'],
            'category' => ['nullable', 'integer'],
            'status' => ['nullable', Rule::enum(QuestionStatus::class)],
            'difficulty' => ['nullable', Rule::enum(Difficulty::class)],
            'source' => ['nullable', Rule::enum(QuestionSource::class)],
            'kind' => ['nullable', 'string', 'max:64'],
        ]);

        // The status tabs count what the other filters match.
        $counts = $this->filtered(array_merge($filters, ['status' => null]))
            ->toBase()
            ->selectRaw('status, count(*) as aggregate')
            ->groupBy('status')
            ->pluck('aggregate', 'status')
            ->map(fn ($count) => (int) $count);

        $questions = $this->filtered($filters)
            ->with(['translations', 'category.translations'])
            ->latest('updated_at')
            ->latest('id')
            ->paginate(self::PER_PAGE)
            ->withQueryString()
            ->through(fn (Question $question) => $this->summary($question));

        return Inertia::render('admin/questions/Index', [
            'questions' => $questions,
            'filters' => array_merge(['search' => null, 'category' => null, 'status' => null, 'difficulty' => null, 'source' => null, 'kind' => null], $filters),
            'noKind' => self::NO_KIND,
            'statusCounts' => ['all' => $counts->sum(), ...$counts->all()],
            ...$this->options(),
        ]);
    }

    public function create(): Response
    {
        return Inertia::render('admin/questions/Edit', [
            'question' => null,
            ...$this->options(),
        ]);
    }

    public function store(QuestionRequest $request): RedirectResponse
    {
        $question = DB::transaction(function () use ($request) {
            $question = new Question([
                'category_id' => $request->integer('category_id'),
                'kind' => $request->validated('kind'),
                'difficulty' => $request->enum('difficulty', Difficulty::class),
                'correct_index' => $request->integer('correct_index'),
                'source' => QuestionSource::Manual,
            ]);
            $question->status = $request->enum('status', QuestionStatus::class) ?? QuestionStatus::Draft;
            $question->edited_at = now();
            $question->save();

            $this->saveTranslations($question, $request->translations(), (int) $request->user()?->id);

            return $question;
        });

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Question added.')]);

        return to_route('admin.questions.edit', $question);
    }

    public function edit(Question $question): Response
    {
        $question->load(['translations.reviewer']);

        return Inertia::render('admin/questions/Edit', [
            'question' => [
                'id' => $question->id,
                'category_id' => $question->category_id,
                'kind' => $question->kind,
                'difficulty' => $question->difficulty->value,
                'status' => $question->status->value,
                'correct_index' => $question->correct_index,
                'source' => $question->source->value,
                'source_ref' => $question->source_ref,
                'license' => $question->license,
                'times_asked' => $question->times_asked,
                'correct_rate' => $question->correctRate(),
                'edited_at' => $question->edited_at?->toIso8601String(),
                'updated_at' => $question->updated_at?->toIso8601String(),
                'can_delete' => $this->canDelete($question),
                'translations' => $question->translations->mapWithKeys(fn (QuestionTranslation $translation) => [
                    $translation->locale => [
                        'text' => $translation->text,
                        'options' => $translation->options,
                        'explanation' => $translation->explanation,
                        'reviewed_by' => $translation->reviewer?->name,
                        'reviewed_at' => $translation->reviewed_at?->toIso8601String(),
                    ],
                ]),
            ],
            ...$this->options(),
        ]);
    }

    public function update(QuestionRequest $request, Question $question): RedirectResponse
    {
        DB::transaction(function () use ($request, $question) {
            $question->load('translations');

            $question->fill([
                'category_id' => $request->integer('category_id'),
                'kind' => $request->validated('kind'),
                'difficulty' => $request->enum('difficulty', Difficulty::class),
                'correct_index' => $request->integer('correct_index'),
            ]);
            $question->status = $request->enum('status', QuestionStatus::class) ?? $question->status;

            // Mark real content changes, so imports of the question files
            // don't overwrite them. A status change alone doesn't count.
            if ($question->isDirty(['category_id', 'kind', 'difficulty', 'correct_index']) || $this->wordingChanged($question, $request->translations())) {
                $question->edited_at = now();
            }

            $question->save();

            $this->saveTranslations($question, $request->translations(), (int) $request->user()?->id);
        });

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Question saved.')]);

        return back();
    }

    /**
     * Publish, reject or send back a question, e.g. from the list.
     */
    public function updateStatus(Request $request, Question $question): RedirectResponse
    {
        $validated = $request->validate(['status' => ['required', Rule::enum(QuestionStatus::class)]]);

        $question->status = QuestionStatus::from($validated['status']);
        $question->save();

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Status changed.')]);

        return back();
    }

    public function destroy(Question $question): RedirectResponse
    {
        if (! $this->canDelete($question)) {
            Inertia::flash('toast', ['type' => 'error', 'message' => __('This question can’t be deleted. Reject it instead.')]);

            return back();
        }

        $question->delete();

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Question deleted.')]);

        return to_route('admin.questions.index');
    }

    /**
     * Only questions written here and never played can go: a played one is
     * part of past games, and one from a question file would come back with
     * the next import (rejecting it keeps it out of games for good).
     */
    private function canDelete(Question $question): bool
    {
        return $question->source_ref === null
            && ! Round::query()->where('question_id', $question->id)->exists();
    }

    /**
     * @param  array<string, mixed>  $filters
     * @return Builder<Question>
     */
    private function filtered(array $filters): Builder
    {
        $search = trim((string) ($filters['search'] ?? ''));

        return Question::query()
            ->when($filters['category'] ?? null, fn (Builder $query, $category) => $query->where('category_id', $category))
            ->when($filters['status'] ?? null, fn (Builder $query, $status) => $query->where('status', $status))
            ->when($filters['difficulty'] ?? null, fn (Builder $query, $difficulty) => $query->where('difficulty', $difficulty))
            ->when($filters['source'] ?? null, fn (Builder $query, $source) => $query->where('source', $source))
            ->when($filters['kind'] ?? null, fn (Builder $query, $kind) => $kind === self::NO_KIND
                ? $query->whereNull('kind')
                : $query->where('kind', $kind))
            ->when($search !== '', function (Builder $query) use ($search) {
                $like = '%'.addcslashes($search, '%_\\').'%';

                $query->where(fn (Builder $query) => $query
                    ->whereHas('translations', fn (Builder $translations) => $translations
                        ->where('text', 'ilike', $like)
                        ->orWhereRaw('options::text ilike ?', [$like]))
                    ->orWhere('source_ref', 'ilike', $like)
                    ->when(ctype_digit($search), fn (Builder $query) => $query->orWhere('id', (int) $search)));
            });
    }

    /**
     * A question as one row of the list.
     *
     * @return array<string, mixed>
     */
    private function summary(Question $question): array
    {
        $texts = $question->translations->pluck('text', 'locale');
        $locale = app()->getLocale();

        return [
            'id' => $question->id,
            'text' => $texts[$locale] ?? $texts->first(),
            'category' => $question->category->translation($locale)->name ?? $question->category->slug,
            'category_color' => $question->category->color,
            'kind' => $question->kind,
            'difficulty' => $question->difficulty->value,
            'status' => $question->status->value,
            'source' => $question->source->value,
            'missing_locales' => array_values(array_diff(QuizlyConfig::locales(), $texts->keys()->all())),
            'times_asked' => $question->times_asked,
            'correct_rate' => $question->correctRate(),
            'edited' => $question->edited_at !== null,
        ];
    }

    /**
     * @param  array<string, array{text: string, options: list<string>, explanation: string|null}>  $translations
     */
    private function wordingChanged(Question $question, array $translations): bool
    {
        $current = $question->translations
            ->mapWithKeys(fn (QuestionTranslation $translation) => [$translation->locale => [
                'text' => $translation->text,
                'options' => $translation->options,
                'explanation' => $translation->explanation,
            ]])
            ->sortKeys()
            ->all();

        ksort($translations);

        return $current !== $translations;
    }

    /**
     * @param  array<string, array{text: string, options: list<string>, explanation: string|null}>  $translations
     */
    private function saveTranslations(Question $question, array $translations, int $reviewerId): void
    {
        foreach ($translations as $locale => $translation) {
            $question->translations()->updateOrCreate(['locale' => $locale], [
                ...$translation,
                'reviewed_by' => $reviewerId ?: null,
                'reviewed_at' => now(),
            ]);
        }

        // A language emptied in the form is no longer translated.
        $question->translations()->whereNotIn('locale', array_keys($translations))->delete();
    }

    /**
     * What the filters and the form can choose from.
     *
     * @return array<string, mixed>
     */
    private function options(): array
    {
        $locale = app()->getLocale();

        return [
            'categories' => Category::query()->ordered()->with('translations')->get()
                ->map(fn (Category $category) => [
                    'id' => $category->id,
                    'name' => $category->translation($locale)->name ?? $category->slug,
                    'color' => $category->color,
                    'active' => $category->is_active,
                ])->values(),
            // Types already in use, to pick from rather than misspell.
            'kinds' => Question::query()->whereNotNull('kind')->distinct()->orderBy('kind')->pluck('kind'),
            'difficulties' => array_column(Difficulty::cases(), 'value'),
            'statuses' => array_column(QuestionStatus::cases(), 'value'),
            'sources' => array_column(QuestionSource::cases(), 'value'),
            'locales' => QuizlyConfig::locales(),
            'optionCount' => config('quizly.options_per_question'),
        ];
    }
}
