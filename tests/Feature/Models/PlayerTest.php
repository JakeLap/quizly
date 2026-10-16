<?php

use App\Models\Game;
use App\Models\Player;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('nicknames are unique within a room regardless of case', function () {
    $game = Game::factory()->create();
    Player::factory()->for($game)->create(['nickname' => 'Nikos']);

    Player::factory()->for($game)->create(['nickname' => 'nIKOS']);
})->throws(UniqueConstraintViolationException::class);

test('the same nickname can be used in different rooms', function () {
    Player::factory()->create(['nickname' => 'Nikos']);
    $player = Player::factory()->create(['nickname' => 'Nikos']);

    expect($player->exists)->toBeTrue();
});

test('a player is identified by the plain token while only its hash is stored', function () {
    $player = Player::factory()->withToken('secret-player-token')->create();

    expect(Player::findByToken('secret-player-token')?->id)->toBe($player->id)
        ->and(Player::findByToken('wrong'))->toBeNull()
        ->and(Player::findByToken(null))->toBeNull()
        ->and($player->toArray())->not->toHaveKey('token_hash');
});

test('kicked players are no longer participating', function () {
    $game = Game::factory()->create();
    Player::factory()->for($game)->create();
    Player::factory()->for($game)->create(['kicked_at' => now()]);

    expect($game->players()->participating()->count())->toBe(1);
});
