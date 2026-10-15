<?php

namespace App\Console\Commands;

use App\Services\Ai\CoupureAiClient;
use Illuminate\Console\Command;

/**
 * Ré-entraîne le modèle de risque du module 2 (coupures) : exporte d'abord les
 * exemples réels (snapshots labellisés + coupures résolues) vers les CSV, puis
 * déclenche `POST /retrain-coupures` côté Python (artefacts DÉDIÉS, distincts du
 * modèle canicule), qui entraîne en tâche de fond et recharge à chaud.
 */
class AiRetrainCoupures extends Command
{
    protected $signature = 'ai:retrain-coupures';

    protected $description = 'Exporte les exemples réels puis demande au microservice de ré-entraîner le modèle coupures.';

    public function __construct(private CoupureAiClient $client)
    {
        parent::__construct();
    }

    public function handle(): int
    {
        if (! $this->client->enabled()) {
            $this->error('Service IA désactivé (AI_SERVICE_ENABLED=false) ou AI_SERVICE_URL absente.');

            return self::FAILURE;
        }

        $this->call('ai:export-coupures');

        $this->line('Demande de ré-entraînement du modèle coupures…');

        $resultat = $this->client->retrain();

        if ($resultat === null) {
            $this->error('Le microservice n\'a pas accepté la demande (indisponible ou refusée).');

            return self::FAILURE;
        }

        $this->info(sprintf(
            'Ré-entraînement coupures lancé : %s',
            $resultat['status'] ?? $resultat['message'] ?? 'ok',
        ));

        return self::SUCCESS;
    }
}
