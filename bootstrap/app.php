<?php

use App\Http\Middleware\CaptureRequestTime;
use App\Http\Middleware\EnsureAccountsEnabled;
use App\Http\Middleware\HandleAppearance;
use App\Http\Middleware\HandleInertiaRequests;
use App\Http\Middleware\SetLocale;
use Illuminate\Contracts\Auth\Middleware\AuthenticatesRequests;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Middleware\AddLinkHeadersForPreloadedAssets;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Symfony\Component\HttpFoundation\Response as SymfonyResponse;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        channels: __DIR__.'/../routes/channels.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->prepend(CaptureRequestTime::class);

        // In production the app sits behind a reverse proxy (e.g. Coolify's
        // Traefik), which is the only way in: trust it for the visitor's IP
        // (rate limits) and for HTTPS.
        $middleware->trustProxies(at: '*');

        $middleware->encryptCookies(except: ['appearance', 'sidebar_state']);

        // Switched-off account pages answer 404 before anything asks for a login.
        $middleware->prependToPriorityList(
            before: AuthenticatesRequests::class,
            prepend: EnsureAccountsEnabled::class,
        );

        $middleware->web(append: [
            HandleAppearance::class,
            SetLocale::class,
            HandleInertiaRequests::class,
            AddLinkHeadersForPreloadedAssets::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*') || $request->expectsJson(),
        );

        // Errors on pages get a page in the game's style, instead of Laravel's
        // (which Inertia would show in a modal). Requests made with fetch keep
        // their JSON, and in development server errors keep their debug page.
        $exceptions->respond(function (SymfonyResponse $response, Throwable $e, Request $request) {
            $status = $response->getStatusCode();

            if ($request->expectsJson()
                || ! in_array($status, [403, 404, 405, 419, 429, 500, 503], true)
                || ($status >= 500 && config('app.debug'))) {
                return $response;
            }

            // The error may have happened before the middleware that sets these.
            $locale = SetLocale::localeFor($request);
            app()->setLocale($locale);
            Inertia::share([
                'locale' => $locale,
                'translations' => HandleInertiaRequests::translations($locale),
            ]);

            return Inertia::render('Error', ['status' => $status])
                ->toResponse($request)
                ->setStatusCode($status);
        });
    })->create();
