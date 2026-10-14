<?php

use App\Enums\GameStatus;
use App\Events\RoomUpdated;
use App\Models\Game;
use App\Models\Player;
use App\Models\Round;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;

uses(RefreshDatabase::class);

beforeEach(fn () => Event::fake([RoomUpdated::class]));

test('expired rooms are closed', function () {
    $expired = Game::factory()->status(GameStatus::Question)->create(['expires_at' => now()->subMinute()]);
    $current = Game::factory()->status(GameStatus::Question)->create();

    $this->artisan('quizly:expire-rooms')->assertSuccessful();

    expect($expired->fresh()->status)->toBe(GameStatus::Abandoned)
        ->and($current->fresh()->status)->toBe(GameStatus::Question);
    Event::assertDispatched(RoomUpdated::class, fn (RoomUpdated $event) => $event->game->is($expired));
});

test('lobbies nobody joined are closed after a while', function () {
    $this->travelTo(now()->subMinutes(31));
    $empty = Game::factory()->create();
    $kickedOnly = Game::factory()->create();
    Player::factory()->for($kickedOnly)->create(['kicked_at' => now()]);
    $waiting = Game::factory()->create();
    Player::factory()->for($waiting)->create();
    $this->travelBack();
    $fresh = Game::factory()->create();

    $this->artisan('quizly:expire-rooms')->assertSuccessful();

    expect($empty->fresh()->status)->toBe(GameStatus::Abandoned)
        ->and($kickedOnly->fresh()->status)->toBe(GameStatus::Abandoned)
        ->and($waiting->fresh()->status)->toBe(GameStatus::Lobby)
        ->and($fresh->fresh()->status)->toBe(GameStatus::Lobby);
});

test('old finished and closed rooms are deleted with their players and rounds', function () {
    $this->travelTo(now()->subDays(8));
    $old = Game::factory()->status(GameStatus::Finished)->create();
    Player::factory()->for($old)->create();
    Round::factory()->for($old)->create();
    $oldClosed = Game::factory()->status(GameStatus::Abandoned)->create();
    $stillRunning = Game::factory()->status(GameStatus::Question)->create();
    $this->travelBack();
    $recent = Game::factory()->status(GameStatus::Finished)->create();

    $this->artisan('model:prune', ['--model' => [Game::class]])->assertSuccessful();

    expect(Game::find($old->id))->toBeNull()
        ->and(Game::find($oldClosed->id))->toBeNull()
        ->and(Player::where('game_id', $old->id)->exists())->toBeFalse()
        ->and(Round::where('game_id', $old->id)->exists())->toBeFalse()
        ->and(Game::find($stillRunning->id))->not->toBeNull()
        ->and(Game::find($recent->id))->not->toBeNull();
});

test('the housekeeping is scheduled', function () {
    $this->artisan('schedule:list')
        ->expectsOutputToContain('quizly:expire-rooms')
        ->expectsOutputToContain('model:prune');
});
