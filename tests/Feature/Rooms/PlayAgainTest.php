<?php

use App\Actions\Game\SubmitAnswer;
use App\Actions\Rooms\PlayAgain;
use App\Actions\Rooms\StartGame;
use App\Enums\GameStatus;
use App\Events\RoomUpdated;
use App\Game\GameEngine;
use App\Models\Category;
use App\Models\Game;
use App\Models\Player;
use App\Models\Question;
use App\Support\GuestCookies;
use App\Support\RoomState;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Event;
use Illuminate\Validation\ValidationException;

uses(RefreshDatabase::class);

beforeEach(function () {
    Event::fake([RoomUpdated::class]);
});

/**
 * Play a one-round game to the end in a room with two players.
 *
 * @return array{0: Game, 1: Player, 2: Player}
 */
function finishedRoom(int $questions = 3): array
{
    $room = Game::factory()->withHostToken('host-token')->create(['code' => 'ABCD', 'settings' => ['rounds' => 1, 'seconds_per_round' => 10]]);
    $category = Category::factory()->create();
    Question::factory()->for($category)->count($questions)->create();
    $room->categories()->attach($category);
    $vip = Player::factory()->for($room)->vip()->withToken('vip-token')->create();
    $other = Player::factory()->for($room)->withToken('player-token')->create();

    playOneRound($room, $vip);

    return [$room->fresh(), $vip->fresh(), $other->fresh()];
}

function playOneRound(Game $room, Player $scorer): void
{
    app(StartGame::class)->handle($room->fresh());
    $engine = app(GameEngine::class);

    // Intro → question; the scorer answers right, the round closes, the game ends.
    $engine->skip($room);
    $round = $engine->currentRound($room->fresh());
    test()->travelTo($round->opens_at->addSecond());
    app(SubmitAnswer::class)->handle($scorer, $round->number, $round->correctSlot(), now());

    // The other player doesn't answer: skip on to the reveal, then the end.
    while ($room->fresh()->status !== GameStatus::Finished) {
        $engine->skip($room);
    }

    Game::query()->whereKey($room->id)->update(['host_seen_at' => now()]);
}

test('the host can take a finished room back to the lobby with the same players', function () {
    [$room, $vip, $other] = finishedRoom();
    $kicked = Player::factory()->for($room)->create(['kicked_at' => now()]);
    expect($room->status)->toBe(GameStatus::Finished)->and($vip->score)->toBeGreaterThan(0);

    $this->withCookie(GuestCookies::HOST, 'host-token')
        ->post(route('host.rooms.play-again', $room))
        ->assertRedirect()
        ->assertSessionHasNoErrors();

    $room->refresh();
    expect($room->status)->toBe(GameStatus::Lobby)
        ->and($room->match_number)->toBe(2)
        ->and($room->current_round)->toBe(0)
        ->and($room->next_transition_at)->toBeNull()
        ->and($room->finished_at)->toBeNull()
        ->and($room->settings->rounds)->toBe(1) // settings carry over
        ->and($vip->fresh()->score)->toBe(0)
        ->and($room->participants()->pluck('id')->all())->toBe([$vip->id, $other->id])
        ->and($kicked->fresh()->kicked_at)->not->toBeNull()
        ->and(RoomState::of($room)['round'])->toBeNull()
        ->and(RoomState::of($room)['match'])->toBe(2);
    Event::assertDispatched(RoomUpdated::class);
});

test('the VIP can play again from their phone, other players cannot', function () {
    [$room] = finishedRoom();

    $this->withCookie(GuestCookies::PLAYER, 'player-token')->post(route('play.play-again', $room))->assertForbidden();
    Auth::forgetGuards();
    $this->withCookie(GuestCookies::PLAYER, 'vip-token')->post(route('play.play-again', $room))->assertSessionHasNoErrors();

    expect($room->fresh()->status)->toBe(GameStatus::Lobby);
});

test('the next game asks new questions and only counts its own scores', function () {
    [$room, $vip, $other] = finishedRoom();
    $firstQuestion = $room->rounds()->sole()->question_id;
    $this->withCookie(GuestCookies::HOST, 'host-token')->post(route('host.rooms.play-again', $room));

    playOneRound($room->fresh(), $other);

    $room->refresh();
    $rounds = $room->rounds()->get();
    $standings = RoomState::of($room)['standings'];

    expect($room->status)->toBe(GameStatus::Finished)
        ->and($rounds->pluck('match_number')->all())->toBe([1, 2])
        ->and($rounds->pluck('number')->all())->toBe([1, 1])
        ->and($rounds->last()->question_id)->not->toBe($firstQuestion)
        // Only this game's points: the first game's winner starts from zero.
        ->and($standings[0]['playerId'])->toBe($other->id)
        ->and($standings[0]['correct'])->toBe(1)
        ->and(collect($standings)->firstWhere('playerId', $vip->id)['score'])->toBe(0);
});

test('a game cannot start once every question has been used in the room', function () {
    [$room] = finishedRoom(questions: 1);
    $this->withCookie(GuestCookies::HOST, 'host-token')->post(route('host.rooms.play-again', $room));

    $this->withCookie(GuestCookies::HOST, 'host-token')
        ->post(route('host.rooms.start', $room))
        ->assertSessionHasErrors('start');
});

test('a player’s answer from the previous game is not shown as their answer now', function () {
    [$room] = finishedRoom();
    $this->withCookie(GuestCookies::HOST, 'host-token')->post(route('host.rooms.play-again', $room));
    Auth::forgetGuards();

    $this->withCookie(GuestCookies::PLAYER, 'vip-token')
        ->get('/play/ABCD')
        ->assertInertia(fn ($page) => $page->where('myAnswer', null)->where('room.match', 2));
});

test('only a finished room can play again', function () {
    $room = Game::factory()->withHostToken('host-token')->status(GameStatus::Question)->create();

    $this->withCookie(GuestCookies::HOST, 'host-token')
        ->post(route('host.rooms.play-again', $room))
        ->assertSessionHasErrors('room');
});

test('play again fails politely if another room took the code meanwhile', function () {
    // New rooms avoid recently finished codes, so this needs a race; the guard
    // still makes sure it can't break the database's uniqueness rule.
    [$room] = finishedRoom();
    Game::factory()->create(['code' => 'ABCD']);

    expect(fn () => app(PlayAgain::class)->handle($room))
        ->toThrow(ValidationException::class, __('This room code is now used by another room. Start a new room instead.'));

    expect($room->fresh()->status)->toBe(GameStatus::Finished);
});

test('new rooms do not take the code of a room that just finished', function () {
    Game::factory()->status(GameStatus::Finished)->create(['code' => 'ABCD', 'finished_at' => now()]);

    expect(Game::findByCode('ABCD'))->not->toBeNull();
});

test('opening the room renews the host and player cookies', function () {
    [$room] = finishedRoom();

    $this->withCookie(GuestCookies::HOST, 'host-token')->get(route('host.rooms.show', $room))->assertCookie(GuestCookies::HOST);
    Auth::forgetGuards();
    $this->withCookie(GuestCookies::PLAYER, 'vip-token')->get('/play/ABCD')->assertCookie(GuestCookies::PLAYER);
});

test('a room whose screen stayed on the results does not pause when the VIP plays again', function () {
    [$room] = finishedRoom();
    $engine = app(GameEngine::class);

    // The screen keeps heartbeating while everyone looks at the results.
    $this->travel(30)->seconds();
    $this->withCookie(GuestCookies::HOST, 'host-token')->post(route('host.rooms.heartbeat', $room))->assertNoContent();
    Auth::forgetGuards();

    $this->withCookie(GuestCookies::PLAYER, 'vip-token')->post(route('play.play-again', $room))->assertSessionHasNoErrors();
    $engine->advanceDue();

    expect($room->fresh())->status->toBe(GameStatus::Lobby)->paused_at->toBeNull();
});

test('a room whose screen really left during the results pauses when the VIP plays again', function () {
    [$room] = finishedRoom();

    $this->travel(30)->seconds();
    $this->withCookie(GuestCookies::PLAYER, 'vip-token')->post(route('play.play-again', $room))->assertSessionHasNoErrors();
    app(GameEngine::class)->advanceDue();

    expect($room->fresh()->paused_at)->not->toBeNull();
});
