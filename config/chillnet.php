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

];
