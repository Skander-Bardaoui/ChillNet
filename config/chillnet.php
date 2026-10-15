<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Durée de vie du refresh token (minutes)
    |--------------------------------------------------------------------------
    |
    | Le refresh token est émis dans un cookie httpOnly. Sa durée de vie par
    | défaut est celle de `jwt.refresh_ttl` (14 jours). Lorsque le foyer coche
    | « Se souvenir de moi » à la connexion, on lui accorde une fenêtre plus
    | longue : 43200 minutes = 30 jours.
    |
    | À chaque rafraîchissement, le refresh token est régénéré (rotation) en
    | conservant cette durée.
    |
    */

    'remember_refresh_ttl' => (int) env('CHILLNET_REMEMBER_REFRESH_TTL', 43200),

    /*
    |--------------------------------------------------------------------------
    | Flag « secure » des cookies d'authentification
    |--------------------------------------------------------------------------
    |
    | Les cookies access_token/refresh_token sont httpOnly ; le flag `secure`
    | interdit au navigateur de les transmettre en clair. Par défaut on suit
    | l'environnement (true en production, donc derrière TLS). Un lab servi en
    | HTTP pur (ingress Traefik « web », http://chillnet.local) doit forcer
    | AUTH_COOKIE_SECURE=false, sinon le navigateur ne renvoie jamais les
    | cookies et la connexion reboucle sur /login.
    |
    */
    'auth_cookie_secure' => (bool) env('AUTH_COOKIE_SECURE', env('APP_ENV') === 'production'),

];
