<?php

namespace App\Http\Controllers\Front;

use App\Enums\StatutCoupure;
use App\Http\Controllers\Controller;
use App\Models\Alerte;
use App\Models\Coupure;
use App\Models\Lieu;
use App\Models\Quartier;
use App\Models\Residence;
use App\Services\VigilanceAiService;
use App\Services\WeatherService;
use App\Support\Geo;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;

/**
 * Tableau de bord de l'espace habitant.
 *
 * Les managers (admin/gestionnaire) sont redirigés vers le back office :
 * leur espace est /admin. L'habitant voit :
 *  - le bandeau de vigilance alimenté par l'alerte active la plus grave ;
 *  - la « Chronologie 24h » (météo horaire + stress électrique réel) ;
 *  - la carte OpenStreetMap des refuges / zones d'ombre dans un rayon de 800 m.
 */
class DashboardController extends Controller
{
    public function __construct(
        private VigilanceAiService $ia,
        private WeatherService $meteo,
    ) {}

    public function index(Request $request)
    {
        $user = $request->user();

        if ($user->isAdmin() || $user->isGestionnaire()) {
            return redirect()->route('back.dashboard');
        }

        // Le foyer est centré sur SES lieux (Domicile, Travail…) : le lieu
        // principal pilote le périmètre, la météo, les alertes et les coupures.
        $user->loadMissing('residence.quartier');
        $lieu = $user->lieuPrincipal();
        $residence = $user->residence;

        // Zone interne (jamais montrée) : celle du lieu, sinon de l'ancienne
        // résidence (compat habitants d'avant les lieux).
        $quartierId = $lieu?->quartier_id ?? $residence?->quartier_id;

        // Point du foyer : coordonnées du lieu, sinon de la résidence,
        // sinon centroïde du quartier interne.
        $origine = $this->origine($lieu, $residence, $quartierId);

        // Alerte active la plus grave couvrant le foyer : cercle géographique
        // OU quartier (alertes héritées).
        $candidats = Alerte::with('quartiers')
            ->validees()
            ->get()
            ->filter(fn (Alerte $alerte): bool => $alerte->estActive());

        $alerteActive = Alerte::filtrerPourLieu($candidats, $quartierId, $origine[0] ?? null, $origine[1] ?? null)
            ->sortByDesc(fn (Alerte $alerte): int => $alerte->niveau->gravite())
            ->first();

        $messagePersonnalise = null;
        if ($alerteActive) {
            $profilKey = implode('-', array_map(
                fn ($profil): string => $profil->value,
                $user->profilsVulnerabilite(),
            )) ?: 'standard';

            $messagePersonnalise = Cache::remember(
                "alerte:{$alerteActive->id}:profil:{$profilKey}",
                now()->addMinutes(30),
                fn (): string => $this->ia->messagePersonnalise($alerteActive, $user),
            );
        }

        $meteo = $origine !== null
            ? $this->meteo->actuel($origine[0], $origine[1])
            : null;

        // Planification anticipée : météo des prochaines 24 h depuis l'origine.
        $heures = $this->timeline($origine, $quartierId);
        $fenetreOptimale = $this->plageSansStress($heures);
        $plageCritique = $this->plageSousTension($heures);

        // Refuges & zones d'ombre dans le périmètre de 800 m.
        $refuges = $this->refugesProches($origine);
        $refugesOuverts = $alerteActive !== null;

        return view('front.dashboard', compact(
            'lieu', 'alerteActive', 'messagePersonnalise', 'meteo',
            'origine', 'heures', 'fenetreOptimale', 'plageCritique',
            'refuges', 'refugesOuverts',
        ));
    }

    /**
     * Point de départ du périmètre : coordonnées du lieu, sinon de la
     * résidence (compat), sinon centroïde du quartier interne, sinon null
     * (carte masquée).
     *
     * @return array{0: float, 1: float}|null
     */
    private function origine(?Lieu $lieu, ?Residence $residence, ?int $quartierId): ?array
    {
        if ($lieu?->hasCoordinates()) {
            return [(float) $lieu->latitude, (float) $lieu->longitude];
        }

        if ($residence?->hasCoordinates()) {
            return [(float) $residence->latitude, (float) $residence->longitude];
        }

        if ($quartierId !== null) {
            $quartier = Quartier::find($quartierId);

            if ($quartier?->hasCoordinates()) {
                return [(float) $quartier->latitude, (float) $quartier->longitude];
            }
        }

        return null;
    }

    /**
     * Chronologie horaire : météo live + stress électrique (coupures réelles
     * de la zone interne du lieu qui chevauchent chaque heure). Tableau vide
     * si indisponible.
     *
     * @param  array{0: float, 1: float}|null  $origine
     * @return array<int, array<string, mixed>>
     */
    private function timeline(?array $origine, ?int $quartierId): array
    {
        if ($origine === null) {
            return [];
        }

        $previsions = $this->meteo->previsions($origine[0], $origine[1]);

        if ($previsions === []) {
            return [];
        }

        $coupures = $quartierId !== null
            ? Coupure::where('quartier_id', $quartierId)
                ->whereIn('statut', [StatutCoupure::EnCours->value, StatutCoupure::Prevue->value])
                ->get()
            : new Collection;

        $seuil = (float) config('services.weather.seuil_defaut', 35);

        $heures = [];
        foreach ($previsions as $prevision) {
            $debutHeure = $prevision->heure->copy()->startOfHour();
            $finHeure = $debutHeure->copy()->addHour();

            $stress = $coupures->contains(function (Coupure $coupure) use ($debutHeure, $finHeure): bool {
                if (! $coupure->debut) {
                    return false;
                }

                // Une coupure sans fin (ponctuelle) occupe 2 h, comme Coupure::chevauche().
                $debut = $coupure->debut;
                $fin = $coupure->fin ?? $debut->copy()->addHours(2);

                return $debut < $finHeure && $fin > $debutHeure;
            });

            $heures[] = [
                'heure' => $prevision->heure,
                'temperature' => $prevision->temperature,
                'ressentie' => $prevision->ressentie,
                'humidite' => $prevision->humidite,
                'condition' => $prevision->condition,
                'icone' => $prevision->iconeMaterial(),
                'stress' => $stress,
                'pic' => $prevision->temperature >= $seuil,
            ];
        }

        return $heures;
    }

    /**
     * Plus longue plage contiguë « fraîche et sans coupure » (pour recharger
     * équipements et accumulateurs de froid). Null si aucune donnée.
     *
     * @param  array<int, array<string, mixed>>  $heures
     * @return array{debut: Carbon, fin: Carbon}|null
     */
    private function plageSansStress(array $heures): ?array
    {
        if ($heures === []) {
            return null;
        }

        $seuil = (float) config('services.weather.seuil_defaut', 35);
        $meilleure = null;
        $courante = null;

        foreach ($heures as $heure) {
            $calme = ! $heure['stress'] && $heure['temperature'] < $seuil;

            if ($calme) {
                if ($courante === null) {
                    $courante = ['debut' => $heure['heure'], 'fin' => $heure['heure']->copy()->addHour(), 'n' => 1];
                } else {
                    $courante['fin'] = $heure['heure']->copy()->addHour();
                    $courante['n']++;
                }

                if ($meilleure === null || $courante['n'] > $meilleure['n']) {
                    $meilleure = $courante;
                }
            } else {
                $courante = null;
            }
        }

        return $meilleure ? ['debut' => $meilleure['debut'], 'fin' => $meilleure['fin']] : null;
    }

    /**
     * Plus longue plage sous tension : pic de chaleur (≥ seuil) ou coupure.
     * Null si aucune donnée / aucune tension.
     *
     * @param  array<int, array<string, mixed>>  $heures
     * @return array{debut: Carbon, fin: Carbon}|null
     */
    private function plageSousTension(array $heures): ?array
    {
        if ($heures === []) {
            return null;
        }

        $meilleure = null;
        $courante = null;

        foreach ($heures as $heure) {
            $tension = $heure['stress'] || $heure['pic'];

            if ($tension) {
                if ($courante === null) {
                    $courante = ['debut' => $heure['heure'], 'fin' => $heure['heure']->copy()->addHour(), 'n' => 1];
                } else {
                    $courante['fin'] = $heure['heure']->copy()->addHour();
                    $courante['n']++;
                }

                if ($meilleure === null || $courante['n'] > $meilleure['n']) {
                    $meilleure = $courante;
                }
            } else {
                $courante = null;
            }
        }

        return $meilleure ? ['debut' => $meilleure['debut'], 'fin' => $meilleure['fin']] : null;
    }

    /**
     * Refuges / zones d'ombre dans le rayon de 800 m autour de l'origine,
     * triés du plus proche au plus loin.
     *
     * @param  array{0: float, 1: float}|null  $origine
     * @return Collection<int, array<string, mixed>>
     */
    private function refugesProches(?array $origine): Collection
    {
        if ($origine === null) {
            return new Collection;
        }

        return Residence::pointFraicheur()
            ->whereNotNull('latitude')
            ->whereNotNull('longitude')
            ->with('quartier')
            ->get()
            ->map(function (Residence $residence) use ($origine): ?array {
                $distanceM = Geo::distanceKm(
                    $origine[0],
                    $origine[1],
                    (float) $residence->latitude,
                    (float) $residence->longitude,
                ) * 1000;

                if ($distanceM > Geo::RAYON_REFUGES_M) {
                    return null;
                }

                return [
                    'nom' => $residence->nom,
                    'adresse' => $residence->adresse,
                    'quartier' => $residence->quartier?->nom,
                    'lat' => (float) $residence->latitude,
                    'lng' => (float) $residence->longitude,
                    'distanceM' => (int) round($distanceM),
                    'climatisee' => (bool) $residence->salle_climatisee,
                ];
            })
            ->filter()
            ->sortBy('distanceM')
            ->values();
    }
}
