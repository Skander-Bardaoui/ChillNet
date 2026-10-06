<?php

namespace App\Services;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Géocodage inverse (coordonnées → adresse) via Nominatim / OpenStreetMap.
 *
 * Sert uniquement à pré-remplir le champ « adresse » de « Mes lieux » quand
 * l'habitant pose ou déplace son point : l'adresse reste toujours modifiable.
 *
 * Toute erreur (réseau, quota, payload inattendu) renvoie `null` : l'appelant
 * laisse alors le champ tel quel. Cette classe ne lève jamais d'exception.
 */
class GeocodingService
{
    /**
     * Adresse lisible pour des coordonnées, ou null si indisponible.
     */
    public function adresseInverse(?float $latitude, ?float $longitude): ?string
    {
        if ($latitude === null || $longitude === null) {
            return null;
        }

        $baseUrl = rtrim((string) config('services.nominatim.base_url', 'https://nominatim.openstreetmap.org'), '/');

        // Une même rue est rejouée à chaque clic : on mémorise le résultat.
        $cacheKey = sprintf('geocode:reverse:%s:%s', round($latitude, 4), round($longitude, 4));

        $memoire = Cache::get($cacheKey);

        if (is_string($memoire)) {
            return $memoire;
        }

        try {
            $response = Http::timeout(6)
                ->retry(1, 300)
                ->withHeaders([
                    // Politique d'usage Nominatim : User-Agent identifiable.
                    'User-Agent' => (string) config('services.nominatim.user_agent', 'ChillNet/1.0'),
                    'Accept-Language' => 'fr',
                ])
                ->get($baseUrl.'/reverse', [
                    'format' => 'jsonv2',
                    'lat' => $latitude,
                    'lon' => $longitude,
                    'zoom' => 18,
                    'addressdetails' => 1,
                ]);
        } catch (\Throwable $e) {
            Log::warning('Nominatim : appel impossible', ['message' => $e->getMessage()]);

            return null;
        }

        if ($response->failed()) {
            Log::warning('Nominatim : réponse en erreur', ['status' => $response->status()]);

            return null;
        }

        $json = $response->json();

        if (! is_array($json)) {
            Log::warning('Nominatim : payload inattendu', ['body' => $response->body()]);

            return null;
        }

        $adresse = $this->composerAdresse($json['address'] ?? [], $json['display_name'] ?? null);

        if ($adresse !== null) {
            Cache::put($cacheKey, $adresse, now()->addDay());
        }

        return $adresse;
    }

    /**
     * Compose une adresse courte et lisible à partir des composants Nominatim
     * (numéro + voie, code postal + ville, pays), avec repli sur `display_name`.
     *
     * @param  array<string, mixed>  $composants
     */
    private function composerAdresse(array $composants, mixed $displayName): ?string
    {
        $numero = $composants['house_number'] ?? null;
        $voie = $composants['road'] ?? ($composants['pedestrian'] ?? ($composants['footway'] ?? null));
        $ville = $composants['city'] ?? ($composants['town'] ?? ($composants['village'] ?? ($composants['municipality'] ?? null)));
        $codePostal = $composants['postcode'] ?? null;
        $pays = $composants['country'] ?? null;

        $parties = [];

        $ligne = trim(implode(' ', array_filter([$numero, $voie])));
        if ($ligne !== '') {
            $parties[] = $ligne;
        }

        $villeLigne = trim(implode(' ', array_filter([$codePostal, $ville])));
        if ($villeLigne !== '') {
            $parties[] = $villeLigne;
        }

        if (is_string($pays) && $pays !== '') {
            $parties[] = $pays;
        }

        if ($parties === []) {
            // Aucun composant exploitable : on garde les premiers morceaux du
            // libellé complet plutôt que de ne rien proposer.
            if (is_string($displayName) && $displayName !== '') {
                return implode(', ', array_slice(array_map('trim', explode(',', $displayName)), 0, 3));
            }

            return null;
        }

        return implode(', ', $parties);
    }
}
