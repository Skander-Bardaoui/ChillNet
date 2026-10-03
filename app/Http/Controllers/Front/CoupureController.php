<?php

namespace App\Http\Controllers\Front;

use App\Enums\StatutCoupure;
use App\Http\Controllers\Controller;
use App\Http\Requests\StoreCoupureRequest;
use App\Models\Coupure;
use App\Models\Lieu;
use App\Models\Quartier;
use Illuminate\Http\Request;

/**
 * Front office des coupures (module 2).
 *
 *  - Tout le monde (public) : carte + liste des coupures actives, filtre par zone.
 *  - Habitant connecté : signale une coupure EN COURS.
 */
class CoupureController extends Controller
{
    /**
     * Carte des coupures actives + liste filtrable par zone, triée et paginée.
     * La carte montre TOUTES les coupures filtrées, la liste est paginée (10/page).
     */
    public function index(Request $request)
    {
        $quartiers = Quartier::orderBy('nom')->get();

        // Base : en cours + prévues uniquement (l'historique résolu reste au back).
        // Jointure with('quartier') = pas de requête N+1 dans la liste et la carte.
        $base = Coupure::with('quartier')
            ->whereIn('statut', [StatutCoupure::EnCours->value, StatutCoupure::Prevue->value])
            ->when($request->filled('quartier_id'), fn ($q) => $q->parZone((int) $request->input('quartier_id')));

        // Compteurs de l'en-tête (requêtes COUNT légères).
        $nbActives = (clone $base)->where('statut', StatutCoupure::EnCours->value)->count();
        $nbPrevues = (clone $base)->where('statut', StatutCoupure::Prevue->value)->count();

        // Tri demandé (?tri=...), valeur par défaut : plus récent d'abord.
        $tri = $request->input('tri', 'recent');
        $trisValides = ['recent', 'ancien', 'zone', 'statut', 'type'];
        if (! in_array($tri, $trisValides, true)) {
            $tri = 'recent';
        }

        $liste = clone $base;
        match ($tri) {
            'ancien' => $liste->orderBy('debut', 'asc'),
            'zone' => $liste->orderBy(Quartier::select('nom')->whereColumn('quartiers.id', 'coupures.quartier_id'))->orderBy('debut', 'desc'),
            'statut' => $liste->orderByRaw("CASE statut WHEN 'en_cours' THEN 0 WHEN 'prevue' THEN 1 ELSE 2 END")->orderBy('debut', 'desc'),
            'type' => $liste->orderBy('type')->orderBy('debut', 'desc'),
            default => $liste->orderBy('debut', 'desc'),
        };

        // Pagination : 10 par page, filtres conservés dans les liens (?page=2&quartier_id=..&tri=..).
        $coupures = $liste->paginate(10)->withQueryString();

        // Centre de la carte Leaflet : la zone filtrée si elle a des
        // coordonnées, sinon le barycentre des quartiers géolocalisés,
        // sinon Tunis par défaut (façon étudiante, sans service externe).
        $filtre = $request->filled('quartier_id')
            ? $quartiers->firstWhere('id', (int) $request->input('quartier_id'))
            : null;

        if ($filtre && $filtre->hasCoordinates()) {
            $centre = [$filtre->latitude, $filtre->longitude];
        } else {
            $geo = $quartiers->filter(fn ($q) => $q->hasCoordinates());
            $centre = $geo->isNotEmpty()
                ? [$geo->avg('latitude'), $geo->avg('longitude')]
                : [36.8065, 10.1815];
        }

        // Données simples pour Leaflet (pré-calculées ici car @json()
        // n'aime pas les closures `fn` directement dans Blade).
        // La carte montre toutes les coupures filtrées (pas seulement la page).
        $quartiersCoords = [];
        foreach ($quartiers as $quartier) {
            if ($quartier->hasCoordinates()) {
                $quartiersCoords[$quartier->id] = [
                    'lat' => $quartier->latitude,
                    'lng' => $quartier->longitude,
                    'nom' => $quartier->nom,
                ];
            }
        }

        // Un marqueur par coupure : le point posé librement s'il existe,
        // sinon le centre géolocalisé du quartier (coupures historiques).
        $marqueurs = [];
        $toutes = (clone $base)->with('quartier')->orderBy('debut')->get();
        foreach ($toutes as $coupure) {
            if ($coupure->hasCoordinates()) {
                $lat = $coupure->latitude;
                $lng = $coupure->longitude;
            } elseif ($coupure->quartier?->hasCoordinates()) {
                $lat = $coupure->quartier->latitude;
                $lng = $coupure->quartier->longitude;
            } else {
                continue;
            }

            $zoneNom = $coupure->quartier?->nom ?? $coupure->lieu ?? 'Zone signalée';

            $marqueurs[] = [
                'lat' => $lat,
                'lng' => $lng,
                'statut' => $coupure->statut?->value ?? $coupure->statut,
                'titre' => ($coupure->type?->label() ?? $coupure->type).' — '.$zoneNom,
                'detail' => ($coupure->lieu ? $coupure->lieu.' · ' : '').'De '.($coupure->debut?->format('d/m H:i') ?? '?').' à '.($coupure->fin?->format('d/m H:i') ?? '—').' · '.($coupure->description ?? ''),
            ];
        }

        return view('front.coupures', compact('quartiers', 'coupures', 'nbActives', 'nbPrevues', 'tri', 'centre', 'quartiersCoords', 'marqueurs'));
    }

    /**
     * Formulaire habitant : signaler une coupure en cours.
     *
     * L'habitant choisit l'un de SES lieux (Domicile, Travail…). Le quartier
     * interne est déduit de ce lieu : plus aucune liste de quartiers.
     */
    public function create(Request $request)
    {
        $lieux = $request->user()->lieux()
            ->orderByDesc('est_principal')
            ->orderBy('nom')
            ->get();

        // Repère visuel : le lieu principal, sinon le premier déclaré.
        $principal = $lieux->firstWhere('est_principal', true) ?? $lieux->first();

        $lieuxJson = $lieux->map(fn (Lieu $lieu): array => [
            'id' => $lieu->id,
            'nom' => $lieu->nom,
            'typeLabel' => $lieu->type->label(),
            'lat' => $lieu->latitude !== null ? (float) $lieu->latitude : null,
            'lng' => $lieu->longitude !== null ? (float) $lieu->longitude : null,
            'principal' => (bool) $lieu->est_principal,
        ])->values();

        $geo = $lieuxJson->filter(fn (array $l): bool => $l['lat'] !== null && $l['lng'] !== null);

        if ($principal?->hasCoordinates()) {
            $centre = [(float) $principal->latitude, (float) $principal->longitude];
        } elseif ($geo->isNotEmpty()) {
            $centre = [$geo->avg('lat'), $geo->avg('lng')];
        } else {
            $centre = [36.8065, 10.1815];
        }

        $lieuIdParDefaut = old('lieu_id', $principal?->id);

        return view('front.coupures-create', compact('lieux', 'lieuxJson', 'centre', 'lieuIdParDefaut'));
    }

    /**
     * « Je confirme » : un habitant constate aussi cette coupure en cours
     * et crédibilise le signalement (+1). Règles façon étudiante :
     *  - uniquement sur une coupure EN COURS ;
     *  - jamais sur son propre signalement ;
     *  - une seule fois par session (anti double-clic, sans table pivot).
     */
    public function confirm(Request $request, int $coupure)
    {
        $coupure = Coupure::findOrFail($coupure);

        if (($coupure->statut?->value ?? $coupure->statut) !== StatutCoupure::EnCours->value) {
            return back()->with('error', 'On ne peut confirmer qu\'une coupure en cours.');
        }

        if ($coupure->user_id && (int) $coupure->user_id === (int) $request->user()?->id) {
            return back()->with('error', 'Vous ne pouvez pas confirmer votre propre signalement : ce sont les voisins qui le crédibilisent.');
        }

        $cle = 'coupure_confirmee_'.$coupure->id;

        if (session()->get($cle)) {
            return back()->with('error', 'Vous avez déjà confirmé cette coupure.');
        }

        $coupure->increment('confirmations');
        session()->put($cle, true);

        return back()->with('success', 'Merci ! Votre confirmation a été prise en compte.');
    }

    /**
     * L'habitant signale une coupure depuis l'un de ses lieux.
     *
     * On ne crée que des coupures « en cours » (statut forcé), rattachées au
     * quartier interne du lieu choisi. Le contrôleur vérifie l'appartenance du
     * lieu au foyer (la Request le fait déjà via `exists` + `user_id`).
     */
    public function store(StoreCoupureRequest $request)
    {
        $user = $request->user();
        $data = $request->validated();

        $lieu = $user->lieux()->findOrFail((int) $data['lieu_id']);

        // Sans zone interne (aucun quartier géolocalisé proche), impossible de
        // rattacher le signalement : on l'explique plutôt que de planter.
        if (! $lieu->quartier_id) {
            return back()
                ->withErrors(['lieu_id' => "Ce lieu n'est relié à aucune zone : impossible de rattacher le signalement."])
                ->withInput();
        }

        $data['quartier_id'] = $lieu->quartier_id;

        // On mémorise le point exact du lieu : la carte publique est plus précise.
        if ($lieu->hasCoordinates()) {
            $data['latitude'] = $lieu->latitude;
            $data['longitude'] = $lieu->longitude;
        }

        unset($data['lieu_id']);

        // Signalement habitant = toujours « en cours ».
        $data['statut'] = StatutCoupure::EnCours->value;
        $data['user_id'] = $user->id;
        $data['debut'] ??= now();

        Coupure::create($data);

        return redirect()->route('coupures.index')
            ->with('success', 'Merci ! Votre signalement a été enregistré.');
    }
}
