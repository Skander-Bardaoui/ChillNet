<?php

namespace App\Services\Ai;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Client HTTP vers les endpoints « coupures » du microservice IA
 * (`/coupures/*`), chemin distinct de `AiServiceClient` (module canicule) pour
 * ne pas polluer son contrat.
 *
 * Même règle d'or : AUCUNE méthode ne lève jamais et aucune ne bloque au-delà
 * du timeout. Timeout, connexion refusée, statut non-2xx ou JSON invalide →
 * `null`, ce qui déclenche le repli « règles » côté `CoupureRiskAiService`.
 *
 * Chaque requête porte l'en-tête `X-AI-TOKEN` (clé partagée avec le service).
 */
class CoupureAiClient
{
    public function enabled(): bool
    {
        return (bool) config('ai.enabled', true)
            && trim((string) config('ai.service_url', '')) !== '';
    }

    /**
     * POST /coupures/risk — score de risque d'une ou plusieurs zones.
     *
     * @param  array<int, array<string, mixed>>  $zones
     * @return array<int, array<string, mixed>>|null
     */
    public function risk(array $zones): ?array
    {
        $reponse = $this->post('/coupures/risk', ['zones' => array_values($zones)]);

        if ($reponse === null) {
            return null;
        }

        return is_array($reponse['results'] ?? null) ? $reponse['results'] : null;
    }

    /**
     * POST /coupures/anomalies — incidents suspects à partir des signalements.
     *
     * @param  array<string, mixed>  $payload
     * @return array<int, array<string, mixed>>|null
     */
    public function anomalies(array $payload): ?array
    {
        $reponse = $this->post('/coupures/anomalies', $payload);

        if ($reponse === null) {
            return null;
        }

        return is_array($reponse['incidents'] ?? null) ? $reponse['incidents'] : null;
    }

    /**
     * POST /coupures/etr — temps de rétablissement estimé des coupures en cours.
     *
     * @param  array<int, array<string, mixed>>  $coupures
     * @return array<int, array<string, mixed>>|null
     */
    public function etr(array $coupures): ?array
    {
        $reponse = $this->post('/coupures/etr', ['coupures' => array_values($coupures)]);

        if ($reponse === null) {
            return null;
        }

        return is_array($reponse['results'] ?? null) ? $reponse['results'] : null;
    }

    /**
     * GET /coupures/insights — métriques, dépendances et importances.
     *
     * @return array<string, mixed>|null
     */
    public function insights(): ?array
    {
        return $this->get('/coupures/insights');
    }

    /**
     * POST /retrain-coupures — ré-entraîne le modèle coupures (artefacts dédiés).
     *
     * @return array<string, mixed>|null
     */
    public function retrain(): ?array
    {
        return $this->post(
            '/retrain-coupures',
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
            Log::warning('Service IA coupures : appel impossible', [
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
            Log::warning('Service IA coupures : appel impossible', [
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
            Log::warning('Service IA coupures : statut inattendu', [
                'path' => $path,
                'status' => $reponse->status(),
            ]);

            return null;
        }

        $json = $reponse->json();

        if (! is_array($json)) {
            Log::warning('Service IA coupures : réponse JSON invalide', ['path' => $path]);

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
