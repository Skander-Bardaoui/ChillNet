<?php

namespace App\Services;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Géocodage inverse (coordonnées → adresse) via Nominatim / OpenStreetMap.
 *
 * Sert à pré-remplir automatiquement les champs « adresse » (Mes lieux, points
 * de fraîcheur) et « rue / quartier » (formulaire de coupure) quand on pose ou
 * déplace un point : les valeurs restent toujours modifiables.
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
        $json = $this->rechercher($latitude, $longitude);

        if ($json === null) {
            return null;
        }

        return $this->composerAdresse($json['address'] ?? [], $json['display_name'] ?? null);
    }

    /**
     * Composants de localité d'un point : nom de quartier, ville, code postal.
     * Sert à pré-remplir un quartier créé à la volée depuis la carte.
     *
     * @return array{nom: ?string, ville: ?string, code_postal: ?string}|null
     */
    public function localiteInverse(?float $latitude, ?float $longitude): ?array
    {
        $json = $this->rechercher($latitude, $longitude);

        if ($json === null) {
            return null;
        }

        $composants = $json['address'] ?? [];

        $ville = $this->premier($composants, ['city', 'town', 'village', 'municipality']);
        $nom = $this->premier($composants, ['suburb', 'neighbourhood', 'quarter', 'city_district', 'hamlet']) ?? $ville;
        $codePostal = $this->premier($composants, ['postcode']);

        if ($nom === null && $ville === null && $codePostal === null) {
            return null;
        }

        return ['nom' => $nom, 'ville' => $ville, 'code_postal' => $codePostal];
    }

    /**
     * Réponse Nominatim brute (mise en cache), ou null si indisponible.
     *
     * @return array<string, mixed>|null
     */
    private function rechercher(?float $latitude, ?float $longitude): ?array
    {
        if ($latitude === null || $longitude === null) {
            return null;
        }

        $baseUrl = rtrim((string) config('services.nominatim.base_url', 'https://nominatim.openstreetmap.org'), '/');

        // Une même rue est rejouée à chaque clic : on mémorise le résultat.
        $cacheKey = sprintf('geocode:reverse:%s:%s', round($latitude, 4), round($longitude, 4));

        $memoire = Cache::get($cacheKey);

        if (is_array($memoire)) {
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

        Cache::put($cacheKey, $json, now()->addDay());

        return $json;
    }

    /**
     * Première valeur textuelle non vide parmi les clés indiquées.
     *
     * @param  array<string, mixed>  $composants
     * @param  list<string>  $cles
     */
    private function premier(array $composants, array $cles): ?string
    {
        foreach ($cles as $cle) {
            $valeur = $composants[$cle] ?? null;

            if (is_string($valeur) && trim($valeur) !== '') {
                return trim($valeur);
            }
        }

        return null;
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