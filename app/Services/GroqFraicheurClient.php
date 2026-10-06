<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Accès Groq du module 3 (points de fraîcheur), sur la même configuration
 * que le module 1 : `config('services.groq.*')` ← GROQ_API_KEY / GROQ_MODEL
 * du fichier .env. Renvoie `null` si la clé est absente ou si l'appel échoue :
 * chaque service appelant retombe alors sur son calcul déterministe.
 */
class GroqFraicheurClient
{
    public function disponible(): bool
    {
        return ! empty(config('services.groq.key'));
    }

    /**
     * Réponse texte du modèle, ou null.
     */
    public function completer(string $systeme, string $utilisateur, int $maxTokens = 600, float $temperature = 0.3): ?string
    {
        if (! $this->disponible()) {
            return null;
        }

        try {
            $response = Http::withToken((string) config('services.groq.key'))
                ->timeout(20)
                ->acceptJson()
                ->post(rtrim((string) config('services.groq.base_url', 'https://api.groq.com/openai/v1'), '/').'/chat/completions', [
                    'model' => config('services.groq.model', 'openai/gpt-oss-120b'),
                    'messages' => [
                        ['role' => 'system', 'content' => $systeme],
                        ['role' => 'user', 'content' => $utilisateur],
                    ],
                    'temperature' => $temperature,
                    // gpt-oss : le raisonnement compte dans max_tokens.
                    'reasoning_effort' => 'low',
                    'max_tokens' => $maxTokens,
                ]);
        } catch (\Throwable $e) {
            Log::warning('Groq (points de fraîcheur) : appel impossible', ['message' => $e->getMessage()]);

            return null;
        }

        $texte = data_get($response->json(), 'choices.0.message.content');

        if ($response->failed() || ! is_string($texte) || trim($texte) === '') {
            Log::warning('Groq (points de fraîcheur) : réponse inexploitable', ['status' => $response->status()]);

            return null;
        }

        return trim($texte);
    }

    /**
     * Réponse JSON décodée (premier objet `{...}` trouvé), ou null.
     *
     * @return array<string, mixed>|null
     */
    public function completerJson(string $systeme, string $utilisateur, int $maxTokens = 600): ?array
    {
        $texte = $this->completer($systeme, $utilisateur, $maxTokens, 0.0);

        if ($texte === null || ! preg_match('/\{.*\}/s', $texte, $m)) {
            return null;
        }

        $json = json_decode($m[0], true);

        return is_array($json) ? $json : null;
    }
}
