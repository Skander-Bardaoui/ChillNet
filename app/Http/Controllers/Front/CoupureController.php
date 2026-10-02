<?php

namespace App\Http\Controllers\Front;

use App\Enums\StatutCoupure;
use App\Http\Controllers\Controller;
use App\Http\Requests\StoreCoupureRequest;
use App\Models\Coupure;
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

        $marqueurs = [];
        $toutes = (clone $base)->orderBy('debut')->get();
        foreach ($toutes as $coupure) {
            if (! $coupure->quartier || ! $coupure->quartier->hasCoordinates()) {
                continue;
            }
            $marqueurs[] = [
                'lat' => $coupure->quartier->latitude,
                'lng' => $coupure->quartier->longitude,
                'statut' => $coupure->statut?->value ?? $coupure->statut,
                'titre' => ($coupure->type?->label() ?? $coupure->type).' — '.$coupure->quartier->nom,
                'detail' => ($coupure->lieu ? $coupure->lieu.' · ' : '').'De '.($coupure->debut?->format('d/m H:i') ?? '?').' à '.($coupure->fin?->format('d/m H:i') ?? '—').' · '.($coupure->description ?? ''),
            ];
        }

        return view('front.coupures', compact('quartiers', 'coupures', 'nbActives', 'nbPrevues', 'tri', 'centre', 'quartiersCoords', 'marqueurs'));
    }

    /**
     * Formulaire habitant : signaler une coupure en cours.
     */
    public function create()
    {
        $quartiers = Quartier::orderBy('nom')->get();
        $coupure = null;

        // Quartiers géolocalisés pour le bouton "Me localiser" (Haversine en JS).
        $quartiersGeo = [];
        // Tous les quartiers pour la recherche (même sans coordonnées).
        $quartiersSearch = [];
        foreach ($quartiers as $quartier) {
            $quartiersSearch[] = [
                'id' => $quartier->id,
                'nom' => $quartier->nom,
                'ville' => $quartier->ville,
                'lat' => $quartier->latitude,
                'lng' => $quartier->longitude,
            ];
            if ($quartier->hasCoordinates()) {
                $quartiersGeo[] = [
                    'id' => $quartier->id,
                    'nom' => $quartier->nom,
                    'lat' => $quartier->latitude,
                    'lng' => $quartier->longitude,
                ];
            }
        }

        return view('front.coupures-create', compact('quartiers', 'coupure', 'quartiersGeo', 'quartiersSearch'));
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
     * L'habitant ne crée que des coupures "en cours" :
     * on force le statut même si le formulaire envoie autre chose.
     *
     * Équivalent manuel avec validate() (sans FormRequest) :
     *   $data = $request->validate([
     *       'quartier_id' => 'required|exists:quartiers,id',
     *       'type' => 'required|in:delestage,surcharge,panne,maintenance',
     *       'debut' => 'required|date',
     *       ...
     *   ], [ 'quartier_id.required' => 'Veuillez sélectionner...' ]);
     * Ici on utilise StoreCoupureRequest qui fait exactement ce validate()
     * + les messages d'erreur en français + l'anti-chevauchement.
     */
    public function store(StoreCoupureRequest $request)
    {
        $data = $request->validated();

        // Zone déclarée à la volée : on crée le quartier (ou on réutilise
        // celui déjà déclaré par un voisin — firstOrCreate = pas de doublon),
        // avec la position cliquée sur la carte / donnée par le GPS.
        // C'est le même pattern que l'inscription (declareResidence).
        if ($request->input('zone_mode') === StoreCoupureRequest::MODE_NOUVELLE) {
            $quartier = Quartier::firstOrCreate(
                [
                    'nom' => trim((string) $request->input('nouveau_quartier_nom')),
                    'ville' => trim((string) $request->input('nouveau_quartier_ville')),
                ],
                [
                    'code_postal' => $request->input('nouveau_quartier_code_postal') ?: '0000',
                    'latitude' => $request->input('nouveau_latitude'),
                    'longitude' => $request->input('nouveau_longitude'),
                    'description' => 'Quartier déclaré par un habitant lors d\'un signalement de coupure.',
                ],
            );

            $data['quartier_id'] = $quartier->id;

            // Cas limite : le quartier existait déjà (déclaré par un voisin
            // entre-temps) avec une coupure qui chevauche. La Request a sauté
            // le contrôle (zone crue neuve) : on le refait ici explicitement.
            if (Coupure::chevauche($data['quartier_id'], (string) ($data['debut'] ?? now()), $data['fin'] ?? null, null, $data['lieu'] ?? null)) {
                return back()
                    ->withErrors(['debut' => 'Une autre coupure (en cours ou prévue) occupe déjà cet endroit (même zone, même rue) sur ce créneau.'])
                    ->withInput();
            }
        }

        // On retire les champs du formulaire qui ne sont pas des colonnes :
        // seuls quartier_id/type/statut/debut/fin/description partent en base.
        unset(
            $data['zone_mode'],
            $data['nouveau_quartier_nom'],
            $data['nouveau_quartier_ville'],
            $data['nouveau_quartier_code_postal'],
            $data['nouveau_latitude'],
            $data['nouveau_longitude'],
        );

        // Sécurité front : un signalement habitant = toujours "en cours".
        $data['statut'] = StatutCoupure::EnCours->value;
        $data['user_id'] = $request->user()?->id;

        // Si l'habitant ne met pas d'heure de début, on prend maintenant.
        $data['debut'] ??= now();

        Coupure::create($data);

        return redirect()->route('coupures.index')
            ->with('success', 'Merci ! Votre signalement a été enregistré.');
    }
}
