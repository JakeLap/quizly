<?php

use App\Events\RoomUpdated;
use App\Http\Middleware\SetLocale;
use App\Models\Game;
use App\Models\Player;
use App\Support\GuestCookies;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Inertia\Testing\AssertableInertia as Assert;

uses(RefreshDatabase::class);

test('picking a language remembers it', function () {
    $response = $this->from('/')->post(route('locale.update'), ['locale' => 'en']);

    $response->assertRedirect('/')->assertCookie(SetLocale::COOKIE, 'en');
    expect(collect($response->headers->getCookies())->firstWhere(fn ($cookie) => $cookie->getName() === SetLocale::COOKIE)->getExpiresTime())
        ->toBeGreaterThan(now()->addYear()->getTimestamp());
});

test('only supported languages can be picked', function () {
    $this->from('/')->post(route('locale.update'), ['locale' => 'de'])->assertSessionHasErrors('locale');
});

test('the picked language wins over the browser’s', function () {
    $this->withCookie(SetLocale::COOKIE, 'en')
        ->withHeader('Accept-Language', 'el-GR,el;q=0.9')
        ->get('/')
        ->assertInertia(fn (Assert $page) => $page->where('locale', 'en')->where('translations', []));

    $this->withCookie(SetLocale::COOKIE, 'el')
        ->withHeader('Accept-Language', 'en-US,en;q=0.9')
        ->get('/play')
        ->assertInertia(fn (Assert $page) => $page->where('locale', 'el')->where('translations.Join', 'Είσοδος'));
});

test('without a pick, the browser’s language is used', function () {
    $this->withHeader('Accept-Language', 'en-US,en;q=0.9')
        ->get('/')
        ->assertInertia(fn (Assert $page) => $page->where('locale', 'en'));
});

test('a room created after picking a language is played in it', function () {
    $this->withCookie(SetLocale::COOKIE, 'en')->post(route('host.rooms.store'));

    expect(Game::sole()->locale)->toBe('en');
});

test('inside a room everyone sees the room’s language', function () {
    Event::fake([RoomUpdated::class]);
    $room = Game::factory()->create(['code' => 'ABCD', 'locale' => 'el']);
    Player::factory()->for($room)->withToken('player-token')->create();

    $this->withCookie(SetLocale::COOKIE, 'en')
        ->withCookie(GuestCookies::PLAYER, 'player-token')
        ->get('/play/ABCD')
        ->assertInertia(fn (Assert $page) => $page->component('play/Room')->where('locale', 'el'));
});
