<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;

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
        RateLimiter::for('login', function (Request $request) {
            $username = strtolower(trim((string) $request->input('username')));

            return Limit::perMinute(5)->by(
                $username . '|' . $request->ip()
            )->response(function (Request $request, array $headers) {
                return back()
                    ->withErrors([
                        'username' => 'Terlalu banyak percobaan login. Silakan coba lagi dalam satu menit.',
                    ])
                    ->onlyInput('username')
                    ->withHeaders($headers);
            });
        });
    }
}
