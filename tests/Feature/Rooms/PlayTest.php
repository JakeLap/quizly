<?php

use App\Enums\GameStatus;
use App\Events\RoomUpdated;
use App\Models\Game;
use App\Models\Player;
use App\Models\Question;
use App\Support\GuestCookies;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Event;
use Inertia\Testing\AssertableInertia as Assert;

uses(RefreshDatabase::class);

beforeEach(function () {
    Event::fake([RoomUpdated::class]);
});

test('a player can join with the room code and a nickname', function () {
    $room = Game::factory()->create(['code' => 'ABCD']);

    $response = $this->post(route('play.store'), ['code' => 'ABCD', 'nickname' => 'Maria']);

    $player = Player::sole();
    $response->assertRedirect(route('play.show', 'ABCD'))->assertCookie(GuestCookies::PLAYER);

    $cookie = collect($response->headers->getCookies())->firstWhere(fn ($c) => $c->getName() === GuestCookies::PLAYER);
    expect($cookie->getPath())->toBe('/play')
        ->and($player->game_id)->toBe($room->id)
        ->and($player->nickname)->toBe('Maria')
        ->and($player->is_vip)->toBeTrue()
        ->and($room->fresh()->state_version)->toBe(1);

    Event::assertDispatched(RoomUpdated::class);
});

test('the first player is the VIP and everyone gets a different colour', function () {
    Game::factory()->create(['code' => 'ABCD']);

    $this->post(route('play.store'), ['code' => 'ABCD', 'nickname' => 'Maria']);
    $this->post(route('play.store'), ['code' => 'ABCD', 'nickname' => 'Nikos']);

    [$first, $second] = Player::orderBy('id')->get();
    expect($first->is_vip)->toBeTrue()
        ->and($second->is_vip)->toBeFalse()
        ->and($first->color)->not->toBe($second->color);
});

test('codes and nicknames are tidied up', function () {
    Game::factory()->create(['code' => 'ABCD']);

    $this->post(route('play.store'), ['code' => ' ab cd ', 'nickname' => '  Kostas   the  Great '])
        ->assertRedirect(route('play.show', 'ABCD'));

    expect(Player::sole()->nickname)->toBe('Kostas the Great');
});

test('joining fails for an unknown, started or full room', function (Closure $setup, string $field) {
    $setup();

    $this->post(route('play.store'), ['code' => 'ABCD', 'nickname' => 'Maria'])
        ->assertSessionHasErrors($field);

    expect(Player::where('nickname', 'Maria')->exists())->toBeFalse();
})->with([
    'unknown room' => [fn () => null, 'code'],
    'expired room' => [fn () => Game::factory()->create(['code' => 'ABCD', 'expires_at' => now()->subMinute()]), 'code'],
    'already started' => [fn () => Game::factory()->status(GameStatus::Question)->create(['code' => 'ABCD']), 'code'],
    'full' => [fn () => Player::factory()->for(Game::factory()->create(['code' => 'ABCD', 'settings' => ['max_players' => 2]]))->count(2)->create(), 'code'],
]);

test('nicknames must be unique in the room regardless of case', function () {
    $room = Game::factory()->create(['code' => 'ABCD']);
    Player::factory()->for($room)->create(['nickname' => 'Maria']);

    $this->post(route('play.store'), ['code' => 'ABCD', 'nickname' => 'MARIA'])
        ->assertSessionHasErrors('nickname');
});

test('nicknames are validated', function (string $nickname) {
    Game::factory()->create(['code' => 'ABCD']);

    $this->post(route('play.store'), ['code' => 'ABCD', 'nickname' => $nickname])
        ->assertSessionHasErrors('nickname');
})->with(['', 'A', 'This nickname is far too long', '<script>', '-dash', 'emoji 🎉']);

test('greek nicknames are welcome', function () {
    Game::factory()->create(['code' => 'ABCD']);

    $this->post(route('play.store'), ['code' => 'ABCD', 'nickname' => 'Γιώργος_7'])->assertSessionHasNoErrors();

    expect(Player::sole()->nickname)->toBe('Γιώργος_7');
});

test('errors are shown in the room language', function (string $locale, string $message) {
    $room = Game::factory()->create(['code' => 'ABCD', 'locale' => $locale]);
    Player::factory()->for($room)->create(['nickname' => 'Maria']);

    $this->post(route('play.store'), ['code' => 'ABCD', 'nickname' => 'Maria'])
        ->assertSessionHasErrors(['nickname' => $message]);
})->with([
    ['el', 'Το ψευδώνυμο χρησιμοποιείται ήδη. Δοκίμασε άλλο.'],
    ['en', 'That nickname is taken. Try another one.'],
]);

test('joining twice from the same phone does not create a second player', function () {
    $room = Game::factory()->create(['code' => 'ABCD']);
    Player::factory()->for($room)->withToken('player-token')->create();

    $this->withCookie(GuestCookies::PLAYER, 'player-token')
        ->post(route('play.store'), ['code' => 'ABCD', 'nickname' => 'Someone'])
        ->assertRedirect(route('play.show', 'ABCD'));

    expect(Player::count())->toBe(1);
});

test('a player in the room sees the room on their phone', function () {
    $room = Game::factory()->create(['code' => 'ABCD']);
    $player = Player::factory()->for($room)->withToken('player-token')->create();

    $this->withCookie(GuestCookies::PLAYER, 'player-token')
        ->get('/play/ABCD')
        ->assertInertia(fn (Assert $page) => $page
            ->component('play/Room')
            ->where('me.id', $player->id)
            ->where('kicked', false)
            ->where('room.code', 'ABCD'));
});

test('anyone else gets the join form with the code filled in', function () {
    Game::factory()->create(['code' => 'ABCD']);

    $this->get('/play/ABCD')
        ->assertInertia(fn (Assert $page) => $page->component('play/Join')->where('code', 'ABCD'));
});

test('room links are case insensitive', function () {
    $this->get('/play/abcd')->assertRedirect('/play/ABCD');
    $this->get('/play/abc1')->assertNotFound();
});

test('a player can leave the lobby, handing over VIP', function () {
    $room = Game::factory()->create(['code' => 'ABCD']);
    $vip = Player::factory()->for($room)->vip()->withToken('player-token')->create();
    $other = Player::factory()->for($room)->create();

    $this->withCookie(GuestCookies::PLAYER, 'player-token')
        ->post(route('play.leave', $room))
        ->assertRedirect(route('play.create'));

    expect(Player::find($vip->id))->toBeNull()
        ->and($other->fresh()->is_vip)->toBeTrue();
});

test('the VIP can start the game from their phone', function () {
    $room = Game::factory()->create(['code' => 'ABCD', 'settings' => ['rounds' => 1]]);
    $question = Question::factory()->create();
    $room->categories()->attach($question->category);
    Player::factory()->for($room)->vip()->withToken('vip-token')->create();
    Player::factory()->for($room)->withToken('player-token')->create();

    $this->withCookie(GuestCookies::PLAYER, 'player-token')->post(route('play.start', $room))->assertForbidden();
    Auth::forgetGuards(); // the test client keeps the resolved player between requests
    $this->withCookie(GuestCookies::PLAYER, 'vip-token')->post(route('play.start', $room))->assertSessionHasNoErrors();

    expect($room->fresh()->status)->toBe(GameStatus::Intro);
});

test('players cannot act in rooms they are not in', function () {
    $room = Game::factory()->create(['code' => 'ABCD']);
    Player::factory()->withToken('player-token')->create();

    $this->withCookie(GuestCookies::PLAYER, 'player-token')->post(route('play.leave', $room))->assertForbidden();
    Auth::forgetGuards();
    $this->post(route('play.leave', $room))->assertForbidden();
});
