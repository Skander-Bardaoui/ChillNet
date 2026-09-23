<?php

namespace App\Http\Controllers\Auth;

use App\Models\User;
use App\Support\AuthCookie;
use App\Support\JwtTokens;
use Illuminate\Support\Facades\Cookie;

/**
 * Émission et révocation de la paire de cookies d'authentification
 * (access_token + refresh_token) lue par le guard `web`
 * (config('auth.guards.web.driver') === 'jwt').
 *
 * La fabrication des jetons est centralisée dans App\Support\JwtTokens ; ce trait
 * se contente de les déposer dans (ou de les retirer des) cookies de la réponse.
 */
trait IssuesJwtCookies
{
    /**
     * Construit la paire de cookies httpOnly (access + refresh).
     *
     * @return array<int, \Symfony\Component\HttpFoundation\Cookie>
     */
    protected function authCookies(User $user, bool $remember = false): array
    {
        $tokens = JwtTokens::forUser($user, $remember);

        return [
            AuthCookie::make(AuthCookie::ACCESS, $tokens['access_token'], AuthCookie::accessTtl()),
            AuthCookie::make(AuthCookie::REFRESH, $tokens['refresh_token'], $tokens['refresh_ttl']),
        ];
    }

    /**
     * Connecte l'utilisateur en déposant une paire de cookies httpOnly.
     *
     * Les cookies sont à la fois mis en file (Cookie::queue) ET retournés pour
     * être attachés directement à la réponse via ->withCookie() : la pose ne
     * dépend donc plus d'un seul mécanisme.
     *
     * @return array<int, \Symfony\Component\HttpFoundation\Cookie>
     */
    protected function issueAuthCookies(User $user, bool $remember = false): array
    {
        $cookies = $this->authCookies($user, $remember);

        foreach ($cookies as $cookie) {
            Cookie::queue($cookie);
        }

        return $cookies;
    }

    /**
     * Cookies d'effacement (même path que la pose).
     *
     * @return array<int, \Symfony\Component\HttpFoundation\Cookie>
     */
    protected function forgetAuthCookies(): array
    {
        return [
            AuthCookie::forget(AuthCookie::ACCESS),
            AuthCookie::forget(AuthCookie::REFRESH),
        ];
    }

    /**
     * Révoque les jetons portés par la requête puis efface les cookies.
     *
     * @return array<int, \Symfony\Component\HttpFoundation\Cookie>
     */
    protected function clearAuthCookies(): array
    {
        JwtTokens::invalidate(
            request()->cookie(AuthCookie::ACCESS),
            request()->cookie(AuthCookie::REFRESH),
        );

        $cookies = $this->forgetAuthCookies();

        foreach ($cookies as $cookie) {
            Cookie::queue($cookie);
        }

        return $cookies;
    }
}
