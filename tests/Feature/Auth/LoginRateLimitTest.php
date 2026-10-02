<?php

namespace Tests\Feature\Auth;

use Illuminate\Contracts\Auth\UserProvider;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\RateLimiter;
use Mockery;
use Tests\TestCase;

class LoginRateLimitTest extends TestCase
{
    public function test_login_endpoint_limits_requests_after_five_attempts(): void
    {
        $username = 'rate_limit_http_test';
        $key = strtolower($username) . '|127.0.0.1';

        RateLimiter::clear($key);

        $provider = Mockery::mock(UserProvider::class);
        $provider->shouldReceive('retrieveByCredentials')
            ->times(5)
            ->andReturn(null);

        Auth::guard()->setProvider($provider);

        for ($i = 0; $i < 5; $i++) {
            $this->from('/login')
                ->post(route('login.process'), [
                    'username' => $username,
                    'password' => 'WrongPassword123!',
                ])
                ->assertRedirect('/login');
        }

        // Request keenam harus ditangani oleh rate limiter,
        // bukan diteruskan ke proses autentikasi.
        $this->from('/login')
            ->post(route('login.process'), [
                'username' => $username,
                'password' => 'WrongPassword123!',
            ])
            ->assertRedirect('/login')
            ->assertSessionHasErrors('username');

        RateLimiter::clear($key);
    }
}
