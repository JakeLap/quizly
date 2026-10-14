<?php

namespace App\Game;

use App\Models\Game;
use App\Models\Question;

class QuestionPicker
{
    /**
     * A question for the game's next round, never one it has already asked.
     *
     * A random category is drawn first and then a random question in it, so
     * a category with many questions doesn't crowd out the others.
     */
    public function pick(Game $game): ?Question
    {
        $candidates = Question::query()
            ->playableIn($game->locale)
            ->whereIn('category_id', $game->categories()->select('categories.id'))
            ->whereNotIn('id', $game->rounds()->select('question_id'));

        $categoryId = (clone $candidates)->distinct()->pluck('category_id')->shuffle()->first();

        if ($categoryId === null) {
            return null;
        }

        return $candidates->where('category_id', $categoryId)->inRandomOrder()->first();
    }
}
