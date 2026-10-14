<?php

use App\Actions\Game\SubmitAnswer;
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
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Event;
use Illuminate\Validation\ValidationException;

uses(RefreshDatabase::class);

beforeEach(function () {
    Event::fake([RoomUpdated::class]);
    $this->travelTo(Carbon::parse('2026-10-14 12:00:00.000'));
});

function at(string $time): void
{
    test()->travelTo(Carbon::parse("2026-10-14 {$time}"));
}

function clockTick(): void
{
    app(GameEngine::class)->advanceDue();
}

function heartbeatFromScreen(Game $game): void
{
    test()->withCookie(GuestCookies::HOST, 'host-token')
        ->post(route('host.rooms.heartbeat', $game))
        ->assertNoContent();
}

/**
 * A game whose first question (60 s) opened at 12:00:07, with the host
 * screen last seen at 12:00:08.
 *
 * @return array{0: Game, 1: list<Player>}
 */
function questionShowing(): array
{
    $game = Game::factory()->withHostToken('host-token')->create(['code' => 'ABCD', 'settings' => ['rounds' => 2, 'seconds_per_round' => 60]]);
    $category = Category::factory()->create();
    Question::factory()->for($category)->count(2)->create();
    $game->categories()->attach($category);
    $players = [Player::factory()->for($game)->create(), Player::factory()->for($game)->create()];

    app(StartGame::class)->handle($game);
    at('12:00:04');
    clockTick();
    at('12:00:08');
    heartbeatFromScreen($game);

    return [$game->fresh(), $players];
}

test('the game pauses once the screen has been silent for 10 seconds', function () {
    [$game] = questionShowing();

    at('12:00:17');
    clockTick();
    expect($game->fresh()->isPaused())->toBeFalse();

    at('12:00:18.500');
    clockTick();

    $game->refresh();
    expect($game->isPaused())->toBeTrue()
        ->and($game->status)->toBe(GameStatus::Question)
        ->and($game->paused_remaining_ms)->toBe(48800) // until 12:01:07.300
        ->and($game->next_transition_at->format('H:i:s.v'))->toBe('12:02:18.500')
        ->and(RoomState::of($game)['paused'])->toBeTrue();
    Event::assertDispatched(RoomUpdated::class);
});

test('players cannot answer while the game is paused', function () {
    [$game, [$player]] = questionShowing();
    at('12:00:20');
    clockTick();

    expect(fn () => app(SubmitAnswer::class)->handle($player, 1, 0, now()))
        ->toThrow(ValidationException::class, __('The game is paused until the screen is back.'));
});

test('when the screen is back the question carries on with the time that was left', function () {
    [$game, [$player]] = questionShowing();
    at('12:00:20');
    clockTick();

    // Paused for 40 seconds.
    at('12:01:00');
    heartbeatFromScreen($game);

    $game->refresh();
    $round = app(GameEngine::class)->currentRound($game);
    expect($game->isPaused())->toBeFalse()
        ->and($round->opens_at->format('H:i:s.v'))->toBe('12:00:47.000')
        ->and($round->ends_at->format('H:i:s.v'))->toBe('12:01:47.000')
        ->and($game->next_transition_at->format('H:i:s.v'))->toBe('12:01:47.300');

    // 13 s before the pause plus 1 s after it: the pause doesn't count.
    at('12:01:01');
    app(SubmitAnswer::class)->handle($player, 1, 0, now());
    expect($round->answers()->sole()->response_ms)->toBe(14000);
});

test('opening the room on the screen again also resumes the game', function () {
    [$game] = questionShowing();
    at('12:00:20');
    clockTick();

    $this->withCookie(GuestCookies::HOST, 'host-token')
        ->get(route('host.rooms.show', $game))
        ->assertOk()
        ->assertInertia(fn ($page) => $page->where('room.paused', false));
});

test('the room closes if the screen is not back within 2 minutes', function () {
    [$game] = questionShowing();
    at('12:00:20');
    clockTick();

    at('12:02:19.900');
    clockTick();
    expect($game->fresh()->status)->toBe(GameStatus::Question);

    at('12:02:20');
    clockTick();

    $game->refresh();
    expect($game->status)->toBe(GameStatus::Abandoned)
        ->and($game->isPaused())->toBeFalse()
        ->and($game->next_transition_at)->toBeNull();
});

test('the other phases pause and resume too', function () {
    [$game] = questionShowing();
    $this->withCookie(GuestCookies::HOST, 'host-token')->post(route('host.rooms.next', $game));
    expect($game->fresh()->status)->toBe(GameStatus::Reveal); // ends 12:00:14

    at('12:00:10');
    heartbeatFromScreen($game);
    at('12:00:13');
    Game::query()->update(['host_seen_at' => Carbon::parse('2026-10-14 12:00:02')]);
    clockTick();
    expect($game->fresh()->isPaused())->toBeTrue();

    at('12:00:43');
    heartbeatFromScreen($game);

    expect($game->fresh()->status)->toBe(GameStatus::Reveal)
        ->and($game->fresh()->next_transition_at->format('H:i:s.v'))->toBe('12:00:44.000');
});

describe('in the lobby', function () {
    beforeEach(function () {
        $this->room = Game::factory()->withHostToken('host-token')->create(['code' => 'ABCD', 'settings' => ['rounds' => 1]]);
        $question = Question::factory()->create();
        $this->room->categories()->attach($question->category);
        Player::factory()->for($this->room)->vip()->withToken('vip-token')->create();
        Player::factory()->for($this->room)->create();
    });

    test('the VIP cannot start while the screen is away', function () {
        at('12:00:11');

        $this->withCookie(GuestCookies::PLAYER, 'vip-token')
            ->post(route('play.start', $this->room))
            ->assertSessionHasErrors('start');

        expect($this->room->fresh()->status)->toBe(GameStatus::Lobby);
    });

    test('the room closes if the screen does not come back', function () {
        at('12:00:11');
        clockTick();
        expect($this->room->fresh()->isPaused())->toBeTrue();

        at('12:02:11');
        clockTick();

        expect($this->room->fresh()->status)->toBe(GameStatus::Abandoned);
    });

    test('the lobby is back to normal when the screen returns', function () {
        at('12:00:11');
        clockTick();

        heartbeatFromScreen($this->room);

        expect($this->room->fresh()->isPaused())->toBeFalse()
            ->and($this->room->fresh()->next_transition_at)->toBeNull();
    });
});
