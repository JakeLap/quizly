<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Notes when a request arrived, before anything else runs, so answer
 * times aren't inflated by the time spent in sessions, auth and so on.
 */
class CaptureRequestTime
{
    public const string ATTRIBUTE = 'received_at';

    public function handle(Request $request, Closure $next): Response
    {
        $request->attributes->set(self::ATTRIBUTE, now());

        return $next($request);
    }
}
