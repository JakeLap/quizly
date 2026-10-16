<?php

namespace App\Actions\Rooms;

use App\Enums\GameStatus;
use App\Game\GameEngine;
use App\Models\Game;
use App\Models\Question;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class StartGame
{
    public function __construct(private GameEngine $engine) {}

    /**
     * Close the lobby and start the game.
     *
     * @throws ValidationException
     */
    public function handle(Game $game): void
    {
        DB::transaction(function () use ($game) {
            $game = Game::query()->lockForUpdate()->findOrFail($game->id);

            if ($game->status !== GameStatus::Lobby) {
                throw ValidationException::withMessages(['start' => __('This game has already started.')]);
            }

            // The questions are shown on the screen, so it has to be there.
            if ($game->isPaused() || ! $game->hostIsPresent()) {
                throw ValidationException::withMessages(['start' => __('The screen is offline. Open the room on the screen to start.')]);
            }

            $minPlayers = config('quizly.players.min');

            if ($game->participants()->count() < $minPlayers) {
                throw ValidationException::withMessages([
                    'start' => __('At least :count players are needed to start.', ['count' => $minPlayers]),
                ]);
            }

            // Questions asked in the room's earlier games aren't asked again.
            $available = Question::query()
                ->playableIn($game->locale)
                ->whereIn('category_id', $game->categories()->select('categories.id'))
                ->whereNotIn('id', $game->rounds()->select('question_id'))
                ->count();

            if ($available < $game->settings->rounds) {
                throw ValidationException::withMessages([
                    'start' => __('The chosen categories only have :count questions for :rounds rounds. Pick more categories or fewer rounds.', [
                        'count' => $available,
                        'rounds' => $game->settings->rounds,
                    ]),
                ]);
            }

            $this->engine->start($game);
        });
    }
}
