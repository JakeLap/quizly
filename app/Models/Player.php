<?php

namespace App\Models;

use App\Support\Token;
use Carbon\CarbonImmutable;
use Database\Factories\PlayerFactory;
use Illuminate\Auth\Authenticatable as AuthenticatableTrait;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * A guest who joined a room from their phone with a nickname.
 *
 * Players are Authenticatable so the `player` guard can resolve them from
 * their cookie, which is what lets them join the room's presence channel.
 *
 * @property int $id
 * @property int $game_id
 * @property string $token_hash
 * @property string $nickname
 * @property string $color
 * @property string|null $avatar
 * @property int $score
 * @property bool $is_connected
 * @property bool $is_vip
 * @property CarbonImmutable|null $last_seen_at
 * @property CarbonImmutable|null $kicked_at
 * @property CarbonImmutable|null $created_at
 * @property CarbonImmutable|null $updated_at
 * @property-read Game $game
 * @property-read Collection<int, Answer> $answers
 */
#[Fillable(['token_hash', 'nickname', 'color', 'avatar', 'is_vip'])]
#[Hidden(['token_hash'])]
class Player extends Model implements Authenticatable
{
    use AuthenticatableTrait;

    /** @use HasFactory<PlayerFactory> */
    use HasFactory;

    public static function findByToken(?string $token): ?self
    {
        if (blank($token)) {
            return null;
        }

        return static::query()->where('token_hash', Token::hash($token))->first();
    }

    /**
     * Presence channel member id. Prefixed so it can never collide with the host's.
     */
    public function getAuthIdentifierForBroadcasting(): string
    {
        return "player:{$this->id}";
    }

    public function isParticipating(): bool
    {
        return $this->kicked_at === null;
    }

    /**
     * @return BelongsTo<Game, $this>
     */
    public function game(): BelongsTo
    {
        return $this->belongsTo(Game::class);
    }

    /**
     * @return HasMany<Answer, $this>
     */
    public function answers(): HasMany
    {
        return $this->hasMany(Answer::class);
    }

    /**
     * Players still taking part, i.e. not kicked by the host.
     *
     * @param  Builder<Player>  $query
     */
    #[Scope]
    protected function participating(Builder $query): void
    {
        $query->whereNull('kicked_at');
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'score' => 'integer',
            'is_connected' => 'boolean',
            'is_vip' => 'boolean',
            'last_seen_at' => 'datetime',
            'kicked_at' => 'datetime',
        ];
    }
}
