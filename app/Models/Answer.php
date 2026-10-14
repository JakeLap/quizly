<?php

namespace App\Models;

use Carbon\CarbonImmutable;
use Database\Factories\AnswerFactory;
use Illuminate\Database\Eloquent\Attributes\DateFormat;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $round_id
 * @property int $player_id
 * @property int $option_index The chosen question option (not the display slot).
 * @property bool $is_correct
 * @property int $response_ms Time from the round's opens_at to the server receiving the answer.
 * @property int $points
 * @property CarbonImmutable $received_at
 * @property CarbonImmutable|null $created_at
 * @property CarbonImmutable|null $updated_at
 * @property-read Round $round
 * @property-read Player $player
 */
#[Fillable(['player_id', 'option_index', 'is_correct', 'response_ms', 'points', 'received_at'])]
#[DateFormat('Y-m-d H:i:s.v')]
class Answer extends Model
{
    /** @use HasFactory<AnswerFactory> */
    use HasFactory;

    /**
     * @return BelongsTo<Round, $this>
     */
    public function round(): BelongsTo
    {
        return $this->belongsTo(Round::class);
    }

    /**
     * @return BelongsTo<Player, $this>
     */
    public function player(): BelongsTo
    {
        return $this->belongsTo(Player::class);
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'option_index' => 'integer',
            'is_correct' => 'boolean',
            'response_ms' => 'integer',
            'points' => 'integer',
            'received_at' => 'datetime',
        ];
    }
}
