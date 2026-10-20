<?php

namespace App\Services\Ai;

/**
 * Client HTTP vers les endpoints « coupures » du microservice IA
 * (`/coupures/*`), chemin distinct de `AiServiceClient` (module canicule) pour
 * ne pas polluer son contrat.
 *
 * Même règle d'or : AUCUNE méthode ne lève jamais et aucune ne bloque au-delà
 * du timeout. Timeout, connexion refusée, statut non-2xx ou JSON invalide →
 * `null`, ce qui déclenche le repli « règles » côté `CoupureRiskAiService`.
 */
class CoupureAiClient extends AbstractAiClient
{
    protected function service(): string
    {
        return 'coupures';
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
}
