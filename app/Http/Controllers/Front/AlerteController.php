<?php

namespace App\Http\Controllers\Front;

use App\Enums\NiveauAlerte;
use App\Http\Controllers\Controller;
use App\Models\Alerte;
use App\Models\Quartier;
use App\Services\MeteoHoraire;
use App\Services\VigilanceAiService;
use App\Services\WeatherService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;

/**
 * Front office des alertes canicule (module 1) — espace habitant.
 *
 * L'habitant voit les alertes VALIDÉES et ACTIVES **de ses lieux**
 * (Domicile, Travail…), avec un message adapté à son profil de vulnérabilité.
 * Le quartier reste interne : il sert uniquement à rattacher le lieu.
 * Il ne peut pas créer d'alerte (réservé au back office).
 */
class AlerteController extends Controller
{
    /** Jours abrégés en français (indexés sur Carbon::dayOfWeek, 0 = dimanche). */
    private const JOURS_FR = ['dim.', 'lun.', 'mar.', 'mer.', 'jeu.', 'ven.', 'sam.'];

    public function __construct(
        private VigilanceAiService $ia,
        private WeatherService $meteo,
    ) {}

    public function index(Request $request)
    {
        $user = $request->user();

        // Les lieux du foyer remplacent le quartier dans toute l'interface.
        $lieux = $user->lieux()->orderByDesc('est_principal')->orderBy('nom')->get();

        // Lieu consulté : celui demandé (?lieu_id) s'il appartient au foyer,
        // sinon le lieu principal.
        $lieuId = $request->filled('lieu_id') ? (int) $request->input('lieu_id') : null;
        $lieuFiltre = $lieuId ? $lieux->firstWhere('id', $lieuId) : null;
        $lieuFiltre ??= $user->lieuPrincipal();
        $filtreId = $lieuFiltre?->id;

        // Zone interne + point de référence depuis le lieu (compat résidence
        // pour les foyers qui n'auraient encore aucun lieu).
        $residence = $user->residence;
        $quartierId = $lieuFiltre?->quartier_id ?? $residence?->quartier_id;

        $latitude = null;
        $longitude = null;

        if ($lieuFiltre?->hasCoordinates()) {
            $latitude = (float) $lieuFiltre->latitude;
            $longitude = (float) $lieuFiltre->longitude;
        } elseif ($residence?->hasCoordinates()) {
            $latitude = (float) $residence->latitude;
            $longitude = (float) $residence->longitude;
        } elseif ($quartierId !== null) {
            $quartier = Quartier::find($quartierId);

            if ($quartier?->hasCoordinates()) {
                $latitude = (float) $quartier->latitude;
                $longitude = (float) $quartier->longitude;
            }
        }

        // Ne charge que les alertes encore utiles (actives ou à venir) : le
        // front n'affiche jamais les terminées, inutile de les lire en base.
        // Visibilité géo-aware : cercle (point) OU quartier (alertes héritées).
        $visibles = Alerte::filtrerPourLieu(
            Alerte::with('quartiers')->validees()->where('fin', '>=', now())->get(),
            $quartierId,
            $latitude,
            $longitude,
        );

        $actives = (clone $visibles)
            ->filter(fn (Alerte $alerte): bool => $alerte->estActive())
            ->sortByDesc(fn (Alerte $alerte): int => $alerte->niveau->gravite())
            ->values();

        $aVenir = (clone $visibles)
            ->filter(fn (Alerte $alerte): bool => $alerte->estProgrammee())
            ->sortBy(fn (Alerte $alerte) => $alerte->debut)
            ->values();

        $stats = [
            'actives' => $actives->count(),
            'rouge' => $actives->filter(fn (Alerte $a): bool => $a->niveau === NiveauAlerte::Rouge)->count(),
            'orange' => $actives->filter(fn (Alerte $a): bool => $a->niveau === NiveauAlerte::Orange)->count(),
            'jaune' => $actives->filter(fn (Alerte $a): bool => $a->niveau === NiveauAlerte::Jaune)->count(),
        ];

        // Message personnalisé pour l'alerte la plus grave (cache 30 min →
        // au plus un appel LLM par habitant/profil/30 min).
        $alertePrincipale = $actives->first();
        $messagePersonnalise = $alertePrincipale ? $this->messagePour($alertePrincipale, $request) : null;

        $meteo = $latitude !== null && $longitude !== null
            ? $this->meteo->actuel($latitude, $longitude)
            : null;

        // Courbe de température des prochaines 48 h pour situer l'alerte dans le temps.
        $courbe = $this->courbeTemperature($latitude, $longitude, $alertePrincipale);

        return view('front.alertes', compact(
            'lieux',
            'lieuFiltre',
            'filtreId',
            'actives',
            'aVenir',
            'stats',
            'alertePrincipale',
            'messagePersonnalise',
            'meteo',
            'courbe',
        ));
    }

    /**
     * Série de températures des prochaines 48 h, prête pour un sparkline SVG.
     * Coordonnées normalisées dans une zone 720×240 (jamais déformées : le SVG
     * garde son ratio). `null` si le point ou la prévision est indisponible.
     *
     * @return array<string, mixed>|null
     */
    private function courbeTemperature(?float $latitude, ?float $longitude, ?Alerte $alertePrincipale): ?array
    {
        if ($latitude === null || $longitude === null) {
            return null;
        }

        $previsions = $this->meteo->previsions($latitude, $longitude, 48);

        if ($previsions === []) {
            return null;
        }

        $seuil = (float) ($alertePrincipale?->seuil_temperature
            ?? config('services.weather.seuil_defaut', 35));

        $temperatures = array_map(fn (MeteoHoraire $heure): float => $heure->temperature, $previsions);
        $min = min($temperatures);
        $max = max($temperatures);

        // Marge : la courbe ne colle jamais aux bords haut/bas.
        if ($max - $min < 1) {
            $min -= 1;
            $max += 1;
        }

        // Zone de tracé dans le viewBox 720×240. Les marges latérales laissent
        // la place aux libellés d'axe (gauche) et à l'infobulle (les deux bords).
        $largeur = 720.0;
        $hauteur = 240.0;
        $margeHaut = 30.0;
        $margeBas = 42.0;
        $insetGauche = 44.0;
        $insetDroit = 44.0;
        $zoneY = $hauteur - $margeHaut - $margeBas;

        $yPour = fn (float $temperature): float => round(
            $margeHaut + (1 - (($temperature - $min) / ($max - $min))) * $zoneY,
            2,
        );

        $total = count($previsions);
        $points = [];
        $labels = [];
        $jours = [];
        $datePrecedente = null;

        foreach (array_values($previsions) as $i => $prevision) {
            $x = round($total > 1
                ? $insetGauche + $i * ($largeur - $insetGauche - $insetDroit) / ($total - 1)
                : $largeur / 2, 2);

            $points[] = [
                'x' => $x,
                'y' => $yPour($prevision->temperature),
                'heure' => $prevision->heure,
                'temperature' => $prevision->temperature,
                'icone' => $prevision->iconeMaterial(),
                'pic' => $prevision->temperature >= $seuil,
            ];

            // Repère d'axe toutes les 6 h (00 h, 06 h, 12 h, 18 h).
            if ((int) $prevision->heure->format('G') % 6 === 0) {
                $labels[] = ['x' => $x, 'texte' => $prevision->heure->format('H\hi')];
            }

            // Séparateur de jour au premier point d'une nouvelle date.
            // Libellés français explicites : l'app est en français mais la
            // locale Laravel par défaut reste « en ».
            $jour = $prevision->heure->toDateString();
            if ($datePrecedente !== null && $jour !== $datePrecedente) {
                $jours[] = ['x' => $x, 'label' => self::JOURS_FR[$prevision->heure->dayOfWeek]];
            }
            $datePrecedente = $jour;
        }

        // Grille horizontale : 4 bandes régulières entre le min et le max affichés.
        $grille = [];
        for ($k = 0; $k <= 4; $k++) {
            $valeur = $max - ($max - $min) * $k / 4;
            $grille[] = ['y' => $yPour($valeur), 'valeur' => round($valeur)];
        }

        // Courbe et aire lissées (Catmull-Rom → Bézier cubique), bornées à la zone.
        $chemin = $this->cheminLisse($points, $margeHaut, $margeHaut + $zoneY);
        $premierX = $points[0]['x'];
        $dernierX = $points[$total - 1]['x'];
        $aire = $chemin.' L '.$dernierX.','.$hauteur.' L '.$premierX.','.$hauteur.' Z';

        // Données sérialisables pour l'infobulle interactive (Alpine.js).
        $serie = array_map(fn (array $p): array => [
            'x' => $p['x'],
            'y' => $p['y'],
            't' => round($p['temperature'], 1),
            'h' => $p['heure']->format('H\hi'),
            'j' => self::JOURS_FR[$p['heure']->dayOfWeek],
            'pic' => $p['pic'],
            'bas' => $p['y'] < 96,
        ], $points);

        return [
            'points' => $points,
            'chemin' => $chemin,
            'aire' => $aire,
            'grille' => $grille,
            'jours' => $jours,
            'serie' => $serie,
            'maintenant' => $points[0],
            'seuil' => $seuil,
            'seuilY' => ($seuil >= $min && $seuil <= $max) ? $yPour($seuil) : null,
            'min' => round($min, 1),
            'max' => round($max, 1),
            'labels' => $labels,
            'largeur' => $largeur,
            'hauteur' => $hauteur,
            'insetGauche' => $insetGauche,
            'insetDroit' => $insetDroit,
        ];
    }

    /**
     * Chemin SVG lissé : chaque segment devient une courbe de Bézier cubique
     * dérivée des points voisins (Catmull-Rom), bornée verticalement pour
     * éviter tout dépassement disgracieux au-dessus/en dessous de la zone.
     *
     * @param  array<int, array<string, mixed>>  $points
     */
    private function cheminLisse(array $points, float $yMin, float $yMax): string
    {
        $n = count($points);

        if ($n === 0) {
            return '';
        }

        if ($n === 1) {
            return 'M '.$points[0]['x'].','.$points[0]['y'];
        }

        $borner = fn (float $y): float => round(min(max($y, $yMin), $yMax), 2);
        $d = 'M '.$points[0]['x'].','.$points[0]['y'];

        for ($i = 0; $i < $n - 1; $i++) {
            $p0 = $points[max(0, $i - 1)];
            $p1 = $points[$i];
            $p2 = $points[$i + 1];
            $p3 = $points[min($n - 1, $i + 2)];

            $c1x = round($p1['x'] + ($p2['x'] - $p0['x']) / 6, 2);
            $c1y = $borner($p1['y'] + ($p2['y'] - $p0['y']) / 6);
            $c2x = round($p2['x'] - ($p3['x'] - $p1['x']) / 6, 2);
            $c2y = $borner($p2['y'] - ($p3['y'] - $p1['y']) / 6);

            $d .= ' C '.$c1x.','.$c1y.' '.$c2x.','.$c2y.' '.$p2['x'].','.$p2['y'];
        }

        return $d;
    }

    /**
     * Message personnalisé mis en cache (clé = alerte + profil du foyer).
     */
    private function messagePour(Alerte $alerte, Request $request): string
    {
        $user = $request->user();
        $profilKey = implode('-', array_map(
            fn ($profil): string => $profil->value,
            $user?->profilsVulnerabilite() ?? [],
        )) ?: 'standard';

        return Cache::remember(
            "alerte:{$alerte->id}:profil:{$profilKey}",
            now()->addMinutes(30),
            fn (): string => $this->ia->messagePersonnalise($alerte, $user),
        );
    }
}
