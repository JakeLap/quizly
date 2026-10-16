<?php

use App\Enums\GameStatus;
use App\Models\Category;
use App\Models\Game;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('two active rooms cannot share a code', function () {
    Game::factory()->create(['code' => 'ABCD']);

    Game::factory()->status(GameStatus::Question)->create(['code' => 'ABCD']);
})->throws(UniqueConstraintViolationException::class);

test('the code of a finished or abandoned room can be reused', function (GameStatus $status) {
    Game::factory()->status($status)->create(['code' => 'ABCD']);

    $game = Game::factory()->create(['code' => 'ABCD']);

    expect($game->exists)->toBeTrue();
})->with([GameStatus::Finished, GameStatus::Abandoned]);

test('active rooms are found by code regardless of case', function () {
    Game::factory()->status(GameStatus::Finished)->create(['code' => 'ABCD']);
    $active = Game::factory()->create(['code' => 'ABCD']);

    expect(Game::findActiveByCode('abcd')?->id)->toBe($active->id)
        ->and(Game::findActiveByCode('ZZZZ'))->toBeNull();
});

test('the host is identified by the plain token while only its hash is stored', function () {
    $game = Game::factory()->withHostToken('secret-host-token')->create();

    expect(Game::findByHostToken('secret-host-token')?->id)->toBe($game->id)
        ->and(Game::findByHostToken('wrong'))->toBeNull()
        ->and(Game::findByHostToken(null))->toBeNull()
        ->and(Game::findByHostToken(''))->toBeNull()
        ->and($game->host_token_hash)->not->toBe('secret-host-token')
        ->and($game->toArray())->not->toHaveKey('host_token_hash');
});

test('a game has the categories the host picked', function () {
    $game = Game::factory()->create();
    $categories = Category::factory()->count(3)->create();

    $game->categories()->attach($categories);

    expect($game->categories)->toHaveCount(3);
});
