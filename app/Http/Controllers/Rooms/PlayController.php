<?php

namespace App\Http\Controllers\Rooms;

use App\Actions\Game\SubmitAnswer;
use App\Actions\Rooms\JoinRoom;
use App\Actions\Rooms\PlayAgain;
use App\Actions\Rooms\RemovePlayer;
use App\Actions\Rooms\StartGame;
use App\Actions\Rooms\UpdateRoomSettings;
use App\Http\Controllers\Controller;
use App\Http\Requests\Game\SubmitAnswerRequest;
use App\Http\Requests\Rooms\JoinRoomRequest;
use App\Http\Requests\Rooms\UpdateRoomSettingsRequest;
use App\Models\Game;
use App\Models\Player;
use App\Support\GuestCookies;
use App\Support\RoomCode;
use App\Support\RoomState;
use App\Support\SettingsOptions;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cookie;
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
            // Keep the player's cookie alive for as long as they keep playing.
            $token = request()->cookie(GuestCookies::PLAYER);

            if (is_string($token)) {
                Cookie::queue(GuestCookies::player($token));
            }

            return Inertia::render('play/Room', [
                'room' => RoomState::of($room),
                'me' => RoomState::player($player),
                'myAnswer' => $this->myAnswer($room, $player),
                'kicked' => ! $player->isParticipating(),
                'minPlayers' => config('quizly.players.min'),
                // Only the VIP can change the settings, so only they need the options.
                'settingsOptions' => fn () => $player->is_vip ? SettingsOptions::all() : null,
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
     * The VIP can also take a finished room back to the lobby from their phone.
     */
    public function playAgain(Game $room, PlayAgain $playAgain): RedirectResponse
    {
        abort_unless($this->currentPlayer()?->is_vip, 403);

        $playAgain->handle($room);

        return back();
    }

    /**
     * The VIP can set up the game from their phone too: easier than on the TV.
     */
    public function update(UpdateRoomSettingsRequest $request, Game $room, UpdateRoomSettings $updateSettings): RedirectResponse
    {
        abort_unless($this->currentPlayer()?->is_vip, 403);

        $updateSettings->handle($room, $request->settings(), $request->validated('locale'), $request->categoryIds());

        return back();
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
     * @return array{match: int, round: int, slot: int}|null
     */
    private function myAnswer(Game $room, Player $player): ?array
    {
        $round = $room->matchRounds()->where('number', $room->current_round)->first();
        $answer = $round?->answers()->where('player_id', $player->id)->first();

        if (! $round || ! $answer) {
            return null;
        }

        return ['match' => $room->match_number, 'round' => $round->number, 'slot' => (int) array_search($answer->option_index, $round->option_order, true)];
    }

    private function currentPlayer(): ?Player
    {
        $player = Auth::guard('player')->user();

        return $player instanceof Player ? $player : null;
    }
}
