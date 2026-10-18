<?php

namespace App\Console\Commands;

use App\Services\Ai\SignalementAiClient;
use Illuminate\Console\Command;

class AiRetrainSignalements extends Command
{
    protected $signature = 'ai:retrain-signalements';

    protected $description = 'Demande au microservice de ré-entraîner le modèle de catégories des signalements.';

    public function __construct(private SignalementAiClient $client)
    {
        parent::__construct();
    }

    public function handle(): int
    {
        if (! (bool) config('ai.enabled', true)
            || trim((string) config('ai.service_url', '')) === '') {
            $this->error('Service IA désactivé (AI_SERVICE_ENABLED=false) ou AI_SERVICE_URL absente.');

            return self::FAILURE;
        }

        $this->line('Demande de ré-entraînement du modèle de catégories des signalements…');
        $result = $this->client->retrain();

        if ($result === null) {
            $this->error('Le microservice n\'a pas accepté la demande (indisponible ou refusée).');

            return self::FAILURE;
        }

        $this->info(sprintf(
            'Ré-entraînement signalements lancé : %s',
            $result['status'] ?? 'ok',
        ));

        return self::SUCCESS;
    }
}
