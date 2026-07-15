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
            $playerId = (string) $request->input('player_id');

            return [
                Limit::perMinute(60)->by('player:'.($playerId !== '' ? $playerId : $request->ip())),
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
