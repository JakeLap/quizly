<?php

use App\Enums\GameStatus;
use App\Models\Game;
use App\Models\Player;
use App\Support\GuestCookies;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Broadcast;
use Illuminate\Testing\TestResponse;

uses(RefreshDatabase::class);

beforeEach(function () {
    // Sign channel auth responses like Reverb would (no server needed),
    // and register the channels on that broadcaster.
    config([
        'broadcasting.default' => 'reverb',
        'broadcasting.connections.reverb.key' => 'test-key',
        'broadcasting.connections.reverb.secret' => 'test-secret',
        'broadcasting.connections.reverb.app_id' => 'test-app',
    ]);
    Broadcast::purge();
    require base_path('routes/channels.php');
});

function authorizeChannel(string $prefix, string $cookie, string $token, string $code): TestResponse
{
    return test()->withCredentials()->withCookie($cookie, $token)->postJson("/{$prefix}/broadcasting/auth", [
        'socket_id' => '1234.5678',
        'channel_name' => "presence-room.{$code}",
    ]);
}

test('players join the presence channel of their room', function () {
    $room = Game::factory()->create(['code' => 'ABCD']);
    $player = Player::factory()->for($room)->withToken('player-token')->create();

    $response = authorizeChannel('play', GuestCookies::PLAYER, 'player-token', 'ABCD')->assertOk();

    $member = json_decode($response->json('channel_data'), true);
    expect($member['user_id'])->toBe("player:{$player->id}")
        ->and($member['user_info'])->toBe(['id' => "player:{$player->id}", 'role' => 'player', 'playerId' => $player->id]);
});

test('the host joins the presence channel of their room', function () {
    $room = Game::factory()->withHostToken('host-token')->create(['code' => 'ABCD']);

    $response = authorizeChannel('host', GuestCookies::HOST, 'host-token', 'ABCD')->assertOk();

    expect(json_decode($response->json('channel_data'), true)['user_id'])->toBe("host:{$room->id}");
});

test('outsiders cannot join a room channel', function (Closure $setup, string $prefix, string $cookie) {
    $setup();

    authorizeChannel($prefix, $cookie, 'token', 'ABCD')->assertForbidden();
})->with([
    'no cookie' => [fn () => Game::factory()->create(['code' => 'ABCD']), 'play', 'none'],
    'player of another room' => [function () {
        Game::factory()->create(['code' => 'ABCD']);
        Player::factory()->withToken('token')->create();
    }, 'play', GuestCookies::PLAYER],
    'kicked player' => [fn () => Player::factory()->for(Game::factory()->create(['code' => 'ABCD']))->withToken('token')->create(['kicked_at' => now()]), 'play', GuestCookies::PLAYER],
    'host of another room' => [function () {
        Game::factory()->create(['code' => 'ABCD']);
        Game::factory()->withHostToken('token')->create();
    }, 'host', GuestCookies::HOST],
    'host of a closed room' => [fn () => Game::factory()->status(GameStatus::Abandoned)->withHostToken('token')->create(['code' => 'ABCD']), 'host', GuestCookies::HOST],
]);
