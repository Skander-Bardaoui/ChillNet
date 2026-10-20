<?php

namespace App\Services\Ai;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Client HTTP vers les endpoints « conseils / équipements » du microservice IA
 * (`/conseils/*`), chemin distinct de `AiServiceClient` (canicule) et
 * `CoupureAiClient` (coupures) pour ne pas polluer leur contrat.
 *
 * Règle d'or : AUCUNE méthode ne lève jamais et aucune ne bloque au-delà
 * du timeout. Timeout, connexion refusée, statut non-2xx ou JSON invalide →
 * `null`, ce qui déclenche le repli « règles » côté `ConseilRiskService`.
 *
 * Chaque requête porte l'en-tête `X-AI-TOKEN` (clé partagée avec le service).
 */
class ConseilAiClient
{
    public function enabled(): bool
    {
        return (bool) config('ai.enabled', true)
            && trim((string) config('ai.service_url', '')) !== '';
    }

    /**
     * POST /conseils/risk — niveau de risque d'un ou plusieurs foyers.
     *
     * @param  array<int, array<string, mixed>>  $foyers
     * @return array<int, array<string, mixed>>|null
     */
    public function risk(array $foyers): ?array
    {
        $reponse = $this->post('/conseils/risk', ['foyers' => array_values($foyers)]);

        if ($reponse === null) {
            return null;
        }

        return is_array($reponse['results'] ?? null) ? $reponse['results'] : null;
    }

    /**
     * GET /conseils/insights — métriques, importances et classes du modèle.
     *
     * @return array<string, mixed>|null
     */
    public function insights(): ?array
    {
        return $this->get('/conseils/insights');
    }

    /**
     * POST /retrain-conseils — ré-entraîne le modèle conseils (artefacts dédiés).
     *
     * @return array<string, mixed>|null
     */
    public function retrain(): ?array
    {
        return $this->post(
            '/retrain-conseils',
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
                ->timeout($timeout ?? (float) config('ai.timeout', 3.0))
                ->acceptJson()
                ->asJson()
                ->post($this->url($path), $payload);
        } catch (\Throwable $e) {
            Log::warning('Service IA conseils : appel impossible', [
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
                ->timeout((float) config('ai.timeout', 3.0))
                ->acceptJson()
                ->get($this->url($path));
        } catch (\Throwable $e) {
            Log::warning('Service IA conseils : appel impossible', [
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
            Log::warning('Service IA conseils : statut inattendu', [
                'path' => $path,
                'status' => $reponse->status(),
            ]);

            return null;
        }

        $json = $reponse->json();

        if (! is_array($json)) {
            Log::warning('Service IA conseils : réponse JSON invalide', ['path' => $path]);

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
