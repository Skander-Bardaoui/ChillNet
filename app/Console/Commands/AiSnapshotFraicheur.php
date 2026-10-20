<?php

namespace App\Console\Commands;

use App\Models\PointFraicheur;
use App\Models\PointSanteSnapshot;
use App\Services\SantePointService;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;

/**
 * Capture QUOTIDIENNE de la santé prédite des points de fraîcheur validés
 * (module 3).
 *
 * Pour chaque point, on fige le CONTEXTE BRUT (features du modèle : point +
 * avis + pression thermique) et la prédiction courante (p14, score, niveau,
 * version). Ces lignes seront labellisées 14 jours plus tard par
 * `ai:label-fraicheur`, puis réinjectées dans l'entraînement (`ai:export-fraicheur`).
 * Une seule capture par point et par jour.
 */
class AiSnapshotFraicheur extends Command
{
    protected $signature = 'ai:snapshot-fraicheur {--dry-run : Capture à blanc, sans écrire}';

    protected $description = 'Capture la santé prédite (IA) de chaque point de fraîcheur pour la boucle de retour d\'expérience.';

    public function __construct(private SantePointService $sante)
    {
        parent::__construct();
    }

    public function handle(): int
    {
        $dry = (bool) $this->option('dry-run');
        $maintenant = Carbon::now();

        $deja = PointSanteSnapshot::query()
            ->whereDate('captured_at', $maintenant->toDateString())
            ->pluck('point_fraicheur_id')
            ->all();

        $points = PointFraicheur::query()->valides()->whereNotIn('id', $deja)->get();

        if ($points->isEmpty()) {
            $this->warn('Aucun point validé à capturer aujourd\'hui.');

            return self::SUCCESS;
        }

        $resultats = $this->sante->evaluerPlusieurs($points, $maintenant);

        foreach ($resultats as $resultat) {
            if ($dry) {
                $this->line(sprintf(
                    '[dry-run] %s → %s (score %d, p14 %s, source %s)',
                    $resultat['point']->nom,
                    $resultat['niveau']->label(),
                    $resultat['score'],
                    $resultat['p14'] !== null ? number_format($resultat['p14'], 3, ',', '') : 'n/a',
                    $resultat['source'],
                ));

                continue;
            }

            PointSanteSnapshot::create([
                'point_fraicheur_id' => $resultat['point']->id,
                'captured_at' => $maintenant,
                'features' => $resultat['contexte'],
                'p14' => $resultat['p14'],
                'score' => $resultat['score'],
                'niveau' => $resultat['niveau'],
                'source' => $resultat['source'],
                'model_version' => $resultat['model_version'],
            ]);
        }

        $this->info(sprintf(
            '%d snapshot(s) %s.',
            $resultats->count(),
            $dry ? 'capturé(s) (dry-run)' : 'enregistré(s)',
        ));

        return self::SUCCESS;
    }
}
