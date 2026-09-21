<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cookie;
use PHPOpenSourceSaver\JWTAuth\Exceptions\JWTException;
use PHPOpenSourceSaver\JWTAuth\Facades\JWTAuth;
use Symfony\Component\HttpFoundation\Response;

/**
 * Runs before route/auth middleware on every web request. If the access_token
 * cookie is missing/expired/wrong-type, tries to mint a fresh access token
 * from the refresh_token cookie — transparently, so a Blade page navigation
 * never bounces the user to /login just because their access token aged out.
 *
 * Must run before 'auth' (see prependToPriorityList in bootstrap/app.php) —
 * otherwise Laravel's middleware-priority sort runs 'auth' first regardless
 * of this middleware's position in the 'web' group array, and an expired
 * access token would 302 to /login before this ever gets a chance to refresh.
 *
 * The new token is written back into the current request's cookie bag (so
 * the 'auth' middleware that runs right after this one sees it) AND queued
 * as a Set-Cookie header for the browser.
 */
class RefreshExpiredJwtCookie
{
    public function handle(Request $request, Closure $next): Response
    {
        $accessToken = $request->cookie('access_token');

        if ($accessToken && $this->isUsableAccessToken($accessToken)) {
            return $next($request);
        }

        $refreshToken = $request->cookie('refresh_token');

        if ($refreshToken) {
            $newAccessToken = $this->tryMintFromRefreshToken($refreshToken);

            if ($newAccessToken) {
                $request->cookies->set('access_token', $newAccessToken);

                Cookie::queue(cookie(
                    'access_token',
                    $newAccessToken,
                    (int) config('jwt.ttl'),
                    '/',
                    null,
                    app()->environment('production'),
                    true,
                    false,
                    'lax',
                ));
            } else {
                $this->forgetAuthCookies();
            }
        } elseif ($accessToken) {
            // Malformed/invalid access token and no refresh token to fall back on.
            $this->forgetAuthCookies();
        }

        return $next($request);
    }

    private function isUsableAccessToken(string $token): bool
    {
        try {
            $payload = JWTAuth::setToken($token)->getPayload();

            return $payload->get('token_type') !== 'refresh';
        } catch (JWTException $e) {
            return false;
        }
    }

    private function tryMintFromRefreshToken(string $refreshToken): ?string
    {
        try {
            JWTAuth::setToken($refreshToken);
            $payload = JWTAuth::getPayload();

            if ($payload->get('token_type') !== 'refresh') {
                return null;
            }

            $user = JWTAuth::authenticate();

            if (! $user) {
                return null;
            }

            return JWTAuth::claims(['token_type' => 'access'])->fromUser($user);
        } catch (JWTException $e) {
            return null;
        }
    }

    private function forgetAuthCookies(): void
    {
        Cookie::queue(Cookie::forget('access_token'));
        Cookie::queue(Cookie::forget('refresh_token'));
    }
}
