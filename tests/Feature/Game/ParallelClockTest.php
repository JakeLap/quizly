<?php

use App\Actions\Rooms\StartGame;
use App\Enums\GameStatus;
use App\Game\GameEngine;
use App\Models\Category;
use App\Models\Game;
use App\Models\Player;
use App\Models\Question;
use Illuminate\Database\Connection;
use Illuminate\Foundation\Testing\DatabaseTruncation;
use Illuminate\Support\Facades\DB;

// Row locks only matter between separate connections, and another connection
// can't see data inside a test's transaction, so this test commits its data.
uses(DatabaseTruncation::class);

beforeEach(function () {
    config(['database.connections.other_process' => config('database.connections.pgsql')]);

    // If the clock ever waited for a lock again, fail fast instead of hanging.
    DB::statement("SET lock_timeout = '2s'");
});

afterEach(function () {
    DB::connection('other_process')->rollBack();
    DB::purge('other_process');
    DB::statement('SET lock_timeout = 0');
});

function dueGame(): Game
{
    $game = Game::factory()->create(['settings' => ['rounds' => 1]]);
    $category = Category::factory()->create();
    Question::factory()->for($category)->create();
    $game->categories()->attach($category);
    Player::factory()->for($game)->count(2)->create();

    app(StartGame::class)->handle($game);
    test()->travelTo($game->fresh()->next_transition_at);

    return $game->fresh();
}

/** Another process (a second clock, or a request) holds the game's row lock. */
function lockElsewhere(Game $game): Connection
{
    $other = DB::connection('other_process');
    $other->beginTransaction();
    $other->select('select id from games where id = ? for update', [$game->id]);

    return $other;
}

test('the clock skips a game another process holds, without waiting', function () {
    $game = dueGame();
    $other = lockElsewhere($game);

    $started = microtime(true);
    $advanced = app(GameEngine::class)->advanceDue();

    expect($advanced)->toBe(0)
        ->and(microtime(true) - $started)->toBeLessThan(1.0)
        ->and($game->fresh()->status)->toBe(GameStatus::Intro);

    // Once the other process lets go, the next tick picks it up.
    $other->rollBack();

    expect(app(GameEngine::class)->advanceDue())->toBe(1)
        ->and($game->fresh()->status)->toBe(GameStatus::Question);
});

test('parallel clocks share the due games', function () {
    $first = dueGame();
    $second = dueGame();
    lockElsewhere($first);

    expect(app(GameEngine::class)->advanceDue())->toBe(1)
        ->and($first->fresh()->status)->toBe(GameStatus::Intro)
        ->and($second->fresh()->status)->toBe(GameStatus::Question);
});
