<?php

namespace App\Actions\Rooms;

use App\Data\GameSettings;
use App\Enums\GameStatus;
use App\Models\Category;
use App\Models\Game;
use App\Support\RoomCode;
use App\Support\Token;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;

class CreateRoom
{
    /**
     * Open a new room in the lobby, with every category that has questions selected.
     *
     * @return array{0: Game, 1: string} The room and the plain host token for the host's cookie.
     */
    public function handle(?string $locale = null): array
    {
        $locale ??= config('quizly.default_locale');
        $token = Token::generate();

        // Two hosts could draw the same free code at the same moment; the
        // database index rejects the second, which then retries with a new code.
        $game = retry(5, fn () => DB::transaction(function () use ($locale, $token) {
            $game = Game::query()->create([
                'code' => $this->freeCode(),
                'host_token_hash' => Token::hash($token),
                'locale' => $locale,
                'status' => GameStatus::Lobby,
                'settings' => GameSettings::defaults(),
                'expires_at' => now()->addMinutes(config('quizly.room_lifetime_minutes')),
                'host_seen_at' => now(),
            ]);

            $game->categories()->attach($this->categoriesWithQuestions($locale));

            return $game;
        }), when: fn ($e) => $e instanceof UniqueConstraintViolationException);

        return [$game, $token];
    }

    /**
     * A code no room is using, nor a recently finished one (its players may
     * still play again in it).
     */
    private function freeCode(): string
    {
        do {
            $code = RoomCode::generate();
        } while (Game::findByCode($code) !== null);

        return $code;
    }

    /**
     * @return array<int, int>
     */
    private function categoriesWithQuestions(string $locale): array
    {
        return Category::query()
            ->active()
            ->whereHas('questions', fn ($query) => $query->playableIn($locale))
            ->get()
            ->map(fn (Category $category) => $category->id)
            ->values()
            ->all();
    }
}
