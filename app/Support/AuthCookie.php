<?php

namespace App\Support;

use Illuminate\Support\Facades\Cookie;
use Symfony\Component\HttpFoundation\Cookie as SymfonyCookie;

/**
 * Fabrique unique des cookies d'authentification JWT.
 *
 * Centralise les options (chemin, httpOnly, sameSite, secure) et les durées de
 * vie des deux jetons afin que la connexion, le rafraîchissement et la
 * déconnexion émettent toujours des cookies cohérents.
 */
final class AuthCookie
{
    public const ACCESS = 'access_token';

    public const REFRESH = 'refresh_token';

    /**
     * Cookie contenant un jeton.
     */
    public static function make(string $name, string $value, int $minutes): SymfonyCookie
    {
        return cookie(
            $name,
            $value,
            $minutes,
            '/',
            null,
            app()->environment('production'),
            true,
            false,
            'lax',
        );
    }

    /**
     * Cookie d'effacement (même path '/' que le cookie posé).
     */
    public static function forget(string $name): SymfonyCookie
    {
        return Cookie::forget($name, '/', null);
    }

    /**
     * Durée de vie de l'access token, en minutes.
     */
    public static function accessTtl(): int
    {
        return (int) config('jwt.ttl');
    }

    /**
     * Durée de vie du refresh token, en minutes (allongée si « Se souvenir de moi »).
     */
    public static function refreshTtl(bool $remember = false): int
    {
        return $remember
            ? (int) config('chillnet.remember_refresh_ttl')
            : (int) config('jwt.refresh_ttl');
    }
}
