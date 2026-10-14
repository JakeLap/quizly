<?php

use App\Data\GameSettings;
use App\Models\Game;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('defaults come from the quizly config', function () {
    config([
        'quizly.rounds.default' => 7,
        'quizly.seconds_per_round.default' => 15,
        'quizly.players.default' => 6,
    ]);

    expect(GameSettings::defaults()->toArray())->toBe([
        'rounds' => 7,
        'seconds_per_round' => 15,
        'max_players' => 6,
    ]);
});

test('missing keys fall back to the defaults', function () {
    $settings = GameSettings::fromArray(['rounds' => 5]);

    expect($settings->rounds)->toBe(5)
        ->and($settings->secondsPerRound)->toBe(config('quizly.seconds_per_round.default'))
        ->and($settings->maxPlayers)->toBe(config('quizly.players.default'));
});

test('settings outside the allowed range are rejected', function (array $data) {
    GameSettings::fromArray($data);
})->throws(InvalidArgumentException::class)->with([
    'zero rounds' => [['rounds' => 0]],
    'too many rounds' => [['rounds' => 31]],
    'too little time' => [['seconds_per_round' => 4]],
    'too much time' => [['seconds_per_round' => 61]],
    'one player' => [['max_players' => 1]],
    'thirteen players' => [['max_players' => 13]],
]);

test('the game model stores and restores settings', function () {
    $game = Game::factory()->create([
        'settings' => new GameSettings(rounds: 12, secondsPerRound: 30, maxPlayers: 4),
    ]);

    $settings = $game->fresh()->settings;

    expect($settings)->toBeInstanceOf(GameSettings::class)
        ->and($settings->toArray())->toBe(['rounds' => 12, 'seconds_per_round' => 30, 'max_players' => 4]);
});

test('the game model accepts settings as an array', function () {
    $game = Game::factory()->create(['settings' => ['rounds' => 3, 'max_players' => 12]]);

    expect($game->fresh()->settings->rounds)->toBe(3)
        ->and($game->fresh()->settings->maxPlayers)->toBe(12);
});
