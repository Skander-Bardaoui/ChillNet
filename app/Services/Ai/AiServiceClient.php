<?php

namespace App\Services\Ai;

/**
 * Client HTTP vers le microservice IA canicule (FastAPI + scikit-learn).
 *
 * Contrat strict : AUCUNE méthode ne lève jamais d'exception et aucune ne
 * bloque au-delà du timeout configuré. En cas de timeout, de connexion refusée,
 * de statut non-2xx ou de JSON invalide, la méthode renvoie `null` (ou `false`),
 * ce qui déclenche le repli « règles » côté appelant.
 */
class AiServiceClient extends AbstractAiClient
{
    protected function service(): string
    {
        return 'canicule';
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
     * @return array<int, array<string, mixed>>|null liste de résultats, dans l'ordre
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
}
