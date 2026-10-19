<?php

namespace App\Console\Commands;

use App\Enums\Sentiment;
use App\Models\Avis;
use App\Models\PointSanteSnapshot;
use App\Services\SantePointService;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;

/**
 * Labellisation a posteriori des snapshots de santé (module 3).
 *
 * Pour chaque snapshot dont l'horizon de 14 jours est écoulé, on inscrit le
 * RÉSULTAT RÉEL : le point a-t-il reçu au moins DEUX avis négatifs (note <= 2
 * ou sentiment négatif) dans `(captured_at, captured_at + 14 j]` ? Même
 * définition que la cible `label_14j` du simulateur Python.
 */
class AiLabelFraicheur extends Command
{
    protected $signature = 'ai:label-fraicheur';

    protected $description = 'Fixe le résultat réel (14 j) des snapshots de santé des points de fraîcheur.';

    public function handle(): int
    {
        $limite = Carbon::now()->subDays(SantePointService::HORIZON_JOURS);
        $labellises = 0;

        PointSanteSnapshot::query()
            ->nonLabelises()
            ->where('captured_at', '<=', $limite)
            ->orderBy('id')
            ->chunkById(500, function ($snapshots) use (&$labellises): void {
                foreach ($snapshots as $snapshot) {
                    $debut = $snapshot->captured_at;
                    $fin = $debut->copy()->addDays(SantePointService::HORIZON_JOURS);

                    $negatifs = Avis::query()
                        ->where('point_fraicheur_id', $snapshot->point_fraicheur_id)
                        ->where('created_at', '>', $debut)
                        ->where('created_at', '<=', $fin)
                        ->where(fn ($q) => $q->where('note', '<=', 2)->orWhere('sentiment', Sentiment::Negatif->value))
                        ->count();

                    $snapshot->update([
                        'outcome_14j' => $negatifs >= SantePointService::SEUIL_NEGATIFS,
                        'labeled_at' => Carbon::now(),
                    ]);

                    $labellises++;
                }
            });

        $this->info(sprintf('%d snapshot(s) labellisé(s).', $labellises));

        return self::SUCCESS;
    }
}
