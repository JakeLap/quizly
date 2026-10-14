<?php

namespace App\Console\Commands;

use App\Actions\Rooms\CloseRoom;
use App\Enums\GameStatus;
use App\Models\Game;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Builder;

#[Signature('quizly:expire-rooms')]
#[Description('Close rooms that have expired, and lobbies nobody joined')]
class ExpireRoomsCommand extends Command
{
    /**
     * Execute the console command.
     */
    public function handle(CloseRoom $closeRoom): int
    {
        $expired = Game::query()
            ->active()
            ->where(fn (Builder $query) => $query
                ->where('expires_at', '<=', now())
                ->orWhere(fn (Builder $query) => $query
                    ->where('status', GameStatus::Lobby)
                    ->where('created_at', '<=', now()->subMinutes(config('quizly.empty_lobby_minutes')))
                    ->whereDoesntHave('players', fn (Builder $players) => $players->whereNull('kicked_at'))))
            ->get();

        // Closing (rather than deleting) lets screens still open on the room
        // show that it was closed.
        $expired->each(fn (Game $game) => $closeRoom->handle($game));

        $this->components->info("Closed {$expired->count()} room(s).");

        return self::SUCCESS;
    }
}
