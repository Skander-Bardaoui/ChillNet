<?php

namespace App\Http\Controllers\Front;

use App\Enums\NiveauAlerte;
use App\Http\Controllers\Controller;
use App\Models\Alerte;
use App\Models\Quartier;
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

        // Visibilité géo-aware : cercle (point) OU quartier (alertes héritées).
        $visibles = Alerte::filtrerPourLieu(
            Alerte::with('quartiers')->validees()->get(),
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
        ));
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
