<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Builder;

/**
 * Socle des exports CSV réels consommés par l'entraînement Python : résolution
 * du dossier de sortie et écriture paginée du fichier.
 */
abstract class AbstractAiExportCommand extends Command
{
    protected function dossierSortie(): ?string
    {
        $dossier = (string) ($this->option('dir') ?: base_path('ai-service/data'));

        if (! is_dir($dossier) && ! @mkdir($dossier, 0755, true) && ! is_dir($dossier)) {
            $this->error("Dossier introuvable et impossible à créer : {$dossier}");

            return null;
        }

        return $dossier;
    }

    /**
     * @param  list<string>  $entete
     * @param  callable(object): (array<int, int|float|string|null>|null)  $ligne  `null` → ligne ignorée
     */
    protected function exporterCsv(string $chemin, array $entete, Builder $requete, callable $ligne): ?int
    {
        $handle = fopen($chemin, 'w');

        if ($handle === false) {
            $this->error("Impossible d'écrire : {$chemin}");

            return null;
        }

        fputcsv($handle, $entete);

        $total = 0;

        $requete->chunk(500, function ($elements) use ($handle, &$total, $ligne): void {
            foreach ($elements as $element) {
                $valeurs = $ligne($element);

                if ($valeurs === null) {
                    continue;
                }

                fputcsv($handle, $valeurs);
                $total++;
            }
        });

        fclose($handle);

        return $total;
    }
}
