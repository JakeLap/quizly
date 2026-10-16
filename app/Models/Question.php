<?php

namespace App\Models;

use App\Enums\Difficulty;
use App\Enums\QuestionSource;
use App\Enums\QuestionStatus;
use App\Models\Concerns\HasTranslations;
use Carbon\CarbonImmutable;
use Database\Factories\QuestionFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property int $id
 * @property int $category_id
 * @property Difficulty $difficulty
 * @property int $correct_index
 * @property QuestionStatus $status
 * @property QuestionSource $source
 * @property string|null $source_ref
 * @property string|null $license
 * @property int $times_asked
 * @property int $times_correct
 * @property CarbonImmutable|null $created_at
 * @property CarbonImmutable|null $updated_at
 * @property-read Category $category
 * @property-read Collection<int, QuestionTranslation> $translations
 */
#[Fillable(['category_id', 'difficulty', 'correct_index', 'status', 'source', 'source_ref', 'license'])]
class Question extends Model
{
    /** @use HasFactory<QuestionFactory> */
    use HasFactory;

    /** @use HasTranslations<QuestionTranslation> */
    use HasTranslations;

    /**
     * @return BelongsTo<Category, $this>
     */
    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    /**
     * @return HasMany<QuestionTranslation, $this>
     */
    public function translations(): HasMany
    {
        return $this->hasMany(QuestionTranslation::class);
    }

    /**
     * @param  Builder<Question>  $query
     */
    #[Scope]
    protected function published(Builder $query): void
    {
        $query->where('status', QuestionStatus::Published);
    }

    /**
     * Questions that can be asked in a room played in the given locale.
     *
     * @param  Builder<Question>  $query
     */
    #[Scope]
    protected function playableIn(Builder $query, string $locale): void
    {
        $query->published()->whereHas('translations', fn (Builder $q) => $q->where('locale', $locale));
    }

    /**
     * The share of players who answered correctly, or null if never asked.
     */
    public function correctRate(): ?float
    {
        return $this->times_asked > 0 ? $this->times_correct / $this->times_asked : null;
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'difficulty' => Difficulty::class,
            'status' => QuestionStatus::class,
            'source' => QuestionSource::class,
            'correct_index' => 'integer',
        ];
    }
}
