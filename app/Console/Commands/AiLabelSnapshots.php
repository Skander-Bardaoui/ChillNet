<?php

namespace App\Console\Commands;

use App\Models\Coupure;
use App\Models\CoupureRiskSnapshot;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;

/**
 * Labellisation a posteriori des snapshots de risque (module 2 — coupures).
 *
 * Pour chaque snapshot dont la fenêtre d'observation est écoulée, on inscrit le
 * RÉSULTAT RÉEL : une coupure a-t-elle DÉMARRÉ dans `(captured_at, captured_at
 * + 24 h / 48 h]` pour la même zone ? La borne est DEMI-OUVERTE à gauche pour ne
 * pas compter une coupure démarrée exactement au timestamp de la capture (celle
 * qui a motivé le snapshot).
 */
class AiLabelSnapshots extends Command
{
    protected $signature = 'ai:label-snapshots';

    protected $description = 'Fixe le résultat réel (24 h / 48 h) des snapshots de risque IA.';

    public function handle(): int
    {
        $maintenant = Carbon::now();

        $snapshots = CoupureRiskSnapshot::query()
            ->nonLabelises()
            ->whereNotNull('quartier_id')
            ->get();

        $labellises = 0;
        $maj = 0;

        foreach ($snapshots as $snapshot) {
            $capture = $snapshot->captured_at;

            if ($capture === null) {
                continue;
            }

            $modifie = false;

            if ($snapshot->outcome_24h === null) {
                $horizon = $capture->copy()->addHours(24);

                if ($maintenant->greaterThanOrEqualTo($horizon)) {
                    $snapshot->outcome_24h = $this->coupureDans($snapshot->quartier_id, $capture, $horizon);
                    $modifie = true;
                }
            }

            if ($snapshot->outcome_48h === null) {
                $horizon = $capture->copy()->addHours(48);

                if ($maintenant->greaterThanOrEqualTo($horizon)) {
                    $snapshot->outcome_48h = $this->coupureDans($snapshot->quartier_id, $capture, $horizon);
                    $modifie = true;
                }
            }

            if (! $modifie) {
                continue;
            }

            if ($snapshot->outcome_24h !== null && $snapshot->outcome_48h !== null) {
                $snapshot->labeled_at = $maintenant;
                $labellises++;
            }

            $snapshot->save();
            $maj++;
        }

        $this->info(sprintf(
            '%d snapshot(s) mis à jour, %d désormais entièrement labellisé(s).',
            $maj,
            $labellises,
        ));

        return self::SUCCESS;
    }

    /**
     * Une coupure a-t-elle démarré dans `(captured_at, horizon]` pour la zone ?
     */
    private function coupureDans(?int $quartierId, Carbon $apres, Carbon $horizon): bool
    {
        if ($quartierId === null) {
            return false;
        }

        return Coupure::query()
            ->where('quartier_id', $quartierId)
            ->where('debut', '>', $apres)
            ->where('debut', '<=', $horizon)
            ->exists();
    }
}
