<?php

namespace App\Services\Ai;

/**
 * Client dédié au classifieur de catégories du module signalements.
 */
class SignalementAiClient extends AbstractAiClient
{
    protected function service(): string
    {
        return 'signalements';
    }

    /**
     * @return array<string, mixed>|null
     */
    public function classify(string $description): ?array
    {
        return $this->post('/signalements/classify', ['description' => $description]);
    }

    /**
     * @return array<string, mixed>|null
     */
    public function retrain(): ?array
    {
        return $this->post('/retrain-signalements', [], (float) env('AI_SERVICE_RETRAIN_TIMEOUT', 300.0));
    }
}
