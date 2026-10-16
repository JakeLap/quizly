<?php

namespace App\Actions\Rooms;

use App\Enums\GameStatus;
use App\Events\RoomUpdated;
use App\Models\Game;
use App\Models\Player;
use App\Support\QuizlyConfig;
use App\Support\Token;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class JoinRoom
{
    /**
     * Add a player to a room in the lobby.
     *
     * @return array{0: Player, 1: string} The player and the plain token for the player's cookie.
     *
     * @throws ValidationException
     */
    public function handle(Game $game, string $nickname): array
    {
        $token = Token::generate();

        try {
            $player = DB::transaction(function () use ($game, $nickname, $token) {
                // Lock the room so two phones can't both take the last free spot.
                $game = Game::query()->lockForUpdate()->findOrFail($game->id);

                if ($game->status !== GameStatus::Lobby) {
                    throw ValidationException::withMessages(['code' => __('This game has already started.')]);
                }

                $players = $game->participants()->get();

                if ($players->count() >= $game->settings->maxPlayers) {
                    throw ValidationException::withMessages(['code' => __('This room is full.')]);
                }

                if ($players->contains(fn (Player $player) => mb_strtolower($player->nickname) === mb_strtolower($nickname))) {
                    throw $this->nicknameTaken();
                }

                $player = $game->players()->create([
                    'token_hash' => Token::hash($token),
                    'nickname' => $nickname,
                    'color' => $this->freeColor($players->map(fn (Player $player) => $player->color)->all()),
                    'is_vip' => $players->isEmpty(),
                ]);

                $game->bumpVersion();
                $game->save();

                RoomUpdated::send($game);

                return $player;
            });
        } catch (UniqueConstraintViolationException) {
            // The same nickname was submitted concurrently and lost the race.
            throw $this->nicknameTaken();
        }

        return [$player, $token];
    }

    /**
     * @param  array<int, string>  $taken
     */
    private function freeColor(array $taken): string
    {
        $colors = QuizlyConfig::playerColors();

        return collect($colors)->first(fn (string $color) => ! in_array($color, $taken, true))
            ?? $colors[count($taken) % count($colors)];
    }

    private function nicknameTaken(): ValidationException
    {
        return ValidationException::withMessages(['nickname' => __('That nickname is taken. Try another one.')]);
    }
}
