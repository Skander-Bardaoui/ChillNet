<?php

namespace App\Services\Ai;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Client HTTP vers les endpoints « fraîcheur » du microservice IA
 * (`/fraicheur/*`), chemin distinct des modules canicule et coupures.
 *
 * Même règle d'or : AUCUNE méthode ne lève jamais et aucune ne bloque au-delà
 * du timeout. Timeout, connexion refusée, statut non-2xx ou JSON invalide →
 * `null`, ce qui déclenche le repli « règles » côté `SantePointService`.
 *
 * Chaque requête porte l'en-tête `X-AI-TOKEN` (clé partagée avec le service).
 */
class FraicheurAiClient
{
    public function enabled(): bool
    {
        return (bool) config('ai.enabled', true)
            && trim((string) config('ai.service_url', '')) !== '';
    }

    /**
     * POST /fraicheur/sante — santé prédite (14 j) d'un ou plusieurs points.
     *
     * @param  array<int, array<string, mixed>>  $points
     * @return array<int, array<string, mixed>>|null
     */
    public function sante(array $points): ?array
    {
        $reponse = $this->post('/fraicheur/sante', ['points' => array_values($points)]);

        if ($reponse === null) {
            return null;
        }

        return is_array($reponse['results'] ?? null) ? $reponse['results'] : null;
    }

    /**
     * GET /fraicheur/insights — métriques, importances et évaluation du modèle.
     *
     * @return array<string, mixed>|null
     */
    public function insights(): ?array
    {
        return $this->get('/fraicheur/insights');
    }

    /**
     * POST /retrain-fraicheur — ré-entraîne le modèle du module 3 (artefacts dédiés).
     *
     * @return array<string, mixed>|null
     */
    public function retrain(): ?array
    {
        return $this->post(
            '/retrain-fraicheur',
            [],
            (float) env('AI_SERVICE_RETRAIN_TIMEOUT', 300.0),
        );
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
            Log::warning('Service IA fraîcheur : appel impossible', [
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
            Log::warning('Service IA fraîcheur : appel impossible', [
                'path' => $path,
                'message' => $e->getMessage(),
            ]);

            return null;
        }

        return $this->decoder($reponse, $path);
    }

    /**
     * @param  \Illuminate\Http\Client\Response  $reponse
     * @return array<string, mixed>|null
     */
    private function decoder($reponse, string $path): ?array
    {
        if (! $reponse->successful()) {
            Log::warning('Service IA fraîcheur : statut inattendu', [
                'path' => $path,
                'status' => $reponse->status(),
            ]);

            return null;
        }

        $json = $reponse->json();

        if (! is_array($json)) {
            Log::warning('Service IA fraîcheur : réponse JSON invalide', ['path' => $path]);

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
