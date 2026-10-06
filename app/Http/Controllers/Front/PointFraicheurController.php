<?php

namespace App\Http\Controllers\Front;

use App\Enums\StatutPointFraicheur;
use App\Enums\TypePointFraicheur;
use App\Http\Controllers\Controller;
use App\Http\Requests\StorePointFraicheurRequest;
use App\Models\Avis;
use App\Models\PointFraicheur;
use App\Services\RecommandationFraicheurService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;

/**
 * Front office des points de fraîcheur (module 3).
 *
 *  - Public / habitant : consulte les points validés proches, avec le
 *    classement IA (position, affluence estimée, préférences PMR / clim /
 *    ombre / eau) et le détail d'un point avec ses avis.
 *  - Habitant : propose un nouveau point (modéré par l'admin) et suit ses
 *    propositions (modifiables / supprimables tant qu'elles ne sont pas validées).
 */
class PointFraicheurController extends Controller
{
    /** Position par défaut : centre de Tunis. */
    private const CENTRE_DEFAUT = [36.8065, 10.1815];

    public function __construct(private RecommandationFraicheurService $reco) {}

    public function index(Request $request): View
    {
        $filtres = $request->validate([
            'lat' => ['nullable', 'numeric', 'between:-90,90', 'required_with:lng'],
            'lng' => ['nullable', 'numeric', 'between:-180,180', 'required_with:lat'],
            'type' => ['nullable', 'in:'.implode(',', TypePointFraicheur::valeurs())],
            'rayon' => ['nullable', 'in:1,2,5,10,50'],
            'pmr' => ['nullable', 'boolean'],
            'climatise' => ['nullable', 'boolean'],
            'ombrage' => ['nullable', 'boolean'],
            'eau' => ['nullable', 'boolean'],
        ]);

        [$origine, $sourcePosition] = $this->origine($request);

        $preferences = [
            'pmr' => $request->boolean('pmr'),
            'climatise' => $request->boolean('climatise'),
            'ombrage' => $request->boolean('ombrage'),
            'eau' => $request->boolean('eau'),
        ];
        $rayon = (float) ($filtres['rayon'] ?? 5);

        // Contexte thermique : météo live (WeatherAPI) + vigilance canicule.
        $meteo = $this->reco->meteo($origine[0], $origine[1]);
        $pression = $this->reco->pressionThermique($origine[0], $origine[1], $meteo);

        // Tous les points du rayon, évalués par le moteur IA.
        $evalues = $this->reco->recommander(
            $origine[0], $origine[1], $preferences, $filtres['type'] ?? null, $rayon, 200, null, $pression,
        );

        $recommandations = $evalues->take(3);
        $proches = $evalues->sortBy('distance_m')->values();
        $conseil = $this->reco->conseil($recommandations, $preferences, $meteo, $request->user());

        $marqueurs = $proches->map(fn (array $r): array => [
            'lat' => $r['point']->latitude,
            'lng' => $r['point']->longitude,
            'nom' => $r['point']->nom,
            'type' => $r['point']->type->label(),
            'couleur' => $r['point']->type->couleurHex(),
            'score' => $r['score'],
            'distance' => $r['distance_m'],
            'lien' => route('points.show', $r['point']),
        ])->values();

        return view('front.points.index', compact(
            'recommandations', 'proches', 'marqueurs', 'origine', 'sourcePosition',
            'preferences', 'rayon', 'filtres', 'meteo', 'pression', 'conseil',
        ));
    }

    public function show(Request $request, PointFraicheur $point): View
    {
        // Un point non validé n'est visible que par son auteur.
        $estAuteur = $request->user() !== null && (int) $point->user_id === (int) $request->user()->id;

        if (! $point->estValide() && ! $estAuteur) {
            abort(404);
        }

        $point->load(['quartier', 'auteur'])->loadCount('avis')->loadAvg('avis', 'note');

        $avis = $point->avis()->with('user')->latest()->paginate(6);

        $notes = $point->avis()
            ->selectRaw('note, count(*) as total')
            ->groupBy('note')
            ->pluck('total', 'note');

        $monAvis = $request->user()
            ? Avis::where('point_fraicheur_id', $point->id)->where('user_id', $request->user()->id)->first()
            : null;

        // Distance + affluence estimée depuis la position de l'habitant.
        [$origine] = $this->origine($request);
        $point->load(['avis' => fn ($q) => $q->whereNotNull('affluence')->where('created_at', '>=', now()->subDays(30))]);
        $evaluation = $this->reco->evaluer($point, $origine[0], $origine[1], [], now());

        return view('front.points.show', compact('point', 'avis', 'notes', 'monAvis', 'evaluation'));
    }

    public function create(): View
    {
        return view('front.points.create', ['point' => null, 'centre' => $this->origine(request())[0]]);
    }

    public function store(StorePointFraicheurRequest $request): RedirectResponse
    {
        $data = $request->safe()->except(['photo', 'supprimer_photo', 'statut', 'motif_refus']);
        $data['user_id'] = $request->user()->id;
        $data['statut'] = StatutPointFraicheur::EnAttente;

        if ($request->hasFile('photo')) {
            $data['photo'] = $request->file('photo')->store('points-fraicheur', 'public');
        }

        PointFraicheur::create($data);

        return redirect()->route('points.mine')
            ->with('success', 'Merci ! Votre proposition a été envoyée : elle sera visible après validation par un administrateur.');
    }

    public function mesPropositions(Request $request): View
    {
        $propositions = PointFraicheur::where('user_id', $request->user()->id)
            ->avecNotes()
            ->latest()
            ->paginate(10);

        return view('front.points.mine', compact('propositions'));
    }

    public function edit(Request $request, PointFraicheur $point): View
    {
        $this->autoriserProposition($request, $point);

        return view('front.points.edit', ['point' => $point, 'centre' => [$point->latitude, $point->longitude]]);
    }

    public function update(StorePointFraicheurRequest $request, PointFraicheur $point): RedirectResponse
    {
        $this->autoriserProposition($request, $point);

        $data = $request->safe()->except(['photo', 'supprimer_photo', 'statut', 'motif_refus']);

        if ($request->hasFile('photo')) {
            if ($point->photo) {
                Storage::disk('public')->delete($point->photo);
            }
            $data['photo'] = $request->file('photo')->store('points-fraicheur', 'public');
        } elseif ($point->photo && $request->boolean('supprimer_photo')) {
            Storage::disk('public')->delete($point->photo);
            $data['photo'] = null;
        }

        // Une proposition refusée puis corrigée repart en modération.
        $data['statut'] = StatutPointFraicheur::EnAttente;
        $data['motif_refus'] = null;

        $point->update($data);

        return redirect()->route('points.mine')
            ->with('success', 'Proposition mise à jour : elle repart en validation.');
    }

    public function destroy(Request $request, PointFraicheur $point): RedirectResponse
    {
        $this->autoriserProposition($request, $point);

        if ($point->photo) {
            Storage::disk('public')->delete($point->photo);
        }

        $point->delete();

        return redirect()->route('points.mine')->with('success', 'Proposition supprimée.');
    }

    /**
     * L'habitant ne touche qu'à SES propositions, et pas une fois validées
     * (elles appartiennent alors au référentiel géré par l'admin).
     */
    private function autoriserProposition(Request $request, PointFraicheur $point): void
    {
        if ((int) $point->user_id !== (int) $request->user()->id) {
            abort(403, 'Cette proposition ne vous appartient pas.');
        }

        if ($point->estValide()) {
            abort(403, 'Ce point a été validé : contactez un administrateur pour le modifier.');
        }
    }

    /**
     * Position de référence : GPS du navigateur (?lat&lng), sinon lieu
     * principal de l'habitant, sinon centre de Tunis.
     *
     * @return array{0: array{0: float, 1: float}, 1: string}
     */
    private function origine(Request $request): array
    {
        if (is_numeric($request->query('lat')) && is_numeric($request->query('lng'))) {
            return [[(float) $request->query('lat'), (float) $request->query('lng')], 'gps'];
        }

        $lieu = $request->user()?->isHabitant() ? $request->user()->lieuPrincipal() : null;

        if ($lieu?->hasCoordinates()) {
            return [[(float) $lieu->latitude, (float) $lieu->longitude], 'lieu:'.$lieu->nom];
        }

        return [self::CENTRE_DEFAUT, 'defaut'];
    }
}
