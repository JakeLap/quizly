<?php

use App\Actions\Game\SubmitAnswer;
use App\Actions\Rooms\StartGame;
use App\Enums\GameStatus;
use App\Events\RoomUpdated;
use App\Game\GameEngine;
use App\Game\RoomPresence;
use App\Models\Category;
use App\Models\Game;
use App\Models\Player;
use App\Models\Question;
use App\Models\Round;
use App\Support\GuestCookies;
use Illuminate\Broadcasting\Broadcasters\PusherBroadcaster;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Broadcast;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Exceptions;
use Illuminate\Validation\ValidationException;
use Pusher\Pusher;

uses(RefreshDatabase::class);

beforeEach(function () {
    Event::fake([RoomUpdated::class]);
    $this->travelTo(Carbon::parse('2026-10-14 12:00:00.000'));
});

/**
 * A game showing its first question, with three players.
 *
 * @return array{0: Game, 1: Round, 2: list<Player>}
 */
function questionOpen(): array
{
    $game = Game::factory()->withHostToken('host-token')->create(['code' => 'ABCD', 'settings' => ['rounds' => 2, 'seconds_per_round' => 10]]);
    $category = Category::factory()->create();
    Question::factory()->for($category)->count(2)->create();
    $game->categories()->attach($category);

    $players = [];
    foreach (range(1, 3) as $i) {
        $players[] = Player::factory()->for($game)->withToken("player-{$i}")->create();
    }

    app(StartGame::class)->handle($game);
    test()->travelTo($game->fresh()->next_transition_at);
    app(GameEngine::class)->advanceDue();

    $round = app(GameEngine::class)->currentRound($game->fresh());
    test()->travelTo($round->opens_at->addSecond());
    Game::query()->update(['host_seen_at' => now()]); // the host screen is still there

    return [$game->fresh(), $round, $players];
}

/** Reverb reports these players as connected (null: Reverb can't be asked). */
function connected(?array $players): void
{
    test()->mock(RoomPresence::class)
        ->shouldReceive('connectedPlayerIds')
        ->andReturn($players === null ? null : array_map(fn (Player $player) => $player->id, $players));
}

function answerNow(Player $player, int $slot = 0): void
{
    app(SubmitAnswer::class)->handle($player, 1, $slot, now());
}

describe('ending a question early', function () {
    test('it ends once every connected player has answered', function () {
        [$game, , [$first, $second, $gone]] = questionOpen();
        connected([$first, $second]);

        answerNow($first);
        expect($game->fresh()->status)->toBe(GameStatus::Question);

        answerNow($second);
        expect($game->fresh()->status)->toBe(GameStatus::Reveal);
    });

    test('it waits for players who are still connected', function () {
        [$game, , [$first]] = questionOpen();
        connected(array_slice(Player::all()->all(), 0, 3));

        answerNow($first);

        expect($game->fresh()->status)->toBe(GameStatus::Question);
    });

    test('without presence information it waits for everyone in the room', function () {
        [$game, , [$first, $second]] = questionOpen();
        connected(null);

        answerNow($first);
        answerNow($second);

        expect($game->fresh()->status)->toBe(GameStatus::Question);
    });

    test('it never ends before anyone has answered', function () {
        [$game] = questionOpen();
        connected([]);

        app(GameEngine::class)->endQuestionIfEveryoneAnswered($game);

        expect($game->fresh()->status)->toBe(GameStatus::Question);
    });

    test('the host screen reports a player dropping out', function () {
        [$game, , [$first, $second]] = questionOpen();
        connected([$first, $second, Player::all()[2]]);
        answerNow($first);
        answerNow($second);
        expect($game->fresh()->status)->toBe(GameStatus::Question);

        // The third player's phone disconnects and the TV notices.
        connected([$first, $second]);
        $this->withCookie(GuestCookies::HOST, 'host-token')
            ->post(route('host.rooms.presence', $game))
            ->assertNoContent();

        expect($game->fresh()->status)->toBe(GameStatus::Reveal);
    });

    test('only the host can report presence changes', function () {
        [$game] = questionOpen();

        $this->withCookie(GuestCookies::PLAYER, 'player-1')
            ->post(route('host.rooms.presence', $game))
            ->assertForbidden();
    });
});

describe('answer retries', function () {
    test('sending the same answer again is accepted once', function () {
        [, $round, [$player]] = questionOpen();
        connected(null);

        answerNow($player, 2);
        answerNow($player, 2);

        expect($round->answers()->count())->toBe(1);
    });

    test('a retry arriving after the deadline still succeeds', function () {
        [, $round, [$player]] = questionOpen();
        connected(null);
        answerNow($player, 2);

        $this->travelTo($round->ends_at->addSeconds(5));
        answerNow($player, 2);

        expect($round->answers()->sole()->response_ms)->toBe(1000);
    });

    test('changing the answer is not allowed', function () {
        [, , [$player]] = questionOpen();
        connected(null);
        answerNow($player, 2);

        expect(fn () => answerNow($player, 1))->toThrow(ValidationException::class, 'You have already answered.');
    });
});

describe('asking Reverb who is connected', function () {
    function fakeReverb(Closure $users): void
    {
        $pusher = Mockery::mock(Pusher::class);
        $pusher->shouldReceive('getPresenceUsers')->with('presence-room.ABCD')->andReturnUsing($users);

        Broadcast::extend('fake-reverb', fn () => new PusherBroadcaster($pusher));
        config(['broadcasting.connections.fake-reverb' => ['driver' => 'fake-reverb'], 'broadcasting.default' => 'fake-reverb']);
        Broadcast::purge();
    }

    test('it lists the connected players, not the host', function () {
        fakeReverb(fn () => (object) ['users' => [(object) ['id' => 'player:5'], (object) ['id' => 'host:1'], (object) ['id' => 'player:9']]]);

        expect(app(RoomPresence::class)->connectedPlayerIds(Game::factory()->make(['code' => 'ABCD'])))->toBe([5, 9]);
    });

    test('it reports and gives up when Reverb cannot be reached', function () {
        Exceptions::fake();
        fakeReverb(fn () => throw new RuntimeException('Reverb is down.'));

        expect(app(RoomPresence::class)->connectedPlayerIds(Game::factory()->make(['code' => 'ABCD'])))->toBeNull();
        Exceptions::assertReported(RuntimeException::class);
    });

    test('it does not know without Reverb', function () {
        expect(app(RoomPresence::class)->connectedPlayerIds(Game::factory()->make()))->toBeNull();
    });

    test('Reverb requests time out quickly', function () {
        expect(config('broadcasting.connections.reverb.options.timeout'))->toBeLessThanOrEqual(2.0)
            ->and(config('broadcasting.connections.reverb.client_options.connect_timeout'))->toBeLessThanOrEqual(1);
    });
});
