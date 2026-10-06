<?php

namespace App\Http\Controllers\Back;

use App\Enums\StatutPointFraicheur;
use App\Enums\TypePointFraicheur;
use App\Http\Controllers\Controller;
use App\Http\Requests\StorePointFraicheurRequest;
use App\Models\Avis;
use App\Models\PointFraicheur;
use App\Models\Quartier;
use App\Services\SentimentAvisService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;

/**
 * Back office des points de fraîcheur (module 3).
 *
 *  - Admin        : CRUD global + MODÉRATION des points proposés par les
 *                   habitants (valider / refuser avec motif).
 *  - Gestionnaire : CRUD sur les points de SA zone (quartier de sa résidence).
 *
 * L'IA (analyse de sentiment) signale les points mal notés de façon récurrente.
 */
class PointFraicheurController extends Controller
{
    public function __construct(private SentimentAvisService $sentiment) {}

    public function index(Request $request): View
    {
        $filtres = $request->validate([
            'q' => ['nullable', 'string', 'max:100'],
            'type' => ['nullable', 'in:'.implode(',', TypePointFraicheur::valeurs())],
            'statut' => ['nullable', 'in:'.implode(',', StatutPointFraicheur::valeurs())],
            'tri' => ['nullable', 'in:recent,nom,note,avis'],
        ]);

        $perimetre = PointFraicheur::query()
            ->when($this->zoneGestionnaire(), fn ($q, int $zone) => $q->where('quartier_id', $zone));

        $stats = [
            'total' => (clone $perimetre)->count(),
            'valides' => (clone $perimetre)->valides()->count(),
            'en_attente' => (clone $perimetre)->enAttente()->count(),
            'refuses' => (clone $perimetre)->where('statut', StatutPointFraicheur::Refuse->value)->count(),
            'avis' => Avis::whereIn('point_fraicheur_id', (clone $perimetre)->select('id'))->count(),
        ];

        // File de modération : propositions d'habitants en attente.
        $enAttente = (clone $perimetre)->enAttente()->with(['auteur', 'quartier'])->oldest()->get();

        $query = (clone $perimetre)
            ->with(['quartier', 'auteur'])
            ->avecNotes()
            ->when($filtres['q'] ?? null, fn ($q, string $s) => $q->where(fn ($w) => $w
                ->where('nom', 'like', "%{$s}%")
                ->orWhere('adresse', 'like', "%{$s}%")))
            ->deType($filtres['type'] ?? null)
            ->when($filtres['statut'] ?? null, fn ($q, string $s) => $q->where('statut', $s));

        match ($filtres['tri'] ?? 'recent') {
            'nom' => $query->orderBy('nom'),
            'note' => $query->orderByDesc('avis_avg_note'),
            'avis' => $query->orderByDesc('avis_count'),
            default => $query->latest(),
        };

        $points = $query->paginate(10)->withQueryString();

        // Carte : tout le périmètre, couleur par type, contour par statut.
        $marqueurs = (clone $perimetre)->get()->map(fn (PointFraicheur $p): array => [
            'lat' => $p->latitude,
            'lng' => $p->longitude,
            'nom' => $p->nom,
            'type' => $p->type->label(),
            'couleur' => $p->type->couleurHex(),
            'statut' => $p->statut->value,
            'lien' => route('back.points.show', $p),
        ])->values();

        // IA : points validés mal notés de manière récurrente.
        $malNotes = $this->sentiment->pointsMalNotes($this->zoneGestionnaire());

        return view('back.points.index', compact('points', 'stats', 'enAttente', 'marqueurs', 'malNotes', 'filtres'));
    }

    public function create(): View
    {
        return view('back.points.create', ['point' => null, 'centre' => $this->centreCarte()]);
    }

    public function store(StorePointFraicheurRequest $request): RedirectResponse
    {
        $data = $this->attributs($request);
        $data['user_id'] = $request->user()->id;

        // Créé par la gestion : validé d'office (sauf choix explicite de l'admin).
        $data['statut'] ??= StatutPointFraicheur::Valide->value;
        $this->horodaterModeration($data, $request);

        $point = PointFraicheur::create($data);

        return redirect()->route('back.points.show', $point)
            ->with('success', 'Point de fraîcheur « '.$point->nom.' » ajouté.');
    }

    public function show(PointFraicheur $point): View
    {
        $this->autoriser($point);

        $point->load(['quartier', 'auteur', 'validateur'])->loadCount('avis')->loadAvg('avis', 'note');

        $avis = $point->avis()->with('user')->latest()->paginate(8);

        $repartition = $point->avis()
            ->selectRaw('sentiment, count(*) as total')
            ->groupBy('sentiment')
            ->pluck('total', 'sentiment');

        $notes = $point->avis()
            ->selectRaw('note, count(*) as total')
            ->groupBy('note')
            ->pluck('total', 'note');

        // Diagnostic + synthèse IA (Groq) si le point est mal noté.
        $diagnostic = $this->sentiment->pointsMalNotes()->firstWhere('point.id', $point->id);
        $synthese = $diagnostic ? $this->sentiment->synthese($diagnostic) : null;

        return view('back.points.show', compact('point', 'avis', 'repartition', 'notes', 'diagnostic', 'synthese'));
    }

    public function edit(PointFraicheur $point): View
    {
        $this->autoriser($point);

        return view('back.points.edit', ['point' => $point, 'centre' => $this->centreCarte()]);
    }

    public function update(StorePointFraicheurRequest $request, PointFraicheur $point): RedirectResponse
    {
        $this->autoriser($point);

        $data = $this->attributs($request, $point);
        $this->horodaterModeration($data, $request, $point);

        $point->update($data);

        return redirect()->route('back.points.show', $point)
            ->with('success', 'Point de fraîcheur mis à jour.');
    }

    public function destroy(PointFraicheur $point): RedirectResponse
    {
        $this->autoriser($point);

        if ($point->photo) {
            Storage::disk('public')->delete($point->photo);
        }

        $nom = $point->nom;
        $point->delete(); // les avis suivent (cascadeOnDelete)

        return redirect()->route('back.points.index')
            ->with('success', 'Point « '.$nom.' » et ses avis supprimés.');
    }

    /**
     * Modération (admin) : publication d'un point proposé par un habitant.
     */
    public function valider(Request $request, PointFraicheur $point): RedirectResponse
    {
        $point->update([
            'statut' => StatutPointFraicheur::Valide,
            'motif_refus' => null,
            'valide_par' => $request->user()->id,
            'valide_le' => now(),
        ]);

        return back()->with('success', 'Point « '.$point->nom.' » validé : il est visible des habitants.');
    }

    /**
     * Modération (admin) : refus motivé (le motif est montré à l'habitant).
     */
    public function refuser(Request $request, PointFraicheur $point): RedirectResponse
    {
        $request->validate([
            'motif_refus' => ['required', 'string', 'min:10', 'max:500'],
        ], [
            'motif_refus.required' => 'Indiquez le motif du refus.',
            'motif_refus.min' => 'Le motif doit contenir au moins 10 caractères.',
            'motif_refus.max' => 'Le motif ne doit pas dépasser 500 caractères.',
        ]);

        $point->update([
            'statut' => StatutPointFraicheur::Refuse,
            'motif_refus' => $request->input('motif_refus'),
            'valide_par' => $request->user()->id,
            'valide_le' => now(),
        ]);

        return back()->with('success', 'Point « '.$point->nom.' » refusé.');
    }

    /**
     * Données validées + gestion de la photo (upload, remplacement, retrait).
     *
     * @return array<string, mixed>
     */
    private function attributs(StorePointFraicheurRequest $request, ?PointFraicheur $point = null): array
    {
        $data = collect($request->validated())->except(['photo', 'supprimer_photo'])->all();

        if ($request->hasFile('photo')) {
            if ($point?->photo) {
                Storage::disk('public')->delete($point->photo);
            }
            $data['photo'] = $request->file('photo')->store('points-fraicheur', 'public');
        } elseif ($point?->photo && $request->boolean('supprimer_photo')) {
            Storage::disk('public')->delete($point->photo);
            $data['photo'] = null;
        }

        if (($data['statut'] ?? null) !== StatutPointFraicheur::Refuse->value) {
            $data['motif_refus'] = null;
        }

        return $data;
    }

    /**
     * Trace qui a tranché et quand, si le statut change (admin).
     *
     * @param  array<string, mixed>  $data
     */
    private function horodaterModeration(array &$data, Request $request, ?PointFraicheur $point = null): void
    {
        if (isset($data['statut']) && $data['statut'] !== $point?->statut?->value
            && $data['statut'] !== StatutPointFraicheur::EnAttente->value) {
            $data['valide_par'] = $request->user()->id;
            $data['valide_le'] = now();
        }
    }

    /**
     * Quartier du gestionnaire (null pour l'admin = tout le réseau).
     */
    private function zoneGestionnaire(): ?int
    {
        $user = request()->user();

        return $user?->isGestionnaire() ? $user->residence?->quartier_id : null;
    }

    /**
     * Un gestionnaire ne gère que les points de sa zone.
     */
    private function autoriser(PointFraicheur $point): void
    {
        $zone = $this->zoneGestionnaire();

        if ($zone !== null && (int) $point->quartier_id !== $zone) {
            abort(403, 'Vous ne pouvez gérer que les points de fraîcheur de votre zone.');
        }
    }

    /**
     * @return array{0: float, 1: float}
     */
    private function centreCarte(): array
    {
        $zone = request()->user()?->residence?->quartier;

        if ($zone?->hasCoordinates()) {
            return [(float) $zone->latitude, (float) $zone->longitude];
        }

        $geo = Quartier::whereNotNull('latitude')->whereNotNull('longitude')->get();

        return $geo->isNotEmpty()
            ? [(float) $geo->avg('latitude'), (float) $geo->avg('longitude')]
            : [36.8065, 10.1815];
    }
}
