<?php

namespace App\Models;

use App\Data\GameSettings;
use App\Enums\GameStatus;
use App\Support\Token;
use Carbon\CarbonImmutable;
use Database\Factories\GameFactory;
use Illuminate\Database\Eloquent\Attributes\DateFormat;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\MassPrunable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * A room, hosted on a shared screen such as a TV.
 *
 * @property int $id
 * @property string $code
 * @property string $host_token_hash
 * @property string $locale
 * @property GameStatus $status
 * @property GameSettings $settings
 * @property int $current_round
 * @property int $match_number Which game this is in the room; "play again" starts the next one.
 * @property int $state_version Incremented on every state change, so clients can ignore outdated updates.
 * @property CarbonImmutable|null $next_transition_at When the current phase ends and the game clock moves the game on. While paused: when the room closes unless the host returns.
 * @property CarbonImmutable|null $host_seen_at The host screen's last heartbeat.
 * @property CarbonImmutable|null $paused_at When the game paused because the host screen went away.
 * @property int|null $paused_remaining_ms How much of the current phase was left when it paused.
 * @property CarbonImmutable|null $started_at
 * @property CarbonImmutable|null $finished_at
 * @property CarbonImmutable $expires_at
 * @property CarbonImmutable|null $created_at
 * @property CarbonImmutable|null $updated_at
 * @property-read Collection<int, Category> $categories
 * @property-read Collection<int, Player> $players
 * @property-read Collection<int, Round> $rounds
 */
#[Fillable(['code', 'host_token_hash', 'locale', 'status', 'settings', 'expires_at', 'host_seen_at'])]
#[Hidden(['host_token_hash'])]
#[DateFormat('Y-m-d H:i:s.v')]
class Game extends Model
{
    /** @use HasFactory<GameFactory> */
    use HasFactory;

    /**
     * The database defaults, so a new model has them before it's reloaded.
     *
     * @var array<string, mixed>
     */
    protected $attributes = [
        'current_round' => 0,
        'match_number' => 1,
        'state_version' => 0,
    ];

    use MassPrunable;

    public static function findActiveByCode(string $code): ?self
    {
        return static::query()
            ->active()
            ->where('code', strtoupper($code))
            ->where('expires_at', '>', now())
            ->first();
    }

    /**
     * The active room with this code or, failing that, a recently finished
     * one, so the final results can still be shown.
     */
    public static function findByCode(string $code): ?self
    {
        return static::findActiveByCode($code)
            ?? static::query()
                ->where('code', strtoupper($code))
                ->where('status', GameStatus::Finished)
                ->where('finished_at', '>', now()->subMinutes(config('quizly.results_visible_minutes')))
                ->latest('finished_at')
                ->first();
    }

    public static function findByHostToken(?string $token): ?self
    {
        if (blank($token)) {
            return null;
        }

        return static::query()->where('host_token_hash', Token::hash($token))->first();
    }

    /**
     * Rooms appear in URLs by their code.
     */
    public function getRouteKeyName(): string
    {
        return 'code';
    }

    /**
     * @return BelongsToMany<Category, $this>
     */
    public function categories(): BelongsToMany
    {
        return $this->belongsToMany(Category::class, 'game_category');
    }

    /**
     * @return HasMany<Player, $this>
     */
    public function players(): HasMany
    {
        return $this->hasMany(Player::class);
    }

    /**
     * Players still in the room, in the order they joined.
     *
     * @return HasMany<Player, $this>
     */
    public function participants(): HasMany
    {
        return $this->players()->participating()->orderBy('id');
    }

    /**
     * Every round played in the room, over all its games.
     *
     * @return HasMany<Round, $this>
     */
    public function rounds(): HasMany
    {
        return $this->hasMany(Round::class)->orderBy('match_number')->orderBy('number');
    }

    /**
     * The rounds of the game being played now (the room's current match).
     *
     * @return HasMany<Round, $this>
     */
    public function matchRounds(): HasMany
    {
        return $this->hasMany(Round::class)->where('match_number', $this->match_number)->orderBy('number');
    }

    /**
     * Old rooms deleted by `model:prune`. Their players, rounds and answers
     * are deleted with them by the database (cascading foreign keys).
     *
     * @return Builder<Game>
     */
    public function prunable(): Builder
    {
        return Game::query()
            ->whereIn('status', [GameStatus::Finished, GameStatus::Abandoned])
            ->where('updated_at', '<=', now()->subDays(config('quizly.prune_after_days')));
    }

    public function isPaused(): bool
    {
        return $this->paused_at !== null;
    }

    /**
     * Whether the host screen has sent a heartbeat recently.
     */
    public function hostIsPresent(): bool
    {
        return $this->host_seen_at !== null
            && $this->host_seen_at->greaterThan(now()->subSeconds(config('quizly.host.timeout_seconds')));
    }

    /**
     * Record a state change, so clients can ignore updates older than what they have.
     */
    public function bumpVersion(): void
    {
        $this->state_version++;
    }

    /**
     * @param  Builder<Game>  $query
     */
    #[Scope]
    protected function active(Builder $query): void
    {
        $query->whereIn('status', GameStatus::activeValues());
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => GameStatus::class,
            'settings' => GameSettings::class,
            'started_at' => 'datetime',
            'finished_at' => 'datetime',
            'expires_at' => 'datetime',
            'next_transition_at' => 'datetime',
            'host_seen_at' => 'datetime',
            'paused_at' => 'datetime',
            'paused_remaining_ms' => 'integer',
        ];
    }
}
