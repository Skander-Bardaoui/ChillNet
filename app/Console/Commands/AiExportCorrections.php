<?php

namespace App\Console\Commands;

use App\Models\AiPrediction;
use Illuminate\Console\Command;

/**
 * Exporte les corrections gestionnaire (niveau final retenu vs niveau suggéré)
 * vers un CSV consommé par l'entraînement Python (ai-service/scripts/train.py).
 *
 * Chaque ligne = un CONTEXTE BRUT + le niveau réellement publié. Ces exemples
 * sont sur-pondérés à l'entraînement (voir `corrections_weight`).
 */
class AiExportCorrections extends Command
{
    protected $signature = 'ai:export-corrections {--path= : Chemin de sortie du CSV}';

    protected $description = 'Exporte les corrections IA (niveau final) vers un CSV pour le ré-entraînement Python.';

    public function handle(): int
    {
        $chemin = (string) ($this->option('path') ?: base_path('ai-service/data/corrections.csv'));
        $dossier = dirname($chemin);

        if (! is_dir($dossier) && ! @mkdir($dossier, 0755, true) && ! is_dir($dossier)) {
            $this->error("Dossier introuvable et impossible à créer : {$dossier}");

            return self::FAILURE;
        }

        $corrections = AiPrediction::query()
            ->corrigees()
            ->orderBy('id')
            ->get();

        $handle = fopen($chemin, 'w');

        if ($handle === false) {
            $this->error("Impossible d'écrire : {$chemin}");

            return self::FAILURE;
        }

        fputcsv($handle, [
            'date', 'temp_max', 'ressentie', 'humidite',
            'seuil_temperature', 'jours_surchauffe', 'mois', 'niveau',
        ]);

        foreach ($corrections as $prediction) {
            $contexte = is_array($prediction->context) ? $prediction->context : [];
            $historique = is_array($contexte['historique'] ?? null) ? $contexte['historique'] : [];

            fputcsv($handle, [
                $prediction->updated_at?->format('Y-m-d') ?? now()->format('Y-m-d'),
                $this->valeur($contexte, 'temperature'),
                $this->valeur($contexte, 'ressentie'),
                $this->valeur($contexte, 'humidite'),
                $this->valeur($contexte, 'seuil_temperature', $this->valeur($contexte, 'seuil')),
                $historique['jours_surchauffe'] ?? 0,
                $this->valeur($contexte, 'mois', now()->month),
                (string) $prediction->niveau_final,
            ]);
        }

        fclose($handle);

        $this->info(sprintf('%d correction(s) exportée(s) vers %s.', $corrections->count(), $chemin));

        return self::SUCCESS;
    }

    /**
     * @param  array<string, mixed>  $contexte
     */
    private function valeur(array $contexte, string $cle, mixed $defaut = ''): mixed
    {
        $valeur = $contexte[$cle] ?? $defaut;

        return is_numeric($valeur) ? $valeur : ($valeur ?? '');
    }
}
