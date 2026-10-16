<?php

namespace App\Services\Ai;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Client HTTP vers le microservice IA canicule (FastAPI + scikit-learn).
 *
 * Contrat strict : AUCUNE méthode ne lève jamais d'exception et aucune ne
 * bloque au-delà du timeout configuré. En cas de timeout, de connexion refusée,
 * de statut non-2xx ou de JSON invalide, la méthode renvoie `null` (ou `false`),
 * ce qui déclenche le repli « règles » côté appelant.
 *
 * Chaque requête porte l'en-tête `X-AI-TOKEN` (clé partagée avec le service).
 */
class AiServiceClient
{
    /**
     * Le service est-il activé et configuré ?
     */
    public function enabled(): bool
    {
        return (bool) config('ai.enabled', true) && trim((string) config('ai.service_url', '')) !== '';
    }

    /**
     * POST /analyze — classification + explication d'un contexte unitaire.
     *
     * @param  array<string, mixed>  $context  contexte BRUT (aucun feature engineering PHP)
     * @return array<string, mixed>|null
     */
    public function analyze(array $context): ?array
    {
        return $this->post('/analyze', $context);
    }

    /**
     * POST /analyze/batch — classification de plusieurs contextes en un appel.
     *
     * @param  array<int, array<string, mixed>>  $contexts
     * @return array<int, array<string, mixed>>|null  liste de résultats, dans l'ordre
     */
    public function analyzeBatch(array $contexts): ?array
    {
        $reponse = $this->post('/analyze/batch', ['items' => array_values($contexts)]);

        if ($reponse === null) {
            return null;
        }

        $items = $reponse['results'] ?? $reponse['items'] ?? null;

        return is_array($items) ? $items : null;
    }

    /**
     * POST /message — rédaction d'un message personnalisé.
     *
     * @param  array<string, mixed>  $context
     * @param  array<int, string>  $profils
     */
    public function message(array $context, array $profils = [], int|string|null $seed = null): ?string
    {
        $payload = $context;

        if ($profils !== []) {
            $payload['profils'] = array_values($profils);
        }

        if ($seed !== null) {
            $payload['seed'] = (string) $seed;
        }

        $reponse = $this->post('/message', $payload);

        if ($reponse === null) {
            return null;
        }

        $message = $reponse['message'] ?? null;

        return is_string($message) && trim($message) !== '' ? trim($message) : null;
    }

    /**
     * GET /model-info — version, date d'entraînement, métriques, nb de corrections.
     *
     * @return array<string, mixed>|null
     */
    public function modelInfo(): ?array
    {
        return $this->get('/model-info');
    }

    /**
     * GET /health — le service répond-il ?
     */
    public function health(): bool
    {
        return $this->get('/health') !== null;
    }

    /**
     * POST /reload — recharge à chaud l'artefact modèle (sans ré-entraîner).
     */
    public function reload(): bool
    {
        return $this->post('/reload', []) !== null;
    }

    /**
     * POST /retrain — lance un ré-entraînement côté Python puis recharge le
     * modèle. Délai élargi : l'entraînement peut durer plusieurs secondes.
     *
     * @return array<string, mixed>|null
     */
    public function retrain(): ?array
    {
        return $this->post('/retrain', [], (float) env('AI_SERVICE_RETRAIN_TIMEOUT', 120.0));
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>|null
     */
    private function post(string $path, array $payload, ?float $timeout = null): ?array
    {
        if (! $this->enabled()) {
            return null;
        }

        try {
            $reponse = Http::withHeaders($this->headers())
                ->timeout($timeout ?? (float) config('ai.timeout', 2.0))
                ->acceptJson()
                ->asJson()
                ->post($this->url($path), $payload);
        } catch (\Throwable $e) {
            Log::warning('Service IA canicule : appel impossible', [
                'path' => $path,
                'message' => $e->getMessage(),
            ]);

            return null;
        }

        return $this->decoder($reponse, $path);
    }

    /**
     * @return array<string, mixed>|null
     */
    private function get(string $path): ?array
    {
        if (! $this->enabled()) {
            return null;
        }

        try {
            $reponse = Http::withHeaders($this->headers())
                ->timeout((float) config('ai.timeout', 2.0))
                ->acceptJson()
                ->get($this->url($path));
        } catch (\Throwable $e) {
            Log::warning('Service IA canicule : appel impossible', [
                'path' => $path,
                'message' => $e->getMessage(),
            ]);

            return null;
        }

        return $this->decoder($reponse, $path);
    }

    /**
     * Décode une réponse HTTP en tableau, ou `null` si inexploitable.
     *
     * @param  \Illuminate\Http\Client\Response  $reponse
     * @return array<string, mixed>|null
     */
    private function decoder($reponse, string $path): ?array
    {
        if (! $reponse->successful()) {
            Log::warning('Service IA canicule : statut inattendu', [
                'path' => $path,
                'status' => $reponse->status(),
            ]);

            return null;
        }

        $json = $reponse->json();

        if (! is_array($json)) {
            Log::warning('Service IA canicule : réponse JSON invalide', ['path' => $path]);

            return null;
        }

        return $json;
    }

    /**
     * @return array<string, string>
     */
    private function headers(): array
    {
        return ['X-AI-TOKEN' => (string) config('ai.token', '')];
    }

    private function url(string $path): string
    {
        return rtrim((string) config('ai.service_url', ''), '/').'/'.ltrim($path, '/');
    }
}
