<?php

namespace App\Http\Middleware;

use App\Models\User;
use Illuminate\Http\Request;
use Inertia\Middleware;

class HandleInertiaRequests extends Middleware
{
    /**
     * The root template that is loaded on the first page visit.
     *
     * @var string
     */
    protected $rootView = 'app';

    /**
     * Determine the current asset version.
     */
    public function version(Request $request): ?string
    {
        return parent::version($request);
    }

    /**
     * Define the props that are shared by default.
     *
     * @return array<string, mixed>
     */
    public function share(Request $request): array
    {
        return [
            ...parent::share($request),
            'auth' => [
                'user' => $request->user()?->only(['id', 'name', 'email', 'role', 'approved_at', 'last_seen_at']),
            ],
            'presence' => function () use ($request): array {
                $user = $request->user();
                if (!$user || $user->role !== User::ROLE_SUPER_ADMIN) {
                    return [
                        'online_count' => 0,
                        'online_users' => [],
                    ];
                }

                $onlineUsers = User::query()
                    ->whereNotNull('last_seen_at')
                    ->where('last_seen_at', '>=', now()->subMinutes(5))
                    ->orderByDesc('last_seen_at')
                    ->get(['id', 'name', 'email', 'role', 'last_seen_at']);

                return [
                    'online_count' => $onlineUsers->count(),
                    'online_users' => $onlineUsers,
                ];
            },
            'locale' => session('locale', 'en'),
            'flash' => [
                'success' => $request->session()->get('success'),
                'error' => $request->session()->get('error'),
            ],
        ];
    }
}
