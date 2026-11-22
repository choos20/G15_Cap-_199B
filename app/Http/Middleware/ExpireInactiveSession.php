<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class ExpireInactiveSession
{
    public const TIMEOUT_SECONDS = 900;
    public const SESSION_KEY = 'last_user_activity_at';

    public function handle(Request $request, Closure $next): Response
    {
        if (! $request->user()) {
            return $next($request);
        }

        $lastActivity = (int) $request->session()->get(self::SESSION_KEY, now()->timestamp);
        if (now()->timestamp - $lastActivity >= self::TIMEOUT_SECONDS) {
            Auth::logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();

            return $request->expectsJson()
                ? response()->json(['message' => 'Session expired due to inactivity.'], 401)
                : redirect()->route('login', ['reason' => 'inactive']);
        }

        // Only explicit user activity or navigation extends this deadline.
        // Background polling requests must not keep abandoned sessions alive.
        if ($request->routeIs('session.activity') || $request->header('Sec-Fetch-Dest') === 'document') {
            $lastActivity = now()->timestamp;
        }
        $request->session()->put(self::SESSION_KEY, $lastActivity);

        return $next($request);
    }
}
