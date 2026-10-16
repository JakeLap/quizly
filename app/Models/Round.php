<?php

namespace App\Models;

use App\Enums\RoundStatus;
use Carbon\CarbonImmutable;
use Database\Factories\RoundFactory;
use Illuminate\Database\Eloquent\Attributes\DateFormat;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * One question asked in a game.
 *
 * @property int $id
 * @property int $game_id
 * @property int $match_number
 * @property int $number
 * @property int $question_id
 * @property int $category_id
 * @property list<int> $option_order Question option indexes in the order they are displayed; display slot i shows option_order[i].
 * @property RoundStatus $status
 * @property CarbonImmutable|null $shown_at When the question appeared on screen.
 * @property CarbonImmutable|null $opens_at When answers unlock; response times are measured from here.
 * @property CarbonImmutable|null $ends_at The answer deadline.
 * @property CarbonImmutable|null $closed_at When the round actually stopped accepting answers.
 * @property CarbonImmutable|null $created_at
 * @property CarbonImmutable|null $updated_at
 * @property-read Game $game
 * @property-read Question $question
 * @property-read Category $category
 * @property-read Collection<int, Answer> $answers
 */
#[Fillable(['match_number', 'number', 'question_id', 'category_id', 'option_order', 'status', 'shown_at', 'opens_at', 'ends_at', 'closed_at'])]
#[DateFormat('Y-m-d H:i:s.v')]
class Round extends Model
{
    /** @use HasFactory<RoundFactory> */
    use HasFactory;

    /**
     * @return BelongsTo<Game, $this>
     */
    public function game(): BelongsTo
    {
        return $this->belongsTo(Game::class);
    }

    /**
     * @return BelongsTo<Question, $this>
     */
    public function question(): BelongsTo
    {
        return $this->belongsTo(Question::class);
    }

    /**
     * @return BelongsTo<Category, $this>
     */
    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    /**
     * @return HasMany<Answer, $this>
     */
    public function answers(): HasMany
    {
        return $this->hasMany(Answer::class);
    }

    /**
     * The question option index shown in the given display slot.
     */
    public function optionIndexForSlot(int $slot): ?int
    {
        return $this->option_order[$slot] ?? null;
    }

    /**
     * The display slot that holds the correct answer.
     */
    public function correctSlot(): int
    {
        return (int) array_search($this->question->correct_index, $this->option_order, true);
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'option_order' => 'array',
            'status' => RoundStatus::class,
            'shown_at' => 'datetime',
            'opens_at' => 'datetime',
            'ends_at' => 'datetime',
            'closed_at' => 'datetime',
        ];
    }
}
