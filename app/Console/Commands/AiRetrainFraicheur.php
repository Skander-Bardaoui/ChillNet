<?php

namespace App\Console\Commands;

use App\Services\Ai\FraicheurAiClient;
use Illuminate\Console\Command;

/**
 * Ré-entraîne le modèle de santé du module 3 (points de fraîcheur + avis) :
 * exporte d'abord les exemples réels (snapshots labellisés) vers le CSV, puis
 * déclenche `POST /retrain-fraicheur` côté Python (artefacts DÉDIÉS), qui
 * entraîne, évalue et recharge le modèle à chaud.
 */
class AiRetrainFraicheur extends Command
{
    protected $signature = 'ai:retrain-fraicheur';

    protected $description = 'Exporte les exemples réels puis demande au microservice de ré-entraîner le modèle des points de fraîcheur.';

    public function __construct(private FraicheurAiClient $client)
    {
        parent::__construct();
    }

    public function handle(): int
    {
        if (! $this->client->enabled()) {
            $this->error('Service IA désactivé (AI_SERVICE_ENABLED=false) ou AI_SERVICE_URL absente.');

            return self::FAILURE;
        }

        $this->call('ai:export-fraicheur');

        $this->line('Demande de ré-entraînement du modèle des points de fraîcheur…');

        $resultat = $this->client->retrain();

        if ($resultat === null) {
            $this->error('Le microservice n\'a pas accepté la demande (indisponible ou refusée).');

            return self::FAILURE;
        }

        $this->info(sprintf('Ré-entraînement fraîcheur lancé : %s', $resultat['status'] ?? 'ok'));

        return self::SUCCESS;
    }
}
