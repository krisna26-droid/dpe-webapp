<?php

namespace App\Providers;

use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Facades\View;
use Illuminate\Support\Facades\Schema;
use App\Services\InAppNotificationService;

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
        View::composer('layouts.dashboard', function ($view) {
            $request = request();
            $user = $request->user();
            $items = collect();
            // Keep older operational pages usable when the notification module is not installed yet.
            $available = Schema::hasTable('in_app_notification_reads');
            if ($user && $user->is_active && $available) {
                $items = $request->attributes->get('dpe.inbox.items');
                if ($items === null) {
                    $items = app(InAppNotificationService::class)->items($user);
                    $request->attributes->set('dpe.inbox.items', $items);
                }
            }
            $view->with('notificationHeader', ['available' => $available, 'unread' => $items->where('read', false)->count(),
                'items' => $items->where('read', false)->take(4)]);
        });

        RateLimiter::for('login', function (Request $request) {
            $input = $request->input('username');
            $username = is_string($input) ? strtolower(trim($input)) : 'invalid-username';

            return Limit::perMinute(5)->by(
                $username.'|'.$request->ip()
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
