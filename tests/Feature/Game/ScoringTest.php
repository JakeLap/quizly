<?php

use App\Game\ScoreCalculator;
use App\Game\Standings;
use App\Models\Answer;
use App\Models\Game;
use App\Models\Player;
use App\Models\Round;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('points fall from 1000 to 500 over the answer time', function (bool $correct, int $ms, int $points) {
    expect((new ScoreCalculator)->points($correct, $ms, 20000))->toBe($points);
})->with([
    'instant' => [true, 0, 1000],
    'halfway' => [true, 10000, 750],
    'at the deadline' => [true, 20000, 500],
    'after the deadline' => [true, 25000, 500],
    'wrong' => [false, 100, 0],
]);

test('equal scores are ranked by total time on correct answers', function () {
    $game = Game::factory()->create();
    $round = Round::factory()->for($game)->create(['status' => 'revealed']);
    $slow = Player::factory()->for($game)->create(['score' => 900, 'nickname' => 'Alpha']);
    $fast = Player::factory()->for($game)->create(['score' => 900, 'nickname' => 'Beta']);
    $last = Player::factory()->for($game)->create(['score' => 100]);
    Answer::factory()->for($round)->for($slow)->create(['is_correct' => true, 'response_ms' => 3000]);
    Answer::factory()->for($round)->for($fast)->create(['is_correct' => true, 'response_ms' => 1000]);

    $standings = Standings::for($game);

    expect(array_column($standings, 'playerId'))->toBe([$fast->id, $slow->id, $last->id])
        ->and(array_column($standings, 'rank'))->toBe([1, 2, 3]);
});

test('identical results share a rank', function () {
    $game = Game::factory()->create();
    Player::factory()->for($game)->count(2)->create(['score' => 0]);

    expect(array_column(Standings::for($game), 'rank'))->toBe([1, 1]);
});
