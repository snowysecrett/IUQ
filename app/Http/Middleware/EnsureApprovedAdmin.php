<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureApprovedAdmin
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();
        if (!$user) {
            abort(403);
        }

        if ($user->role === \App\Models\User::ROLE_SUPER_ADMIN) {
            return $next($request);
        }

        if ($user->role !== \App\Models\User::ROLE_ADMIN || $user->approved_at === null) {
            abort(403, 'Your admin account is pending superadmin approval.');
        }

        return $next($request);
    }
}
