<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class UpdateLastSeenAt
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user) {
            $now = now();
            $lastPingAt = (int) $request->session()->get('last_seen_ping_at', 0);

            // Throttle writes: persist at most once per minute per session.
            if ($now->timestamp - $lastPingAt >= 60) {
                $user->forceFill(['last_seen_at' => $now])->saveQuietly();
                $request->session()->put('last_seen_ping_at', $now->timestamp);
            }
        }

        return $next($request);
    }
}

