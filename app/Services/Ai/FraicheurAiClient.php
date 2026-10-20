<?php

namespace App\Services\Ai;

/**
 * Client HTTP vers les endpoints « fraîcheur » du microservice IA
 * (`/fraicheur/*`), chemin distinct des modules canicule et coupures.
 *
 * Même règle d'or : AUCUNE méthode ne lève jamais et aucune ne bloque au-delà
 * du timeout. Timeout, connexion refusée, statut non-2xx ou JSON invalide →
 * `null`, ce qui déclenche le repli « règles » côté `SantePointService`.
 */
class FraicheurAiClient extends AbstractAiClient
{
    protected function service(): string
    {
        return 'fraîcheur';
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
}
