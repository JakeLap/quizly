<?php

use App\Http\Controllers\Rooms\HostRoomController;
use App\Http\Controllers\Rooms\PlayController;
use App\Http\Middleware\EnsureRoomHost;
use App\Http\Middleware\EnsureRoomPlayer;
use Illuminate\Broadcasting\BroadcastController;
use Illuminate\Support\Facades\Route;

Route::inertia('/', 'Welcome')->name('home');

// The shared screen (TV) that runs a room. Authenticated by the host cookie, scoped to /host.
Route::prefix('host')->name('host.')->group(function () {
    Route::post('rooms', [HostRoomController::class, 'store'])->middleware('throttle:create-room')->name('rooms.store');
    Route::match(['get', 'post'], 'broadcasting/auth', [BroadcastController::class, 'authenticate'])->name('broadcasting.auth');

    Route::middleware(EnsureRoomHost::class)->controller(HostRoomController::class)->prefix('{room}')->name('rooms.')->group(function () {
        Route::get('/', 'show')->name('show');
        Route::patch('settings', 'update')->name('update');
        Route::post('start', 'start')->name('start');
        Route::post('next', 'next')->name('next');
        Route::post('presence', 'presenceChanged')->middleware('throttle:60,1')->name('presence');
        Route::post('heartbeat', 'heartbeat')->middleware('throttle:60,1')->name('heartbeat');
        Route::post('players/{player}/kick', 'kick')->scopeBindings()->name('kick');
        Route::post('close', 'close')->name('close');
    });
});

// Players on their phones. Authenticated by the player cookie, scoped to /play.
Route::prefix('play')->name('play.')->group(function () {
    Route::get('/', [PlayController::class, 'create'])->name('create');
    Route::post('/', [PlayController::class, 'store'])->middleware('throttle:join-room')->name('store');
    Route::match(['get', 'post'], 'broadcasting/auth', [BroadcastController::class, 'authenticate'])->name('broadcasting.auth');
    Route::get('{code}', [PlayController::class, 'show'])->where('code', '[A-Za-z ]{1,8}')->name('show');

    Route::middleware(EnsureRoomPlayer::class)->controller(PlayController::class)->prefix('{room}')->group(function () {
        Route::post('leave', 'leave')->name('leave');
        Route::post('start', 'start')->name('start');
        Route::post('answer', 'answer')->name('answer');
    });
});

Route::middleware(['auth', 'verified'])->group(function () {
    Route::inertia('dashboard', 'Dashboard')->name('dashboard');
});

require __DIR__.'/settings.php';
