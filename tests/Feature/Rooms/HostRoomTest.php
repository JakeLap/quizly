<?php

use App\Data\GameSettings;
use App\Enums\GameStatus;
use App\Events\RoomUpdated;
use App\Models\Category;
use App\Models\Game;
use App\Models\Player;
use App\Models\Question;
use App\Support\GuestCookies;
use App\Support\RoomCode;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Event;
use Inertia\Testing\AssertableInertia as Assert;

uses(RefreshDatabase::class);

beforeEach(function () {
    Event::fake([RoomUpdated::class]);
});

function hostedRoom(array $attributes = []): Game
{
    return Game::factory()->withHostToken('host-token')->create($attributes);
}

function asHost(): mixed
{
    return test()->withCookie(GuestCookies::HOST, 'host-token');
}

test('a host can open a room', function () {
    $withQuestions = Category::factory()->create();
    Question::factory()->for($withQuestions)->create();
    $empty = Category::factory()->create();

    $response = $this->post(route('host.rooms.store'));

    $game = Game::sole();
    $response->assertRedirect(route('host.rooms.show', $game));
    $response->assertCookie(GuestCookies::HOST);

    $cookie = collect($response->headers->getCookies())->firstWhere(fn ($c) => $c->getName() === GuestCookies::HOST);
    expect($cookie->getPath())->toBe('/host')
        ->and($cookie->isHttpOnly())->toBeTrue()
        ->and(RoomCode::normalize($game->code))->toBe($game->code)
        ->and($game->status)->toBe(GameStatus::Lobby)
        ->and($game->categories->pluck('id')->all())->toBe([$withQuestions->id])
        ->and($game->categories->pluck('id'))->not->toContain($empty->id);
});

test('the host sees the lobby', function () {
    $room = hostedRoom();
    Player::factory()->for($room)->create(['nickname' => 'Maria']);

    asHost()->get(route('host.rooms.show', $room))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('host/Room')
            ->where('room.code', $room->code)
            ->where('room.players.0.nickname', 'Maria')
            ->where('joinUrl', route('play.show', $room->code))
            ->has('categoryOptions'));
});

test('only the host can see or run the room', function () {
    $room = hostedRoom();
    Game::factory()->withHostToken('other-host')->create();

    $this->get(route('host.rooms.show', $room))->assertForbidden();
    Auth::forgetGuards();
    $this->withCookie(GuestCookies::HOST, 'other-host')->get(route('host.rooms.show', $room))->assertForbidden();
    $this->withCookie(GuestCookies::HOST, 'other-host')->post(route('host.rooms.start', $room))->assertForbidden();
});

test('unknown or finished rooms are not found', function () {
    hostedRoom(['code' => 'ABCD', 'status' => GameStatus::Finished]);

    asHost()->get('/host/ABCD')->assertNotFound();
    asHost()->get('/host/ZZZZ')->assertNotFound();
});

test('the host can change the settings', function () {
    $room = hostedRoom(['locale' => 'el']);
    $categories = Category::factory()->count(2)->create();

    asHost()->patch(route('host.rooms.update', $room), [
        'rounds' => 5,
        'seconds_per_round' => 30,
        'max_players' => 4,
        'locale' => 'en',
        'categories' => $categories->pluck('id')->all(),
    ])->assertRedirect()->assertSessionHasNoErrors();

    $room->refresh();
    expect($room->settings)->toEqual(new GameSettings(5, 30, 4))
        ->and($room->locale)->toBe('en')
        ->and($room->categories->pluck('id')->sort()->values()->all())->toBe($categories->pluck('id')->sort()->values()->all())
        ->and($room->state_version)->toBe(1);

    Event::assertDispatched(RoomUpdated::class, fn (RoomUpdated $event) => $event->game->is($room));
});

test('invalid settings are rejected', function (array $overrides, string $field) {
    $room = hostedRoom();
    $category = Category::factory()->create();
    $inactive = Category::factory()->inactive()->create();

    $data = array_merge([
        'rounds' => 10,
        'seconds_per_round' => 20,
        'max_players' => 8,
        'locale' => 'el',
        'categories' => [$category->id],
    ], $overrides);

    if (($overrides['categories'] ?? null) === 'inactive') {
        $data['categories'] = [$inactive->id];
    }

    asHost()->patch(route('host.rooms.update', $room), $data)->assertSessionHasErrors($field);
})->with([
    'too many rounds' => [['rounds' => 31], 'rounds'],
    'too little time' => [['seconds_per_round' => 2], 'seconds_per_round'],
    'too many players' => [['max_players' => 13], 'max_players'],
    'unsupported language' => [['locale' => 'de'], 'locale'],
    'no categories' => [['categories' => []], 'categories'],
    'inactive category' => [['categories' => 'inactive'], 'categories.0'],
]);

test('the player limit cannot go below the players already in the room', function () {
    $room = hostedRoom();
    Player::factory()->for($room)->count(3)->create();

    asHost()->patch(route('host.rooms.update', $room), [
        'rounds' => 10,
        'seconds_per_round' => 20,
        'max_players' => 2,
        'locale' => 'el',
        'categories' => [Category::factory()->create()->id],
    ])->assertSessionHasErrors('max_players');
});

test('the host can remove a player, freeing their nickname', function () {
    $room = hostedRoom();
    $vip = Player::factory()->for($room)->vip()->create(['nickname' => 'Nikos']);
    $next = Player::factory()->for($room)->create();

    asHost()->post(route('host.rooms.kick', [$room, $vip]))->assertRedirect();

    expect($vip->fresh()->kicked_at)->not->toBeNull()
        ->and($vip->fresh()->is_vip)->toBeFalse()
        ->and($next->fresh()->is_vip)->toBeTrue()
        ->and(Player::factory()->for($room)->create(['nickname' => 'nikos'])->exists)->toBeTrue();
});

test('the host cannot remove players of another room', function () {
    $room = hostedRoom();
    $stranger = Player::factory()->create();

    asHost()->post(route('host.rooms.kick', [$room, $stranger]))->assertNotFound();
});

test('the game starts once there are enough players and questions', function () {
    $room = hostedRoom(['settings' => ['rounds' => 2]]);
    $category = Category::factory()->create();
    Question::factory()->for($category)->count(2)->create();
    $room->categories()->attach($category);
    Player::factory()->for($room)->count(2)->create();

    asHost()->post(route('host.rooms.start', $room))->assertRedirect()->assertSessionHasNoErrors();

    expect($room->fresh()->status)->toBe(GameStatus::Intro)
        ->and($room->fresh()->started_at)->not->toBeNull();
    Event::assertDispatched(RoomUpdated::class);
});

test('the game does not start without enough players', function () {
    $room = hostedRoom(['settings' => ['rounds' => 1]]);
    $category = Category::factory()->create();
    Question::factory()->for($category)->create();
    $room->categories()->attach($category);
    Player::factory()->for($room)->create();

    asHost()->post(route('host.rooms.start', $room))->assertSessionHasErrors('start');

    expect($room->fresh()->status)->toBe(GameStatus::Lobby);
});

test('the game does not start with fewer questions than rounds', function () {
    $room = hostedRoom(['settings' => ['rounds' => 5], 'locale' => 'el']);
    $category = Category::factory()->create();
    Question::factory()->for($category)->count(3)->create();
    Question::factory()->for($category)->translatedInto(['en'])->count(5)->create();
    $room->categories()->attach($category);
    Player::factory()->for($room)->count(2)->create();

    asHost()->post(route('host.rooms.start', $room))
        ->assertSessionHasErrors(['start' => __('The chosen categories only have :count questions for :rounds rounds. Pick more categories or fewer rounds.', ['count' => 3, 'rounds' => 5])]);
});

test('closing the room frees its code', function () {
    $room = hostedRoom(['code' => 'ABCD']);

    asHost()->post(route('host.rooms.close', $room))->assertRedirect(route('home'));

    expect($room->fresh()->status)->toBe(GameStatus::Abandoned)
        ->and(Game::factory()->create(['code' => 'ABCD'])->exists)->toBeTrue();
});
