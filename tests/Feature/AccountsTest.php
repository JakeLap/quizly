<?php

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;

uses(RefreshDatabase::class);

beforeEach(function () {
    config(['quizly.accounts_enabled' => false]);
});

test('login, registration and the account pages do not exist while accounts are off', function (string $method, string $uri) {
    $this->json($method, $uri)->assertNotFound();
})->with([
    ['GET', '/login'],
    ['POST', '/login'],
    ['GET', '/register'],
    ['POST', '/register'],
    ['GET', '/forgot-password'],
    ['GET', '/two-factor-challenge'],
    ['GET', '/passkeys/login/options'],
    ['GET', '/dashboard'],
    ['GET', '/settings/profile'],
    ['GET', '/.well-known/passkey-endpoints'],
]);

test('they do not exist for a signed-in user either', function () {
    $this->actingAs(User::factory()->create())->get('/dashboard')->assertNotFound();
});

test('nobody can register while accounts are off', function () {
    $this->post('/register', [
        'name' => 'Someone',
        'email' => 'someone@example.com',
        'password' => 'password',
        'password_confirmation' => 'password',
    ])->assertNotFound();

    expect(User::query()->count())->toBe(0);
});

test('the start page hides the admin link while accounts are off', function () {
    $this->get('/')->assertOk()->assertInertia(fn (Assert $page) => $page->where('accountsEnabled', false));

    config(['quizly.accounts_enabled' => true]);
    $this->get('/')->assertInertia(fn (Assert $page) => $page->where('accountsEnabled', true));
});

test('the game keeps working while accounts are off', function () {
    $this->post(route('host.rooms.store'))->assertRedirect();
    $this->get('/play')->assertOk();
});
