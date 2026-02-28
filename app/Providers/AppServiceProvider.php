<?php

namespace App\Providers;

use App\Models\User;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Vite;
use Illuminate\Support\ServiceProvider;

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
        Vite::prefetch(concurrency: 3);

        RateLimiter::for('public-site', function (Request $request) {
            $role = $request->user()?->role;
            $userId = $request->user()?->id;

            if (in_array($role, [User::ROLE_ADMIN, User::ROLE_SUPER_ADMIN], true)) {
                return Limit::none();
            }

            if ($userId) {
                return Limit::perMinute(200)->by('user:'.$userId);
            }

            return Limit::perMinute(200)->by('ip:'.$request->ip());
        });
    }
}
