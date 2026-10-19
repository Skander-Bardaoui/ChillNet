<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Third Party Services
    |--------------------------------------------------------------------------
    |
    | This file is for storing the credentials for third party services such
    | as Mailgun, Postmark, AWS and more. This file provides the de facto
    | location for this type of information, allowing packages to have
    | a conventional file to locate the various service credentials.
    |
    */

    'postmark' => [
        'key' => env('POSTMARK_API_KEY'),
    ],

    'resend' => [
        'key' => env('RESEND_API_KEY'),
    ],

    'ses' => [
        'key' => env('AWS_ACCESS_KEY_ID'),
        'secret' => env('AWS_SECRET_ACCESS_KEY'),
        'region' => env('AWS_DEFAULT_REGION', 'us-east-1'),
    ],

    'slack' => [
        'notifications' => [
            'bot_user_oauth_token' => env('SLACK_BOT_USER_OAUTH_TOKEN'),
            'channel' => env('SLACK_BOT_USER_DEFAULT_CHANNEL'),
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Météo & IA canicule (Module 1 — alertes / vigilance)
    |--------------------------------------------------------------------------
    |
    | WeatherAPI fournit la température/humidité en direct par quartier
    | (coordonnées lat/lng). Groq (compatible OpenAI) rédige le message
    | personnalisé ; le niveau de vigilance reste décidé par des seuils PHP
    | déterministes (voir VigilanceAiService).
    |
    */

    'weather' => [
        'key' => env('WEATHER_API_KEY'),
        'base_url' => env('WEATHER_BASE_URL', 'https://api.weatherapi.com/v1'),
        'seuil_defaut' => env('WEATHER_SEUIL_DEFAUT', 35),
    ],

    'groq' => [
        'key' => env('GROQ_API_KEY'),
        'base_url' => env('GROQ_BASE_URL', 'https://api.groq.com/openai/v1'),
        'model' => env('GROQ_MODEL', 'openai/gpt-oss-120b'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Géocodage inverse (coordonnées → adresse) — « Mes lieux »
    |--------------------------------------------------------------------------
    |
    | Nominatim (OpenStreetMap) remplit automatiquement le champ adresse quand
    | l'habitant pose son point sur la carte. Aucune clé : la politique d'usage
    | impose juste un User-Agent identifiable (voir GeocodingService).
    |
    */

    'nominatim' => [
        'base_url' => env('NOMINATIM_BASE_URL', 'https://nominatim.openstreetmap.org'),
        'user_agent' => env('NOMINATIM_USER_AGENT', 'ChillNet/1.0 (+'.env('APP_URL', 'http://localhost').')'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Reconnaissance faciale (Face++ / Megvii) — connexion « Face ID »
    |--------------------------------------------------------------------------
    |
    | Les appels sont exclusivement côté serveur : la clé et le secret ne
    | doivent jamais atteindre le navigateur. Voir FacePlusPlusClient.
    |
    */

    'faceplusplus' => [
        'enabled' => (bool) env('FACEPP_ENABLED', true),
        'key' => env('FACEPP_API_KEY'),
        'secret' => env('FACEPP_API_SECRET'),
        'base_url' => env('FACEPP_BASE_URL', 'https://api-us.faceplusplus.com'),
        'timeout' => (float) env('FACEPP_TIMEOUT', 8.0),
        'threshold' => (float) env('FACEPP_LOGIN_THRESHOLD', 80.0),
        'far' => env('FACEPP_LOGIN_FAR', '1e-5'),
        'image_max_bytes' => (int) env('FACEPP_IMAGE_MAX_BYTES', 2097152),
    ],

];
