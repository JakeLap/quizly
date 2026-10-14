<?php

namespace App\Providers;

use App\Auth\Host;
use App\Models\Game;
use App\Models\Player;
use App\Support\GuestCookies;
use App\Support\RoomCode;
use Carbon\CarbonImmutable;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;
use Illuminate\Validation\Rules\Password;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        $this->configureDefaults();
        $this->configureGuestAuth();
        $this->configureRateLimiting();
        $this->configureRouteBindings();
    }

    /**
     * {room} in a URL is the code of an active (or just finished) room.
     */
    protected function configureRouteBindings(): void
    {
        Route::pattern('room', '['.RoomCode::ALPHABET.']{'.RoomCode::LENGTH.'}');
        Route::bind('room', fn (string $code) => Game::findByCode($code) ?? abort(404));
    }

    /**
     * Hosts and players are guests, identified by their cookie tokens.
     */
    protected function configureGuestAuth(): void
    {
        Auth::viaRequest('host-cookie', function (Request $request): ?Host {
            $token = $request->cookie(GuestCookies::HOST);
            $game = is_string($token) ? Game::findByHostToken($token) : null;

            return $game ? new Host($game) : null;
        });

        Auth::viaRequest('player-cookie', function (Request $request): ?Player {
            $token = $request->cookie(GuestCookies::PLAYER);

            return is_string($token) ? Player::findByToken($token) : null;
        });
    }

    protected function configureRateLimiting(): void
    {
        RateLimiter::for('create-room', fn (Request $request) => Limit::perMinute(10)->by($request->ip()));

        RateLimiter::for('join-room', fn (Request $request) => Limit::perMinute(30)->by($request->ip()));
    }

    /**
     * Configure default behaviors for production-ready applications.
     */
    protected function configureDefaults(): void
    {
        Date::use(CarbonImmutable::class);

        DB::prohibitDestructiveCommands(
            app()->isProduction(),
        );

        Password::defaults(fn (): ?Password => app()->isProduction()
            ? Password::min(12)
                ->mixedCase()
                ->letters()
                ->numbers()
                ->symbols()
                ->uncompromised()
            : null,
        );
    }
}
