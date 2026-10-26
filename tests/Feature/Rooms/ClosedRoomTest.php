<?php

use App\Actions\Rooms\CloseRoom;
use App\Events\RoomUpdated;
use App\Models\Game;
use App\Models\Player;
use App\Support\GuestCookies;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Inertia\Testing\AssertableInertia as Assert;

uses(RefreshDatabase::class);

beforeEach(function () {
    Event::fake([RoomUpdated::class]);

    $this->room = Game::factory()->withHostToken('host-token')->create(['code' => 'ABCD', 'locale' => 'el']);
    Player::factory()->for($this->room)->withToken('player-token')->create();
    app(CloseRoom::class)->handle($this->room);
});

test('the screen of a closed room still shows it, closed, instead of an error', function () {
    $this->withCookie(GuestCookies::HOST, 'host-token')
        ->get('/host/ABCD')
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page->component('host/Room')->where('room.status', 'abandoned'));
});

test('so does a phone in that room', function () {
    $this->withCookie(GuestCookies::PLAYER, 'player-token')
        ->get('/play/ABCD')
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page->component('play/Room')->where('room.status', 'abandoned'));
});

test('the screen still finds its own room after another room took the code', function () {
    Game::factory()->withHostToken('new-host')->create(['code' => 'ABCD']);

    $this->withCookie(GuestCookies::HOST, 'host-token')
        ->get('/host/ABCD')
        ->assertInertia(fn (Assert $page) => $page->component('host/Room')->where('room.status', 'abandoned'));
});

test('anyone else gets the join form, or a game-styled error page', function () {
    $this->get('/play/ABCD')->assertInertia(fn (Assert $page) => $page->component('play/Join'));

    $this->get('/host/ABCD')
        ->assertNotFound()
        ->assertInertia(fn (Assert $page) => $page->component('Error')->where('status', 404)->has('translations'));
});

test('error pages follow the room or the visitor\'s language', function () {
    $this->withCookie('quizly_locale', 'el')
        ->get('/host/ZZZZ')
        ->assertInertia(fn (Assert $page) => $page->component('Error')->where('locale', 'el'));
});

test('forbidden pages get the game-styled page too', function () {
    Game::factory()->withHostToken('other-host')->create(['code' => 'WXYZ']);

    $this->withCookie(GuestCookies::HOST, 'host-token')
        ->get('/host/WXYZ')
        ->assertForbidden()
        ->assertInertia(fn (Assert $page) => $page->component('Error')->where('status', 403));
});

test('opening an action\'s address directly gets the game-styled page', function () {
    $this->get('/play/ABCD/leave')
        ->assertMethodNotAllowed()
        ->assertInertia(fn (Assert $page) => $page->component('Error')->where('status', 405));
});

test('requests made with fetch keep their JSON errors', function () {
    $this->withCookie(GuestCookies::HOST, 'host-token')
        ->postJson('/host/ZZZZ/heartbeat')
        ->assertNotFound()
        ->assertJsonStructure(['message']);
});
