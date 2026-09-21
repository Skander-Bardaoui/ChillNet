<?php

namespace App\Http\Controllers\Auth;

use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cookie;
use PHPOpenSourceSaver\JWTAuth\Facades\JWTAuth;

/**
 * Issues the access_token / refresh_token cookie pair that the 'web' guard
 * (config('auth.guards.web.driver') === 'jwt') reads on every request.
 *
 * The access token is a normal short-lived JWT (jwt.ttl). The refresh token
 * is also a JWT — same secret, same signature verification — but carries a
 * 'token_type' => 'refresh' claim and a much longer TTL (jwt.refresh_ttl), so
 * it is only ever accepted by the refresh flow, never by the normal auth guard.
 */
trait IssuesJwtCookies
{
    protected function issueAuthCookies(User $user): void
    {
        $accessToken = Auth::guard('web')->claims(['token_type' => 'access'])->login($user);

        JWTAuth::factory()->setTTL((int) config('jwt.refresh_ttl'));
        $refreshToken = JWTAuth::claims(['token_type' => 'refresh'])->fromUser($user);
        JWTAuth::factory()->setTTL((int) config('jwt.ttl'));

        $secure = app()->environment('production');

        Cookie::queue(cookie(
            'access_token',
            $accessToken,
            (int) config('jwt.ttl'),
            '/',
            null,
            $secure,
            true,
            false,
            'lax',
        ));

        Cookie::queue(cookie(
            'refresh_token',
            $refreshToken,
            (int) config('jwt.refresh_ttl'),
            '/',
            null,
            $secure,
            true,
            false,
            'lax',
        ));
    }

    protected function clearAuthCookies(): void
    {
        $accessToken = request()->cookie('access_token');
        $refreshToken = request()->cookie('refresh_token');

        foreach ([$accessToken, $refreshToken] as $token) {
            if (! $token) {
                continue;
            }

            try {
                JWTAuth::setToken($token)->invalidate();
            } catch (\Throwable $e) {
                // already invalid/expired — nothing to blacklist
            }
        }

        Cookie::queue(Cookie::forget('access_token'));
        Cookie::queue(Cookie::forget('refresh_token'));
    }
}
