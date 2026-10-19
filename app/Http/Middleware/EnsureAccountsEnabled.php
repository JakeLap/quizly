<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * User accounts (login, registration, the dashboard and settings) come from
 * the starter kit and aren't used by the game yet, so they're switched off
 * unless QUIZLY_ACCOUNTS_ENABLED is set. Their pages then don't exist.
 */
class EnsureAccountsEnabled
{
    public function handle(Request $request, Closure $next): Response
    {
        abort_unless(config('quizly.accounts_enabled'), 404);

        return $next($request);
    }
}
