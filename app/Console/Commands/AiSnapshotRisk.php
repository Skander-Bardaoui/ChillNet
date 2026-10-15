<?php

namespace App\Console\Commands;

use App\Models\CoupureRiskSnapshot;
use App\Models\Quartier;
use App\Services\CoupureRiskAiService;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Log;

/**
 * Capture horaire du risque IA par zone (module 2 — coupures).
 *
 * Pour chaque quartier géolocalisé, on fige le CONTEXTE BRUT (features du
 * modèle) et la prédiction courante (p24/p48, score, version). Ces lignes
 * seront labellisées a posteriori par `ai:label-snapshots` puis réinjectées
 * dans l'entraînement (`ai:export-coupures`).
 */
class AiSnapshotRisk extends Command
{
    protected $signature = 'ai:snapshot-risk {--dry-run : Capture à blanc, sans écrire}';

    protected $description = 'Capture le risque de coupure IA par zone pour la boucle de retour d\'expérience.';

    public function __construct(private CoupureRiskAiService $ia)
    {
        parent::__construct();
    }

    public function handle(): int
    {
        $dry = (bool) $this->option('dry-run');
        $maintenant = Carbon::now();

        $quartiers = Quartier::query()
            ->whereNotNull('latitude')
            ->whereNotNull('longitude')
            ->get();

        if ($quartiers->isEmpty()) {
            $this->warn('Aucun quartier géolocalisé : rien à capturer.');

            return self::SUCCESS;
        }

        $captures = 0;

        foreach ($quartiers as $quartier) {
            try {
                $capture = $this->ia->capture($quartier);
            } catch (\Throwable $e) {
                Log::warning('Snapshot IA coupures : capture impossible', [
                    'quartier_id' => $quartier->id,
                    'message' => $e->getMessage(),
                ]);

                continue;
            }

            $score = $capture['score'];

            if ($dry) {
                $this->line(sprintf(
                    '[dry-run] %s → score %d (p24 %s, source %s)',
                    $quartier->nom,
                    (int) $score['score'],
                    $score['p24'] !== null ? number_format((float) $score['p24'], 3, ',', '') : 'n/a',
                    $score['source'],
                ));
                $captures++;

                continue;
            }

            CoupureRiskSnapshot::create([
                'quartier_id' => $quartier->id,
                'captured_at' => $maintenant,
                'features' => $capture['features'],
                'p24' => $score['p24'],
                'p48' => $score['p48'],
                'score' => (int) $score['score'],
                'model_version' => $score['model_version'],
            ]);

            $captures++;
        }

        $this->info(sprintf(
            '%d snapshot(s) %s.',
            $captures,
            $dry ? 'capturé(s) (dry-run)' : 'enregistré(s)',
        ));

        return self::SUCCESS;
    }
}
