<?php

namespace App\Console\Commands;

use App\Services\Ai\AiServiceClient;
use Illuminate\Console\Command;

/**
 * Ré-entraîne le modèle du microservice Python : exporte d'abord les
 * corrections gestionnaire vers le CSV partagé, puis déclenche
 * `POST /retrain` (protégé par X-AI-TOKEN), qui entraîne en tâche de fond et
 * recharge le modèle à chaud.
 *
 * Ne fait rien d'autre : l'entraînement lui-même vit dans ai-service/scripts.
 */
class AiRetrain extends Command
{
    protected $signature = 'ai:retrain';

    protected $description = 'Exporte les corrections puis demande au microservice Python de ré-entraîner le modèle.';

    public function __construct(private AiServiceClient $client)
    {
        parent::__construct();
    }

    public function handle(): int
    {
        if (! $this->client->enabled()) {
            $this->error('Service IA désactivé (AI_SERVICE_ENABLED=false) ou AI_SERVICE_URL absente.');

            return self::FAILURE;
        }

        $this->call('ai:export-corrections');

        $this->line('Demande de ré-entraînement au microservice…');

        $resultat = $this->client->retrain();

        if ($resultat === null) {
            $this->error('Le microservice n\'a pas accepté la demande (indisponible ou refusée).');

            return self::FAILURE;
        }

        $this->info(sprintf(
            'Ré-entraînement lancé : %s',
            $resultat['status'] ?? $resultat['message'] ?? 'ok',
        ));

        return self::SUCCESS;
    }
}
