<?php

namespace App\Http\Controllers\Rooms;

use App\Actions\Rooms\CloseRoom;
use App\Actions\Rooms\CreateRoom;
use App\Actions\Rooms\PlayAgain;
use App\Actions\Rooms\RecordHostHeartbeat;
use App\Actions\Rooms\RemoveDisconnectedPlayers;
use App\Actions\Rooms\RemovePlayer;
use App\Actions\Rooms\StartGame;
use App\Actions\Rooms\UpdateRoomSettings;
use App\Game\GameEngine;
use App\Http\Controllers\Controller;
use App\Http\Requests\Rooms\UpdateRoomSettingsRequest;
use App\Models\Game;
use App\Models\Player;
use App\Support\GuestCookies;
use App\Support\RoomState;
use App\Support\SettingsOptions;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response as HttpResponse;
use Illuminate\Support\Facades\Cookie;
use Inertia\Inertia;
use Inertia\Response;

class HostRoomController extends Controller
{
    public function store(CreateRoom $createRoom): RedirectResponse
    {
        [$room, $token] = $createRoom->handle(app()->getLocale());

        return to_route('host.rooms.show', $room)->withCookie(GuestCookies::host($token));
    }

    public function show(Request $request, Game $room, RecordHostHeartbeat $heartbeat): Response
    {
        // Opening the page counts as the screen being back.
        $heartbeat->handle($room);
        $room->refresh();

        // An evening of games can outlast the cookie: renew it on every visit.
        $token = $request->cookie(GuestCookies::HOST);

        if (is_string($token)) {
            Cookie::queue(GuestCookies::host($token));
        }

        return Inertia::render('host/Room', [
            'room' => RoomState::of($room),
            ...SettingsOptions::all(),
            'joinUrl' => route('play.show', $room->code),
            'disconnectGraceSeconds' => config('quizly.lobby_disconnect_grace_seconds'),
            'heartbeatSeconds' => config('quizly.host.heartbeat_seconds'),
        ]);
    }

    public function update(UpdateRoomSettingsRequest $request, Game $room, UpdateRoomSettings $updateSettings): RedirectResponse
    {
        $updateSettings->handle($room, $request->settings(), $request->validated('locale'), $request->categoryIds());

        return back();
    }

    public function start(Game $room, StartGame $startGame): RedirectResponse
    {
        $startGame->handle($room);

        return back();
    }

    /**
     * The host screen is still open (sent every few seconds).
     */
    public function heartbeat(Game $room, RecordHostHeartbeat $heartbeat): HttpResponse
    {
        $heartbeat->handle($room);

        return response()->noContent();
    }

    /**
     * Skip the rest of the current phase (intro, question, reveal or scoreboard).
     */
    public function next(Game $room, GameEngine $engine): RedirectResponse
    {
        $engine->skip($room);

        return back();
    }

    /**
     * The host screen saw a player disconnect. The server checks with Reverb
     * itself rather than trusting the screen: in the lobby, players gone for
     * longer than the grace period are removed; during a question, it ends
     * early if everyone still connected has answered.
     */
    public function presenceChanged(Request $request, Game $room, GameEngine $engine, RemoveDisconnectedPlayers $removeDisconnected): HttpResponse
    {
        $validated = $request->validate([
            'gone' => ['sometimes', 'array', 'max:'.config('quizly.players.max')],
            'gone.*' => ['integer'],
        ]);

        $removeDisconnected->handle($room, array_values(array_map(intval(...), $validated['gone'] ?? [])));
        $engine->endQuestionIfEveryoneAnswered($room);

        return response()->noContent();
    }

    public function kick(Game $room, Player $player, RemovePlayer $removePlayer): RedirectResponse
    {
        $removePlayer->handle($player, kicked: true);

        return back();
    }

    /**
     * Back to the lobby with the same players, for another game.
     */
    public function playAgain(Game $room, PlayAgain $playAgain): RedirectResponse
    {
        $playAgain->handle($room);

        return back();
    }

    public function close(Game $room, CloseRoom $closeRoom): RedirectResponse
    {
        $closeRoom->handle($room);

        return to_route('home');
    }
}
