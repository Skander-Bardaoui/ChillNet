<?php

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
            'role' => \App\Http\Middleware\EnsureUserHasRole::class,
        ]);

        // Les jetons JWT restent httpOnly (invisibles en JS : c'est normal et
        // voulu), mais on les exclut du chiffrement Laravel pour qu'ils soient
        // lisibles tels quels dans Inspecteur > Application > Cookies
        // (valeur eyJ...) et conformes à jwt.decrypt_cookies = false.
        $middleware->encryptCookies(except: [
            \App\Support\AuthCookie::ACCESS,
            \App\Support\AuthCookie::REFRESH,
        ]);

        $middleware->web(append: [
            \App\Http\Middleware\RefreshExpiredJwtCookie::class,
        ]);

        // Laravel's middleware-priority sort otherwise runs 'auth' (and its
        // redirect-to-login) BEFORE an unlisted appended middleware gets a
        // chance to silently refresh an expired access token from the cookie.
        $middleware->prependToPriorityList(
            before: \Illuminate\Contracts\Auth\Middleware\AuthenticatesRequests::class,
            prepend: \App\Http\Middleware\RefreshExpiredJwtCookie::class,
        );
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        //
    })->create();
