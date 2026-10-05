<?php

namespace App\Services;

use App\Models\Quartier;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Client WeatherAPI.com (seul endroit du projet qui appelle la météo).
 *
 * Toute erreur (clé absente, coordonnées manquantes, réseau, payload
 * inattendu) renvoie `null` : l'appelant retombe alors sur la saisie
 * manuelle. Cette classe ne lève jamais d'exception.
 */
class WeatherService
{
    /**
     * Relevé courant pour des coordonnées, ou null si indisponible.
     */
    public function actuel(?float $latitude, ?float $longitude): ?MeteoActuelle
    {
        $key = config('services.weather.key');
        $baseUrl = rtrim((string) config('services.weather.base_url', 'https://api.weatherapi.com/v1'), '/');

        if (empty($key) || $latitude === null || $longitude === null) {
            return null;
        }

        try {
            $response = Http::timeout(6)
                ->retry(1, 300)
                ->get($baseUrl.'/current.json', [
                    'key' => $key,
                    'q' => $latitude.','.$longitude,
                    'lang' => 'fr',
                    'aqi' => 'no',
                ]);
        } catch (\Throwable $e) {
            Log::warning('WeatherAPI : appel impossible', ['message' => $e->getMessage()]);

            return null;
        }

        if ($response->failed()) {
            Log::warning('WeatherAPI : réponse en erreur', ['status' => $response->status()]);

            return null;
        }

        $json = $response->json();

        if (! is_array($json) || ! isset($json['current']['temp_c'])) {
            Log::warning('WeatherAPI : payload inattendu', ['body' => $response->body()]);

            return null;
        }

        return MeteoActuelle::fromWeatherApi($json);
    }

    /**
     * Relevé courant mémorisé un court instant (page « Mes lieux » : plusieurs
     * lieux déclenchent autant d'appels). Seuls les succès sont conservés :
     * une panne transitoire n'est jamais figée, l'appel suivant retente.
     */
    public function actuelCache(?float $latitude, ?float $longitude, int $minutes = 20): ?MeteoActuelle
    {
        if ($latitude === null || $longitude === null) {
            return null;
        }

        $cacheKey = sprintf('weather:current:%s:%s', round($latitude, 3), round($longitude, 3));

        $memoire = Cache::get($cacheKey);

        if ($memoire instanceof MeteoActuelle) {
            return $memoire;
        }

        $releve = $this->actuel($latitude, $longitude);

        if ($releve !== null) {
            Cache::put($cacheKey, $releve, now()->addMinutes($minutes));
        }

        return $releve;
    }

    /**
     * Relevé pour un quartier géolocalisé (null s'il n'a pas de coordonnées).
     */
    public function actuelPourQuartier(Quartier $quartier): ?MeteoActuelle
    {
        if (! $quartier->hasCoordinates()) {
            return null;
        }

        return $this->actuel($quartier->latitude, $quartier->longitude);
    }

    /**
     * Relevé unique pour une alerte multi-quartiers : on moyenne les
     * coordonnées des quartiers géolocalisés.
     *
     * @param  iterable<Quartier>  $quartiers
     */
    public function actuelPourQuartiers(iterable $quartiers): ?MeteoActuelle
    {
        $geo = collect($quartiers)
            ->filter(fn ($quartier): bool => $quartier instanceof Quartier && $quartier->hasCoordinates());

        if ($geo->isEmpty()) {
            return null;
        }

        return $this->actuel((float) $geo->avg('latitude'), (float) $geo->avg('longitude'));
    }

    /**
     * Prévision horaire pour les prochaines heures (par défaut 24 h, jusqu'à 48 h).
     * Toute erreur renvoie un tableau vide : la timeline de l'espace habitant
     * bascule alors sur un état vide, sans jamais lever d'exception.
     *
     * `$jours` est déduit de `$heures` (au moins 2 : la fenêtre franchit
     * minuit) et peut être forcé si l'appelant a besoin d'une fenêtre précise.
     *
     * Seuls les succès sont mémorisés (comme `actuelCache`) : une panne
     * transitoire n'est jamais figée, l'appel suivant retente.
     *
     * @return array<int, MeteoHoraire>
     */
    public function previsions(?float $latitude, ?float $longitude, int $heures = 24, ?int $jours = null): array
    {
        $key = config('services.weather.key');
        $baseUrl = rtrim((string) config('services.weather.base_url', 'https://api.weatherapi.com/v1'), '/');

        if (empty($key) || $latitude === null || $longitude === null || $heures < 1) {
            return [];
        }

        $jours ??= max(2, (int) ceil($heures / 24) + 1);

        $cacheKey = sprintf('weather:forecast:%s:%s:%d:%d', round($latitude, 3), round($longitude, 3), $heures, $jours);

        $memoire = Cache::get($cacheKey);

        if (is_array($memoire)) {
            return $memoire;
        }

        $previsions = (function () use ($baseUrl, $key, $latitude, $longitude, $heures, $jours): array {
            try {
                $response = Http::timeout(6)
                    ->retry(1, 300)
                    ->get($baseUrl.'/forecast.json', [
                        'key' => $key,
                        'q' => $latitude.','.$longitude,
                        // Assez de jours pour couvrir la fenêtre demandée.
                        'days' => $jours,
                        'lang' => 'fr',
                        'aqi' => 'no',
                        'tz' => 'auto',
                    ]);
            } catch (\Throwable $e) {
                Log::warning('WeatherAPI : prévision impossible', ['message' => $e->getMessage()]);

                return [];
            }

            if ($response->failed()) {
                Log::warning('WeatherAPI : prévision en erreur', ['status' => $response->status()]);

                return [];
            }

            $json = $response->json();
            $jours = $json['forecast']['forecastday'] ?? null;

            if (! is_array($jours)) {
                Log::warning('WeatherAPI : payload de prévision inattendu', ['body' => $response->body()]);

                return [];
            }

            // L'application est en UTC : on lit les epochs dans le fuseau du
            // lieu, sinon les heures affichées seraient décalées.
            $fuseau = $json['location']['tz_id'] ?? config('app.timezone');
            $maintenant = now()->timestamp;

            $points = [];
            foreach ($jours as $jour) {
                foreach (($jour['hour'] ?? []) as $heureBrute) {
                    if (! isset($heureBrute['time_epoch'])) {
                        continue;
                    }

                    $epoch = (int) $heureBrute['time_epoch'];

                    // On ne garde que les heures à venir.
                    if ($epoch < $maintenant) {
                        continue;
                    }

                    $points[$epoch] = MeteoHoraire::fromWeatherApi(
                        $heureBrute,
                        Carbon::createFromTimestamp($epoch, $fuseau),
                    );
                }
            }

            ksort($points);

            return array_slice(array_values($points), 0, $heures);
        })();

        if ($previsions !== []) {
            Cache::put($cacheKey, $previsions, now()->addMinutes(30));
        }

        return $previsions;
    }
}
