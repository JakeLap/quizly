<?php

namespace App\Models;

use Carbon\CarbonImmutable;
use Database\Factories\QuestionTranslationFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $question_id
 * @property string $locale
 * @property string $text
 * @property list<string> $options Answer options, in the same order in every locale.
 * @property string|null $explanation
 * @property int|null $reviewed_by
 * @property CarbonImmutable|null $reviewed_at
 * @property CarbonImmutable|null $created_at
 * @property CarbonImmutable|null $updated_at
 * @property-read Question $question
 * @property-read User|null $reviewer
 */
#[Fillable(['locale', 'text', 'options', 'explanation', 'reviewed_by', 'reviewed_at'])]
class QuestionTranslation extends Model
{
    /** @use HasFactory<QuestionTranslationFactory> */
    use HasFactory;

    /**
     * @return BelongsTo<Question, $this>
     */
    public function question(): BelongsTo
    {
        return $this->belongsTo(Question::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'options' => 'array',
            'reviewed_at' => 'datetime',
        ];
    }
}
