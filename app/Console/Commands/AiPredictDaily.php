<?php

namespace App\Console\Commands;

use App\Enums\NiveauAlerte;
use App\Models\AiPrediction;
use App\Models\Alerte;
use App\Models\Quartier;
use App\Services\VigilanceAiService;
use App\Services\WeatherService;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Log;

/**
 * Analyse quotidienne automatique : pour chaque quartier géolocalisé, construit
 * le contexte météo BRUT, interroge le microservice IA (avec repli règles), et
 * — si la chaleur est réelle et qu'aucune alerte de même niveau ne chevauche le
 * créneau — crée un BROUILLON d'alerte (validee = false).
 *
 * Aucune alerte n'est jamais publiée automatiquement : un gestionnaire doit la
 * valider.
 */
class AiPredictDaily extends Command
{
    protected $signature = 'ai:predict-daily {--dry-run : Analyse sans créer de brouillon}';

    protected $description = 'Génère des brouillons d\'alerte canicule pour les quartiers à risque (jamais publiés automatiquement).';

    public function __construct(
        private VigilanceAiService $ia,
        private WeatherService $meteo,
    ) {
        parent::__construct();
    }

    public function handle(): int
    {
        $seuil = $this->ia->seuilDefaut();
        $fenetre = (int) config('ai.auto_draft.window_hours', 24);
        $debut = Carbon::now();
        $fin = Carbon::now()->addHours($fenetre);
        $dry = (bool) $this->option('dry-run');

        $quartiers = Quartier::query()
            ->whereNotNull('latitude')
            ->whereNotNull('longitude')
            ->get();

        if ($quartiers->isEmpty()) {
            $this->warn('Aucun quartier géolocalisé : rien à analyser.');

            return self::SUCCESS;
        }

        $crees = 0;
        $ignores = 0;
        $calmes = 0;

        foreach ($quartiers as $quartier) {
            $meteo = $this->meteo->actuel((float) $quartier->latitude, (float) $quartier->longitude);

            if ($meteo === null) {
                $ignores++;

                continue;
            }

            $previsions = $this->meteo->previsions((float) $quartier->latitude, (float) $quartier->longitude, $fenetre);
            $historique = $this->ia->historiquePourQuartier($quartier->id);

            $contexte = [
                'temperature' => $meteo->temperature,
                'ressentie' => $meteo->ressentie,
                'humidite' => $meteo->humidite,
                'seuil' => $seuil,
                'previsions' => $previsions,
                'historique' => $historique,
                'lieu_type' => 'domicile',
                'quartier' => $quartier->nom,
            ];

            $prediction = $this->ia->predict($contexte);
            $niveau = $prediction['niveau'];

            // Pic de chaleur à venir (orchestration PHP, sans IA) : le service
            // n'est sollicité que si une vraie chaleur est prévue.
            [$picSur24h, $heurePic] = $this->picDeChaleur($previsions, $meteo->temperature);
            $risque = $prediction['niveau_final'] !== 'normal' && $picSur24h >= $seuil;

            if (! $risque) {
                $calmes++;

                continue;
            }

            if (Alerte::chevauche([$quartier->id], $debut->toDateTimeString(), $fin->toDateTimeString(), null, $niveau)) {
                $ignores++;

                continue;
            }

            if ($dry) {
                $this->line(sprintf(
                    '[dry-run] %s → %s (max 24 h %s °C)',
                    $quartier->nom,
                    $niveau->label(),
                    number_format($picSur24h, 1, ',', ''),
                ));
                $crees++;

                continue;
            }

            $this->creerBrouillon($quartier, $meteo, $prediction, $contexte, $seuil, $heurePic, $debut, $fin);
            $crees++;
        }

        $this->info(sprintf(
            '%d brouillon(s) créé(s), %d quartier(s) ignoré(s), %d quartier(s) sans chaleur marquée.%s',
            $crees,
            $ignores,
            $calmes,
            $dry ? ' (dry-run)' : '',
        ));

        return self::SUCCESS;
    }

    /**
     * Plus haute température des prochaines heures et heure du pic.
     *
     * @param  iterable<\App\Services\MeteoHoraire>  $previsions
     * @return array{0: float, 1: ?string}
     */
    private function picDeChaleur(iterable $previsions, float $temperatureActuelle): array
    {
        $max = $temperatureActuelle;
        $heurePic = null;

        foreach ($previsions as $prevision) {
            if (isset($prevision->temperature) && $prevision->temperature > $max) {
                $max = (float) $prevision->temperature;
                $heurePic = $prevision->heure?->format('H:i') ?? null;
            }
        }

        return [$max, $heurePic];
    }

    /**
     * @param  \App\Services\MeteoActuelle  $meteo
     * @param  array<string, mixed>  $prediction
     * @param  array<string, mixed>  $contexte
     */
    private function creerBrouillon(Quartier $quartier, $meteo, array $prediction, array $contexte, float $seuil, ?string $heurePic, Carbon $debut, Carbon $fin): void
    {
        /** @var NiveauAlerte $niveau */
        $niveau = $prediction['niveau'];

        $contexteMessage = [
            'titre' => 'Vigilance '.$niveau->value.' — '.$quartier->nom,
            'niveau' => $niveau->value,
            'niveau_label' => $niveau->label(),
            'quartiers' => [$quartier->nom],
            'temperature_actuelle' => round($meteo->temperature, 1),
            'temperature_ressentie' => $meteo->ressentie,
            'humidite' => $meteo->humidite,
            'heure_pic' => $heurePic,
            'lieu_type' => 'domicile',
        ];

        $alerte = Alerte::create([
            'titre' => 'Vigilance '.$niveau->value.' — '.$quartier->nom,
            'niveau' => $niveau->value,
            'seuil_temperature' => $seuil,
            'temperature_actuelle' => round($meteo->temperature, 1),
            'temperature_ressentie' => $meteo->ressentie,
            'humidite' => $meteo->humidite,
            'source_meteo' => 'weatherapi',
            'debut' => $debut,
            'fin' => $fin,
            'message' => $this->ia->messagePourContexte($contexteMessage, [], 'daily:'.$quartier->id),
            'latitude' => $quartier->latitude,
            'longitude' => $quartier->longitude,
            'rayon_metres' => Alerte::RAYON_DEFAUT_M,
            'validee' => false,
        ]);

        $alerte->quartiers()->sync([$quartier->id]);

        // Trace de la prédiction rattachée au brouillon : à la validation, le
        // niveau publié sera figé comme niveau_final (boucle de retour).
        try {
            AiPrediction::create([
                'alerte_id' => $alerte->id,
                'quartier_ids' => [$quartier->id],
                'context' => $contexte,
                'niveau_modele' => $prediction['niveau_modele']?->value,
                'probabilites' => $prediction['probabilites'] ?? [],
                'niveau_regles' => $prediction['niveau_regles']?->value,
                'niveau_final' => null,
                'model_version' => $prediction['model_version'] ?? null,
                'source' => $prediction['source'] ?? null,
            ]);
        } catch (\Throwable $e) {
            Log::warning('Trace IA canicule : enregistrement brouillon impossible', ['message' => $e->getMessage()]);
        }
    }
}
