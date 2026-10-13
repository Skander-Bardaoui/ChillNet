<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Moteur IA canicule — microservice Python (FastAPI + scikit-learn)
    |--------------------------------------------------------------------------
    |
    | Toute la logique IA (classification, règles, rédaction du message) vit
    | dans le service Python `ai-service/`. Laravel n'est qu'un orchestrateur :
    | il envoie un CONTEXTE BRUT (températures, humidité, prévisions, historique)
    | et reçoit un niveau + une explication.
    |
    | Si le service est indisponible (timeout, connexion refusée, réponse
    | invalide), Laravel retombe sur les règles déterministes PHP
    | (VigilanceAiService::niveauPour) et un message de secours minimal. Le
    | niveau final n'est JAMAIS inférieur aux règles.
    |
    */

    // Interrupteur global : à false, Laravel n'appelle jamais le réseau IA et
    // fonctionne en « mode règles » (tests hors-ligne, exploitation dégradée).
    'enabled' => env('AI_SERVICE_ENABLED', true),

    // URL de base du microservice (uvicorn app.main:app --port 8001).
    'service_url' => env('AI_SERVICE_URL', 'http://127.0.0.1:8001'),

    // Clé partagée, transmise dans l'en-tête X-AI-TOKEN (défense en profondeur).
    'token' => env('AI_SERVICE_TOKEN', ''),

    // Délai maximal (secondes) : au-delà, on bascule en mode règles.
    'timeout' => (float) env('AI_SERVICE_TIMEOUT', 2.0),

    /*
    |--------------------------------------------------------------------------
    | Rédaction du message
    |--------------------------------------------------------------------------
    |
    | `driver` = 'local' : rédaction par le microservice Python (défaut).
    | `driver` = 'groq'  : ancien fournisseur LLM distant (désactivé par défaut,
    |                      à réserver à un usage explicite).
    |
    */

    'driver' => env('AI_DRIVER', 'local'),

    // Rétro-compatibilité : une clé Groq présente active Groq même en « local ».
    'use_groq_when_key_present' => env('AI_USE_GROQ_WHEN_KEY', true),

    /*
    |--------------------------------------------------------------------------
    | Repli : valeurs par défaut quand une mesure est manquante
    |--------------------------------------------------------------------------
    */

    'defaults' => [
        'temperature' => 25.0,
        'humidite' => 50,
    ],

    /*
    |--------------------------------------------------------------------------
    | Messages
    |--------------------------------------------------------------------------
    */

    'messages' => [
        'cache_minutes' => 30,
    ],

    /*
    |--------------------------------------------------------------------------
    | Brouillons automatiques (ai:predict-daily)
    |--------------------------------------------------------------------------
    */

    'auto_draft' => [
        'hour' => env('AI_DRAFT_HOUR', 6),
        'window_hours' => 24,
    ],

];
