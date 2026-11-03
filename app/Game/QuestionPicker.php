<?php

namespace App\Game;

use App\Models\Game;
use App\Models\Question;
use App\Models\Round;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use LogicException;
use stdClass;

class QuestionPicker
{
    /**
     * A question for the next round, of the room's categories and
     * difficulties, never one already asked in this room (including in its
     * earlier games), chosen for variety:
     *
     * 1. the category this game has asked least so far;
     * 2. within it, the kind of question it has asked least (e.g. "calling
     *    code of a country"), every kind equally likely however many
     *    questions it has, and similar kinds counting as one family (see
     *    quizly.question_families). A category's hand-written questions
     *    (without a kind) count as one family too;
     * 3. a random question of that kind.
     *
     * So the same kind only comes back once the category's others have had
     * a turn. Ties are broken at random, but not in favour of what the
     * previous round asked.
     */
    public function pick(Game $game): ?Question
    {
        $asked = $this->askedThisGame($game);

        $categoryIds = $this->candidates($game)->distinct()->pluck('category_id')->map(intval(...))->values()->all();

        if ($categoryIds === []) {
            return null;
        }

        $previous = $asked->last();

        $categoryId = $this->leastUsed($categoryIds, $asked->countBy('category_id')->all(), $previous['category_id'] ?? null);

        $families = $this->families($game, $categoryId);
        $family = $this->leastUsed(array_keys($families), $asked->countBy('family')->all(), $previous['family'] ?? null);

        return $this->candidates($game)
            ->where('category_id', $categoryId)
            ->where(fn (Builder $query) => $query
                ->whereIn('kind', $families[$family]['kinds'])
                ->orWhereIn('id', $families[$family]['ids']))
            ->inRandomOrder()
            ->first();
    }

    /**
     * @return Builder<Question>
     */
    private function candidates(Game $game): Builder
    {
        return Question::query()
            ->playableIn($game->locale)
            ->whereIn('category_id', $game->categories()->select('categories.id'))
            ->whereIn('difficulty', $game->settings->difficulties)
            ->whereNotIn('id', $game->rounds()->select('question_id'));
    }

    /**
     * The category and family of every question asked in this game so far.
     *
     * @return Collection<int, array{category_id: int, family: string}>
     */
    private function askedThisGame(Game $game): Collection
    {
        return Round::query()
            ->join('questions', 'questions.id', '=', 'rounds.question_id')
            ->where('rounds.game_id', $game->id)
            ->where('rounds.match_number', $game->match_number)
            ->orderBy('rounds.number')
            ->toBase()
            ->get(['rounds.category_id', 'rounds.question_id', 'questions.kind'])
            ->map(fn (stdClass $round) => [
                'category_id' => intval($round->category_id),
                'family' => $this->family(is_string($round->kind) ? $round->kind : null, intval($round->category_id)),
            ]);
    }

    /**
     * The families of question left to ask in a category, each with its kinds
     * and its hand-written questions (those without a kind).
     *
     * @return array<string, array{kinds: list<string>, ids: list<int>}>
     */
    private function families(Game $game, int $categoryId): array
    {
        $families = [];

        $kinds = $this->candidates($game)->where('category_id', $categoryId)->whereNotNull('kind')->distinct()->pluck('kind');

        foreach ($kinds->filter(fn (mixed $kind) => is_string($kind)) as $kind) {
            $family = $this->family($kind, $categoryId);
            $families[$family] ??= ['kinds' => [], 'ids' => []];
            $families[$family]['kinds'][] = $kind;
        }

        $handWritten = $this->candidates($game)->where('category_id', $categoryId)->whereNull('kind')->pluck('id');

        if ($handWritten->isNotEmpty()) {
            $families[$this->family(null, $categoryId)] = ['kinds' => [], 'ids' => array_values(array_map(intval(...), $handWritten->all()))];
        }

        return $families;
    }

    private function family(?string $kind, int $categoryId): string
    {
        if ($kind === null) {
            return "hand-written:{$categoryId}";
        }

        $families = config('quizly.question_families');

        return is_array($families) && is_string($families[$kind] ?? null) ? $families[$kind] : $kind;
    }

    /**
     * The key used least so far, at random among ties, but not the one used
     * last if there's another.
     *
     * @template TKey of int|string
     *
     * @param  array<int, TKey>  $keys
     * @param  array<array-key, int>  $uses
     * @return TKey
     */
    private function leastUsed(array $keys, array $uses, int|string|null $last = null): int|string
    {
        if ($keys === []) {
            throw new LogicException('There is nothing to choose from.');
        }

        $fewest = min(array_map(fn (int|string $key) => $uses[$key] ?? 0, $keys));
        $tied = array_values(array_filter($keys, fn (int|string $key) => ($uses[$key] ?? 0) === $fewest));

        if (count($tied) > 1) {
            $tied = array_values(array_filter($tied, fn (int|string $key) => $key !== $last));
        }

        return $tied[array_rand($tied)];
    }
}
