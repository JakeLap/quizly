<?php

use App\Actions\Rooms\StartGame;
use App\Console\Commands\GameClockCommand;
use App\Enums\GameStatus;
use App\Game\GameEngine;
use App\Game\QuestionPicker;
use App\Models\Category;
use App\Models\Game;
use App\Models\Player;
use App\Models\Question;
use App\Support\GuestCookies;
use Illuminate\Broadcasting\Broadcasters\Broadcaster;
use Illuminate\Broadcasting\BroadcastException;
use Illuminate\Contracts\Database\LostConnectionDetector;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Broadcast;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Exceptions;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->travelTo(Carbon::parse('2026-10-14 12:00:00.000'));
    Exceptions::fake();
});

/** A game in its intro, with two players and enough questions. */
function gameInIntro(): Game
{
    $game = Game::factory()->create(['code' => 'ABCD', 'settings' => ['rounds' => 3, 'seconds_per_round' => 10]]);
    $category = Category::factory()->create();
    Question::factory()->for($category)->count(3)->create();
    $game->categories()->attach($category);
    Player::factory()->for($game)->withToken('player-1')->create();
    Player::factory()->for($game)->create();

    app(StartGame::class)->handle($game);

    return $game->fresh();
}

/** Broadcasting fails as if Reverb were down. */
function breakBroadcasting(): void
{
    Broadcast::extend('failing', fn () => new class extends Broadcaster
    {
        public function auth($request): mixed
        {
            return null;
        }

        public function validAuthenticationResponse($request, $result): mixed
        {
            return null;
        }

        public function broadcast(array $channels, $event, array $payload = []): void
        {
            throw new BroadcastException('Reverb is down.');
        }
    });

    config([
        'broadcasting.connections.failing' => ['driver' => 'failing'],
        'broadcasting.default' => 'failing',
    ]);
    Broadcast::purge();
}

/** The game's next phase change fails until fixed. */
function breakTransitions(): void
{
    test()->mock(QuestionPicker::class)->shouldReceive('pick')->andThrow(new RuntimeException('Something broke.'));
}

function tickAt(Game $game): Game
{
    test()->travelTo($game->fresh()->next_transition_at);
    Game::query()->update(['host_seen_at' => now()]); // the host screen is still there
    app(GameEngine::class)->advanceDue();

    return $game->fresh();
}

describe('when broadcasting fails', function () {
    beforeEach(fn () => breakBroadcasting());

    test('the game still starts and the failure is reported', function () {
        $game = gameInIntro();

        expect($game->status)->toBe(GameStatus::Intro)
            ->and($game->next_transition_at->format('H:i:s.v'))->toBe('12:00:04.000');
        Exceptions::assertReported(BroadcastException::class);
    });

    test('the clock keeps the timing of the new phase', function () {
        $game = tickAt(gameInIntro());

        // The question is open for its full time; the failed broadcast
        // must not be treated as a failed transition.
        expect($game->status)->toBe(GameStatus::Question)
            ->and($game->next_transition_at->format('H:i:s.v'))->toBe('12:00:17.300')
            ->and(Cache::has("quizly:game:{$game->id}:advance-failures"))->toBeFalse();
    });

    test('players can still answer', function () {
        $game = tickAt(gameInIntro());
        $this->travelTo(app(GameEngine::class)->currentRound($game)->opens_at->addSecond());

        $this->withCookie(GuestCookies::PLAYER, 'player-1')
            ->post(route('play.answer', $game), ['round' => 1, 'slot' => 0])
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        expect($game->rounds()->sole()->answers()->count())->toBe(1);
    });
});

describe('when a game keeps failing to advance', function () {
    test('it is retried a little later', function () {
        $game = gameInIntro();
        breakTransitions();

        $game = tickAt($game);

        expect($game->status)->toBe(GameStatus::Intro)
            ->and($game->next_transition_at->format('H:i:s.v'))->toBe('12:00:09.000');
    });

    test('it is abandoned after three attempts', function () {
        $game = gameInIntro();
        breakTransitions();

        $game = tickAt($game);
        $game = tickAt($game);
        expect($game->status)->toBe(GameStatus::Intro);

        $game = tickAt($game);

        expect($game->status)->toBe(GameStatus::Abandoned)
            ->and($game->finished_at)->not->toBeNull()
            ->and($game->next_transition_at)->toBeNull();
    });

    test('a success clears earlier failures', function () {
        $game = gameInIntro();
        breakTransitions();
        $game = tickAt($game);
        $game = tickAt($game);

        // Fixed: the real question picker is back.
        $this->instance(QuestionPicker::class, new QuestionPicker);
        $game = tickAt($game);

        expect($game->status)->toBe(GameStatus::Question)
            ->and(Cache::has("quizly:game:{$game->id}:advance-failures"))->toBeFalse();
    });

    test('one broken game does not hold up the others', function () {
        $broken = gameInIntro();
        $healthy = Game::factory()->create(['settings' => ['rounds' => 1]]);
        $healthy->categories()->attach(Category::factory()->create());
        Question::factory()->for($healthy->categories()->first())->create();
        Player::factory()->for($healthy)->count(2)->create();
        app(StartGame::class)->handle($healthy);

        $picker = $this->mock(QuestionPicker::class);
        $picker->shouldReceive('pick')->andReturnUsing(fn (Game $game) => $game->is($broken)
            ? throw new RuntimeException('Something broke.')
            : (new QuestionPicker)->pick($game));

        tickAt($broken);

        expect($broken->fresh()->status)->toBe(GameStatus::Intro)
            ->and($healthy->fresh()->status)->toBe(GameStatus::Question);
    });
});

describe('when the database is unreachable', function () {
    test('games are not blamed for it', function () {
        $game = gameInIntro();
        breakTransitions();
        $this->mock(LostConnectionDetector::class)->shouldReceive('causedByLostConnection')->andReturnTrue();
        $this->travelTo($game->next_transition_at);

        expect(fn () => app(GameEngine::class)->advanceDue())->toThrow(RuntimeException::class);

        expect($game->fresh()->next_transition_at->format('H:i:s.v'))->toBe('12:00:04.000')
            ->and(Cache::has("quizly:game:{$game->id}:advance-failures"))->toBeFalse();
    });

    test('the clock reports the error instead of crashing', function () {
        $this->mock(GameEngine::class)->shouldReceive('advanceDue')->andThrow(new RuntimeException('Database is down.'));

        $this->artisan('quizly:clock', ['--once' => true])->assertFailed();

        Exceptions::assertReported(fn (RuntimeException $e) => $e->getMessage() === 'Database is down.');
    });

    test('the clock backs off while checks keep failing', function (int $failures, int $delay) {
        expect((new GameClockCommand)->delay(100, $failures))->toBe($delay);
    })->with([
        'healthy' => [0, 100],
        'first failure' => [1, 200],
        'second failure' => [2, 400],
        'fifth failure' => [5, 3200],
        'capped' => [9, 5000],
    ]);
});
