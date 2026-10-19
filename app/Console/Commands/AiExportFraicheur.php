<?php

namespace App\Console\Commands;

use App\Models\PointSanteSnapshot;
use Illuminate\Console\Command;

/**
 * Exporte les exemples RÉELS du module 3 vers le CSV consommé par
 * l'entraînement Python (`ai-service/scripts/train_fraicheur.py`).
 *
 * Fichier ``fraicheur_real.csv``, distinct des données simulées
 * (``fraicheur_history.csv``) pour ne JAMAIS les écraser. Les lignes réelles
 * sont sur-pondérées (×5) à l'entraînement.
 */
class AiExportFraicheur extends Command
{
    protected $signature = 'ai:export-fraicheur {--dir= : Dossier de sortie (défaut ai-service/data)}';

    protected $description = 'Exporte les snapshots de santé labellisés vers le CSV réel d\'entraînement du module 3.';

    /**
     * Ordre EXACT des features (identique à ``features.VECTOR_ORDER`` Python,
     * vérifié par la fixture partagée ``tests/fixtures/fraicheur_rules_cases.json``).
     *
     * @var list<string>
     */
    public const FEATURES = [
        'type_code', 'capacite', 'climatise', 'ombrage', 'eau_potable', 'accessible_pmr',
        'heures_ouverture_jour', 'anciennete_jours', 'a_photo',
        'note_moyenne', 'nb_avis_30j', 'note_moyenne_30j', 'evolution_note_30j', 'part_negatifs_30j',
        'jours_depuis_dernier_negatif', 'part_affluence_forte_30j', 'sentiment_moyen_30j',
        'plaintes_proprete_30j', 'plaintes_chaleur_30j', 'plaintes_equipements_30j',
        'temperature', 'canicule_niveau', 'mois',
    ];

    /**
     * Valeurs par défaut si une feature manque (identiques à ``SAFE_DEFAULTS``).
     *
     * @var array<string, int|float>
     */
    private const DEFAUTS = [
        'heures_ouverture_jour' => 12, 'anciennete_jours' => 365, 'note_moyenne' => 3.5,
        'note_moyenne_30j' => 3.5, 'jours_depuis_dernier_negatif' => 90, 'temperature' => 30.0, 'mois' => 7,
    ];

    public function handle(): int
    {
        $dossier = (string) ($this->option('dir') ?: base_path('ai-service/data'));

        if (! is_dir($dossier) && ! @mkdir($dossier, 0755, true) && ! is_dir($dossier)) {
            $this->error("Dossier introuvable et impossible à créer : {$dossier}");

            return self::FAILURE;
        }

        $chemin = $dossier.'/fraicheur_real.csv';
        $handle = fopen($chemin, 'w');

        if ($handle === false) {
            $this->error("Impossible d'écrire : {$chemin}");

            return self::FAILURE;
        }

        fputcsv($handle, ['point_id', 'date', ...self::FEATURES, 'label_14j', 'source']);

        $total = 0;

        PointSanteSnapshot::query()
            ->labelises()
            ->whereNotNull('features')
            ->orderBy('captured_at')
            ->chunk(500, function ($snapshots) use ($handle, &$total): void {
                foreach ($snapshots as $snapshot) {
                    fputcsv($handle, $this->ligne($snapshot));
                    $total++;
                }
            });

        fclose($handle);

        $this->info(sprintf('%d exemple(s) réel(s) exporté(s) → %s', $total, $chemin));

        return self::SUCCESS;
    }

    /**
     * @return list<int|float|string|null>
     */
    private function ligne(PointSanteSnapshot $snapshot): array
    {
        $features = is_array($snapshot->features) ? $snapshot->features : [];

        $valeurs = array_map(function (string $nom) use ($features): int|float {
            $valeur = $features[$nom] ?? null;

            if (is_bool($valeur)) {
                return (int) $valeur;
            }

            return is_numeric($valeur) ? $valeur + 0 : (self::DEFAUTS[$nom] ?? 0);
        }, self::FEATURES);

        return [
            $snapshot->point_fraicheur_id,
            $snapshot->captured_at?->format('Y-m-d'),
            ...$valeurs,
            $snapshot->outcome_14j ? 1 : 0,
            'reel',
        ];
    }
}
