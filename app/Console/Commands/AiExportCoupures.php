<?php

namespace App\Console\Commands;

use App\Enums\StatutCoupure;
use App\Models\Coupure;
use App\Models\CoupureRiskSnapshot;
use Illuminate\Console\Command;

/**
 * Exporte les exemples RÉELS du module 2 vers les CSV consommés par
 * l'entraînement Python (`ai-service/scripts/train_coupures.py`).
 *
 * Deux fichiers, distincts des données simulées (``coupures_history.csv``) pour
 * ne JAMAIS les écraser :
 *   - ``coupures_real.csv``     : snapshots labellisés (features + labels 24/48 h) ;
 *   - ``coupures_etr_real.csv`` : coupures résolues (durée réelle observée).
 *
 * Les lignes réelles sont sur-pondérées (×5) à l'entraînement.
 */
class AiExportCoupures extends Command
{
    protected $signature = 'ai:export-coupures {--dir= : Dossier de sortie (défaut ai-service/data)}';

    protected $description = 'Exporte les snapshots labellisés et coupures résolues vers les CSV réels d\'entraînement.';

    /**
     * Ordre EXACT des colonnes de features (identique à ``features.VECTOR_ORDER``
     * suivi des labels et de la source).
     *
     * @var list<string>
     */
    private const COLONNES = [
        'zone_id', 'timestamp',
        'coupures_7j', 'coupures_14j', 'part_chaleur_14j', 'heures_depuis_derniere',
        'en_cours', 'confirmations_total',
        'temp', 'ressentie', 'heat_index', 'max_24h', 'max_48h', 'canicule_niveau',
        'jours_chauds_consecutifs', 'heure', 'jour_semaine', 'nb_logements_zone', 'nb_residences',
        'label_24h', 'label_48h', 'source',
    ];

    public function handle(): int
    {
        $dossier = (string) ($this->option('dir') ?: base_path('ai-service/data'));

        if (! is_dir($dossier) && ! @mkdir($dossier, 0755, true) && ! is_dir($dossier)) {
            $this->error("Dossier introuvable et impossible à créer : {$dossier}");

            return self::FAILURE;
        }

        $risque = $this->exporteRisque($dossier.'/coupures_real.csv');
        $etr = $this->exporteEtr($dossier.'/coupures_etr_real.csv');

        if ($risque === null) {
            return self::FAILURE;
        }

        $this->info(sprintf('%d exemple(s) de risque exporté(s) → coupures_real.csv', $risque));
        $this->info(sprintf('%d coupure(s) résolue(s) exportée(s) → coupures_etr_real.csv', $etr ?? 0));

        return self::SUCCESS;
    }

    /**
     * Snapshots entièrement labellisés → lignes de features + labels.
     */
    private function exporteRisque(string $chemin): ?int
    {
        $handle = fopen($chemin, 'w');

        if ($handle === false) {
            $this->error("Impossible d'écrire : {$chemin}");

            return null;
        }

        fputcsv($handle, self::COLONNES);

        $total = 0;

        CoupureRiskSnapshot::query()
            ->labelises()
            ->whereNotNull('features')
            ->orderBy('captured_at')
            ->chunk(500, function ($snapshots) use ($handle, &$total): void {
                foreach ($snapshots as $snapshot) {
                    fputcsv($handle, $this->ligneRisque($snapshot));
                    $total++;
                }
            });

        fclose($handle);

        return $total;
    }

    /**
     * @return list<int|float|string|null>
     */
    private function ligneRisque(CoupureRiskSnapshot $snapshot): array
    {
        $f = is_array($snapshot->features) ? $snapshot->features : [];

        $valeur = static fn (string $cle, mixed $defaut = 0): mixed => $f[$cle] ?? $defaut;

        return [
            $snapshot->quartier_id,
            $snapshot->captured_at?->format('Y-m-d H:i:s'),
            $valeur('coupures_7j'),
            $valeur('coupures_14j'),
            $valeur('part_chaleur_14j', 0.0),
            $valeur('heures_depuis_derniere', 336),
            $valeur('en_cours'),
            $valeur('confirmations_total'),
            $valeur('temperature', 25.0),
            $valeur('ressentie', 25.0),
            $valeur('heat_index', 25.0),
            $valeur('max_24h', 25.0),
            $valeur('max_48h', 25.0),
            $valeur('canicule_niveau', 0),
            $valeur('jours_chauds_consecutifs', 0),
            $valeur('heure', 12),
            $valeur('jour_semaine', 0),
            $valeur('nb_logements_zone', 0),
            $valeur('nb_residences', 0),
            $snapshot->outcome_24h ? 1 : 0,
            $snapshot->outcome_48h ? 1 : 0,
            'reel',
        ];
    }

    /**
     * Coupures résolues → durée observée (features enrichies par le snapshot le
     * plus proche pour la température, best-effort).
     */
    private function exporteEtr(string $chemin): ?int
    {
        $handle = fopen($chemin, 'w');

        if ($handle === false) {
            $this->error("Impossible d'écrire : {$chemin}");

            return null;
        }

        fputcsv($handle, [
            'zone_id', 'timestamp', 'type', 'heure', 'jour_semaine',
            'temp', 'heat_index', 'charge_zone', 'confirmations', 'duration_min', 'source',
        ]);

        $total = 0;

        Coupure::query()
            ->where('statut', StatutCoupure::Resolue->value)
            ->whereNotNull('fin')
            ->whereNotNull('debut')
            ->orderBy('debut')
            ->chunk(500, function ($coupures) use ($handle, &$total): void {
                foreach ($coupures as $coupure) {
                    if ($coupure->fin === null || $coupure->debut === null) {
                        continue;
                    }

                    $minutes = (float) $coupure->debut->diffInMinutes($coupure->fin);

                    if ($minutes <= 0) {
                        continue;
                    }

                    $meteo = $this->meteoPourCoupure($coupure);

                    fputcsv($handle, [
                        $coupure->quartier_id,
                        $coupure->debut->format('Y-m-d H:i:s'),
                        $coupure->type?->value,
                        (int) $coupure->debut->hour,
                        (int) $coupure->debut->dayOfWeekIso - 1,
                        $meteo['temp'],
                        $meteo['heat_index'],
                        $meteo['charge_zone'],
                        (int) $coupure->confirmations,
                        round($minutes, 2),
                        'reel',
                    ]);
                    $total++;
                }
            });

        fclose($handle);

        return $total;
    }

    /**
     * Température la plus proche disponible (snapshot de la même zone capturé
     * avant la coupure, dans les 6 h) ; à défaut, valeurs sûres par défaut.
     *
     * @return array{temp: float, heat_index: float, charge_zone: float}
     */
    private function meteoPourCoupure(Coupure $coupure): array
    {
        $snapshot = null;

        if ($coupure->quartier_id !== null && $coupure->debut !== null) {
            $snapshot = CoupureRiskSnapshot::query()
                ->where('quartier_id', $coupure->quartier_id)
                ->where('captured_at', '<=', $coupure->debut)
                ->where('captured_at', '>=', $coupure->debut->copy()->subHours(6))
                ->orderByDesc('captured_at')
                ->first();
        }

        $features = $snapshot && is_array($snapshot->features) ? $snapshot->features : [];

        $temp = (float) ($features['temperature'] ?? 25.0);
        $heat = (float) ($features['heat_index'] ?? $temp);
        $charge = (float) ($features['en_cours'] ?? 0) + (float) ($features['part_chaleur_14j'] ?? 0.0);

        return ['temp' => $temp, 'heat_index' => $heat, 'charge_zone' => $charge];
    }
}
