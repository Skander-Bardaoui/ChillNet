<?php

namespace App\Http\Middleware;

use App\Support\AuthCookie;
use App\Support\JwtTokens;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cookie;
use Symfony\Component\HttpFoundation\Response;

/**
 * Exécuté avant les middlewares de route / d'authentification sur chaque requête web.
 *
 * Si l'access_token est absent, expiré ou de mauvais type, on régénère une paire
 * de jetons à partir du refresh_token (rotation : l'ancien refresh token est
 * révoqué), puis on réinjecte le nouvel access token dans la requête courante
 * pour que le middleware 'auth' qui suit authentifie correctement l'utilisateur.
 *
 * Ce middleware doit s'exécuter AVANT 'auth' — voir prependToPriorityList dans
 * bootstrap/app.php, sinon le tri de priorité des middlewares laisserait 'auth'
 * rediriger vers /login avant qu'on ait pu rafraîchir le jeton.
 */
class RefreshExpiredJwtCookie
{
    public function handle(Request $request, Closure $next): Response
    {
        $accessToken = $request->cookie(AuthCookie::ACCESS);

        if ($accessToken && JwtTokens::isUsableAccessToken($accessToken)) {
            return $next($request);
        }

        $refreshToken = $request->cookie(AuthCookie::REFRESH);
        $tokens = $refreshToken ? JwtTokens::rotate($refreshToken) : null;

        if ($tokens) {
            $request->cookies->set(AuthCookie::ACCESS, $tokens['access_token']);
            $request->cookies->set(AuthCookie::REFRESH, $tokens['refresh_token']);

            $accessCookie = AuthCookie::make(AuthCookie::ACCESS, $tokens['access_token'], AuthCookie::accessTtl());
            $refreshCookie = AuthCookie::make(AuthCookie::REFRESH, $tokens['refresh_token'], $tokens['refresh_ttl']);

            Cookie::queue($accessCookie);
            Cookie::queue($refreshCookie);

            /** @var Response $response */
            $response = $next($request);
            $response->headers->setCookie($accessCookie);
            $response->headers->setCookie($refreshCookie);

            return $response;
        }

        if ($accessToken || $refreshToken) {
            $this->forgetAuthCookies();
        }

        return $next($request);
    }

    private function forgetAuthCookies(): void
    {
        Cookie::queue(AuthCookie::forget(AuthCookie::ACCESS));
        Cookie::queue(AuthCookie::forget(AuthCookie::REFRESH));
    }
}
