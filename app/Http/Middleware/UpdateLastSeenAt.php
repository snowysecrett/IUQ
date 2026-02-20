<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
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

                if (Schema::hasTable('visitor_presences')) {
                    DB::table('visitor_presences')->upsert([
                        [
                            'session_id' => $request->session()->getId(),
                            'user_id' => $user->id,
                            'is_authenticated' => true,
                            'last_seen_at' => $now,
                            'updated_at' => $now,
                            'created_at' => $now,
                        ],
                    ], ['session_id'], ['user_id', 'is_authenticated', 'last_seen_at', 'updated_at']);
                }

                $request->session()->put('last_seen_ping_at', $now->timestamp);
            }
        } else {
            $now = now();
            $lastPingAt = (int) $request->session()->get('last_seen_ping_at', 0);

            if ($now->timestamp - $lastPingAt >= 60 && Schema::hasTable('visitor_presences')) {
                DB::table('visitor_presences')->upsert([
                    [
                        'session_id' => $request->session()->getId(),
                        'user_id' => null,
                        'is_authenticated' => false,
                        'last_seen_at' => $now,
                        'updated_at' => $now,
                        'created_at' => $now,
                    ],
                ], ['session_id'], ['user_id', 'is_authenticated', 'last_seen_at', 'updated_at']);

                $request->session()->put('last_seen_ping_at', $now->timestamp);
            }
        }

        return $next($request);
    }
}
