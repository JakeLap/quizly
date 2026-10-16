<?php

use App\Enums\GameStatus;
use App\Events\RoomUpdated;
use App\Game\RoomPresence;
use App\Models\Game;
use App\Models\Player;
use App\Support\GuestCookies;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;

uses(RefreshDatabase::class);

beforeEach(function () {
    Event::fake([RoomUpdated::class]);

    $this->room = Game::factory()->withHostToken('host-token')->create();

    // Both joined a while ago, so they've had time to connect.
    $this->travelTo(now()->subMinute());
    $this->vip = Player::factory()->for($this->room)->vip()->create(['nickname' => 'father']);
    $this->other = Player::factory()->for($this->room)->create();
    $this->travelBack();
});

/** Reverb reports these player ids as connected (null: it can't be asked). */
function presenceIs(?array $ids): void
{
    test()->mock(RoomPresence::class)->shouldReceive('connectedPlayerIds')->andReturn($ids);
}

function reportGone(Game $room, array $playerIds): void
{
    test()->withCredentials()->withCookie(GuestCookies::HOST, 'host-token')
        ->postJson(route('host.rooms.presence', $room), ['gone' => $playerIds])
        ->assertNoContent();
}

test('a player who closed the tab is removed from the lobby and VIP passes on', function () {
    presenceIs([$this->other->id]);

    reportGone($this->room, [$this->vip->id]);

    expect(Player::find($this->vip->id))->toBeNull()
        ->and($this->other->fresh()->is_vip)->toBeTrue();
    Event::assertDispatched(RoomUpdated::class);
});

test('their nickname is free again', function () {
    presenceIs([$this->other->id]);
    reportGone($this->room, [$this->vip->id]);

    expect(Player::factory()->for($this->room)->create(['nickname' => 'father'])->exists)->toBeTrue();
});

test('a player who came back in the meantime stays', function () {
    presenceIs([$this->vip->id, $this->other->id]);

    reportGone($this->room, [$this->vip->id]);

    expect($this->vip->fresh())->not->toBeNull();
});

test('only the players the screen saw gone for the grace period are removed', function () {
    // Both are offline right now, but the other one is only refreshing.
    presenceIs([]);

    reportGone($this->room, [$this->vip->id]);

    expect(Player::find($this->vip->id))->toBeNull()
        ->and($this->other->fresh())->not->toBeNull();
});

test('players who only just joined are given time to connect', function () {
    $newcomer = Player::factory()->for($this->room)->create();
    presenceIs([]);

    reportGone($this->room, [$newcomer->id]);

    expect($newcomer->fresh())->not->toBeNull();
});

test('nobody is removed when Reverb cannot be asked', function () {
    presenceIs(null);

    reportGone($this->room, [$this->vip->id]);

    expect($this->vip->fresh())->not->toBeNull();
});

test('players are kept once the game has started', function () {
    $this->room->forceFill(['status' => GameStatus::Question])->save();
    presenceIs([]);

    reportGone($this->room, [$this->vip->id]);

    expect($this->vip->fresh())->not->toBeNull();
});

test('the screen can only report players by id', function () {
    $this->withCredentials()->withCookie(GuestCookies::HOST, 'host-token')
        ->postJson(route('host.rooms.presence', $this->room), ['gone' => ['everyone']])
        ->assertUnprocessable();
});
