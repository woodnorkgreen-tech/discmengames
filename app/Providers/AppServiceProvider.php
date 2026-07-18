<?php

namespace App\Providers;

use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        // Every guest at the venue reaches a public host through ONE shared
        // public IP, so per-IP limits must be generous. Registration is
        // nickname-only (no phone collected), so combine a generous venue-IP
        // ceiling with a strict per-nickname ceiling to stop hammering.
        RateLimiter::for('register', function (Request $request) {
            $nickname = mb_strtolower(trim((string) $request->input('nickname')));

            return [
                Limit::perMinute(10)->by('register-nick:'.($nickname !== '' ? $nickname : $request->ip())),
                Limit::perMinute(500)->by('register-ip:'.$request->ip()),
            ];
        });

        RateLimiter::for('per-player', function (Request $request) {
            // Key the per-player bucket on the session token, not the body
            // player_id: player ids are sequential and guessable, so keying on
            // them would let anyone at the venue exhaust a victim's quota and
            // lock them out of answering. The token is a secret only the real
            // player holds; tokenless (unauthenticated) traffic falls back to the
            // shared-IP bucket.
            $token = (string) $request->header('X-Player-Token', '');
            $key = $token !== '' ? hash('sha256', $token) : $request->ip();

            return [
                Limit::perMinute(60)->by('player:'.$key),
                Limit::perMinute(2000)->by('player-ip:'.$request->ip()),
            ];
        });

        RateLimiter::for('player-login', function (Request $request) {
            $nickname = mb_strtolower(trim((string) $request->input('nickname')));

            return [
                Limit::perMinute(8)->by('login-nick:'.($nickname ?: $request->ip())),
                Limit::perMinute(300)->by('login-ip:'.$request->ip()),
            ];
        });
    }
}
