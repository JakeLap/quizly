<?php

namespace App\Support;

use App\Enums\GameStatus;
use App\Enums\RoundStatus;
use App\Game\GameEngine;
use App\Game\Standings;
use App\Models\Answer;
use App\Models\Category;
use App\Models\Game;
use App\Models\Player;
use App\Models\Round;

/**
 * The public state of a room, as shown to the host screen and every phone.
 *
 * It is sent as a whole on each change (rooms are small), so clients never
 * have to merge partial updates and can simply drop any snapshot whose
 * version is older than the one they already have.
 *
 * While a question is open it says who has answered, but never what they
 * answered or which answer is correct; that is only added at the reveal.
 */
final class RoomState
{
    /**
     * @return array<string, mixed>
     */
    public static function of(Game $game): array
    {
        $game->loadMissing(['categories.translations', 'participants']);

        return [
            'code' => $game->code,
            'status' => $game->status->value,
            'locale' => $game->locale,
            'version' => $game->state_version,
            // Which game this is in the room (round numbers restart in each).
            'match' => $game->match_number,
            'settings' => $game->settings->toArray(),
            'categories' => $game->categories
                ->sortBy(fn (Category $category) => [$category->sort_order, $category->id])
                ->map(fn (Category $category) => self::category($category, $game->locale))
                ->values()
                ->all(),
            'players' => $game->participants
                ->map(fn (Player $player) => self::player($player))
                ->values()
                ->all(),
            'round' => ($round = app(GameEngine::class)->currentRound($game)) ? self::round($game, $round) : null,
            'standings' => in_array($game->status, [GameStatus::Scoreboard, GameStatus::Finished], true) ? Standings::for($game) : null,
            'nextAt' => $game->next_transition_at?->getTimestampMs(),
            'paused' => $game->isPaused(),
            'serverNow' => now()->getTimestampMs(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public static function round(Game $game, Round $round): array
    {
        $translation = $round->question->translation($game->locale);
        $options = $translation->options ?? [];

        return [
            'number' => $round->number,
            'total' => $game->settings->rounds,
            'category' => self::category($round->category, $game->locale),
            'question' => $translation->text ?? '',
            'options' => array_map(fn (int $index) => $options[$index] ?? '', $round->option_order),
            'shownAt' => $round->shown_at?->getTimestampMs(),
            'opensAt' => $round->opens_at?->getTimestampMs(),
            'endsAt' => $round->ends_at?->getTimestampMs(),
            'answered' => $round->answers->pluck('player_id')->values()->all(),
            'reveal' => $round->status === RoundStatus::Revealed ? self::reveal($game, $round, $translation?->explanation) : null,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private static function reveal(Game $game, Round $round, ?string $explanation): array
    {
        $answers = $round->answers->keyBy('player_id');
        $slotOf = fn (Answer $answer) => (int) array_search($answer->option_index, $round->option_order, true);

        return [
            'correctSlot' => $round->correctSlot(),
            'explanation' => $explanation,
            'counts' => array_map(
                fn (int $slot) => $round->answers->filter(fn (Answer $answer) => $slotOf($answer) === $slot)->count(),
                array_keys($round->option_order),
            ),
            'results' => $game->participants
                ->map(function (Player $player) use ($answers, $slotOf) {
                    $answer = $answers->get($player->id);

                    return [
                        'playerId' => $player->id,
                        'slot' => $answer ? $slotOf($answer) : null,
                        'correct' => (bool) $answer?->is_correct,
                        'points' => $answer->points ?? 0,
                        'responseMs' => $answer?->response_ms,
                    ];
                })
                ->values()
                ->all(),
        ];
    }

    /**
     * @return array{id: int, slug: string, name: string, icon: string|null, color: string|null}
     */
    public static function category(Category $category, string $locale): array
    {
        return [
            'id' => $category->id,
            'slug' => $category->slug,
            'name' => $category->translation($locale)->name ?? $category->slug,
            'icon' => $category->icon,
            'color' => $category->color,
        ];
    }

    /**
     * @return array{id: int, nickname: string, color: string, isVip: bool, score: int}
     */
    public static function player(Player $player): array
    {
        return [
            'id' => $player->id,
            'nickname' => $player->nickname,
            'color' => $player->color,
            'isVip' => $player->is_vip,
            'score' => $player->score,
        ];
    }
}
