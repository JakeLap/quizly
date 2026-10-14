<?php

namespace App\Http\Controllers\Rooms;

use App\Actions\Game\SubmitAnswer;
use App\Actions\Rooms\JoinRoom;
use App\Actions\Rooms\RemovePlayer;
use App\Actions\Rooms\StartGame;
use App\Http\Controllers\Controller;
use App\Http\Requests\Game\SubmitAnswerRequest;
use App\Http\Requests\Rooms\JoinRoomRequest;
use App\Models\Game;
use App\Models\Player;
use App\Support\GuestCookies;
use App\Support\RoomCode;
use App\Support\RoomState;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class PlayController extends Controller
{
    /**
     * The join form, e.g. when a player opens the site and types the code.
     */
    public function create(): Response
    {
        return Inertia::render('play/Join', ['code' => null]);
    }

    /**
     * The room on the player's phone, or the join form with the code filled in
     * (e.g. after scanning the QR code on the screen).
     */
    public function show(string $code): Response|RedirectResponse
    {
        $normalized = RoomCode::normalize($code);

        if ($normalized !== $code) {
            return $normalized ? to_route('play.show', $normalized) : to_route('play.create');
        }

        $room = Game::findByCode($code);

        if ($room) {
            app()->setLocale($room->locale);
        }

        $player = $this->currentPlayer();

        if ($room && $player?->game_id === $room->id) {
            return Inertia::render('play/Room', [
                'room' => RoomState::of($room),
                'me' => RoomState::player($player),
                'myAnswer' => $this->myAnswer($room, $player),
                'kicked' => ! $player->isParticipating(),
                'minPlayers' => config('quizly.players.min'),
            ]);
        }

        return Inertia::render('play/Join', ['code' => $code]);
    }

    public function store(JoinRoomRequest $request, JoinRoom $joinRoom): RedirectResponse
    {
        $code = $request->validated('code');
        $room = Game::findActiveByCode($code);

        if (! $room) {
            throw ValidationException::withMessages(['code' => __('There is no room with this code.')]);
        }

        // Already in this room (e.g. submitted the form twice): just go back to it.
        $player = $this->currentPlayer();

        if ($player?->game_id === $room->id && $player->isParticipating()) {
            return to_route('play.show', $room->code);
        }

        app()->setLocale($room->locale);

        [, $token] = $joinRoom->handle($room, $request->validated('nickname'));

        return to_route('play.show', $room->code)->withCookie(GuestCookies::player($token));
    }

    public function leave(Game $room, RemovePlayer $removePlayer): RedirectResponse
    {
        $player = $this->currentPlayer();
        abort_unless($player !== null, 403);

        $removePlayer->handle($player);

        return to_route('play.create')->withCookie(GuestCookies::forgetPlayer());
    }

    /**
     * The VIP (the first player to join) can start the game from their phone.
     */
    public function start(Game $room, StartGame $startGame): RedirectResponse
    {
        abort_unless($this->currentPlayer()?->is_vip, 403);

        $startGame->handle($room);

        return back();
    }

    public function answer(SubmitAnswerRequest $request, Game $room, SubmitAnswer $submitAnswer): RedirectResponse
    {
        $player = $this->currentPlayer();
        abort_unless($player !== null, 403);

        $submitAnswer->handle(
            $player,
            (int) $request->validated('round'),
            (int) $request->validated('slot'),
            $request->receivedAt(),
        );

        return back();
    }

    /**
     * Which button the player pressed in the current round, so it stays
     * highlighted if they reload the page.
     *
     * @return array{round: int, slot: int}|null
     */
    private function myAnswer(Game $room, Player $player): ?array
    {
        $round = $room->rounds()->where('number', $room->current_round)->first();
        $answer = $round?->answers()->where('player_id', $player->id)->first();

        if (! $round || ! $answer) {
            return null;
        }

        return ['round' => $round->number, 'slot' => (int) array_search($answer->option_index, $round->option_order, true)];
    }

    private function currentPlayer(): ?Player
    {
        $player = Auth::guard('player')->user();

        return $player instanceof Player ? $player : null;
    }
}
