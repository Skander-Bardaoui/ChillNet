<?php

use App\Http\Middleware\EnsureUserHasRole;
use App\Http\Middleware\RefreshExpiredJwtCookie;
use App\Support\AuthCookie;
use Illuminate\Contracts\Auth\Middleware\AuthenticatesRequests;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->alias([
            'role' => EnsureUserHasRole::class,
        ]);

        // Les jetons JWT restent httpOnly (invisibles en JS : c'est normal et
        // voulu), mais on les exclut du chiffrement Laravel pour qu'ils soient
        // lisibles tels quels dans Inspecteur > Application > Cookies
        // (valeur eyJ...) et conformes à jwt.decrypt_cookies = false.
        $middleware->encryptCookies(except: [
            AuthCookie::ACCESS,
            AuthCookie::REFRESH,
        ]);

        $middleware->web(append: [
            RefreshExpiredJwtCookie::class,
        ]);

        // Laravel's middleware-priority sort otherwise runs 'auth' (and its
        // redirect-to-login) BEFORE an unlisted appended middleware gets a
        // chance to silently refresh an expired access token from the cookie.
        $middleware->prependToPriorityList(
            before: AuthenticatesRequests::class,
            prepend: RefreshExpiredJwtCookie::class,
        );
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        // Une capture biométrique invalide ne doit jamais être renvoyée dans
        // l'ancienne saisie (session / HTML), même en cas d'erreur de validation.
        $exceptions->dontFlash(['image']);
    })->create();
