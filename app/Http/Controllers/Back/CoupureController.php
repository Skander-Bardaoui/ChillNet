<?php

namespace App\Http\Controllers\Back;

use App\Enums\StatutCoupure;
use App\Http\Controllers\Controller;
use App\Http\Requests\StoreCoupureRequest;
use App\Models\Coupure;
use App\Models\Quartier;

/**
 * Back office des coupures (module 2).
 *
 *  - Admin        : CRUD global sur toutes les zones.
 *  - Gestionnaire : SA zone uniquement (le quartier de sa résidence).
 *                   Il publie surtout des coupures PRÉVUES
 *                   (maintenance, délestage programmé).
 */
class CoupureController extends Controller
{
    public function index()
    {
        $user = request()->user();

        $quartierId = $user?->residence?->quartier_id;

        // Tri demandé (?tri=...), défaut : plus récent d'abord.
        $tri = request()->input('tri', 'recent');
        if (! in_array($tri, ['recent', 'ancien', 'zone', 'statut', 'type'], true)) {
            $tri = 'recent';
        }

        $query = Coupure::with('quartier')
            ->when($user && $user->isGestionnaire(), fn ($q) => $q->where('quartier_id', $quartierId));

        // Chiffres du tableau de bord (même périmètre : le gestionnaire
        // ne voit que les stats de sa zone).
        $stats = [
            'total' => (clone $query)->count(),
            'en_cours' => (clone $query)->where('statut', StatutCoupure::EnCours->value)->count(),
            'prevues' => (clone $query)->where('statut', StatutCoupure::Prevue->value)->count(),
            'resolues' => (clone $query)->where('statut', StatutCoupure::Resolue->value)->count(),
            'confirmations' => (clone $query)->sum('confirmations'),
        ];

        match ($tri) {
            'ancien' => $query->orderBy('debut', 'asc'),
            'zone' => $query->orderBy(Quartier::select('nom')->whereColumn('quartiers.id', 'coupures.quartier_id'))->orderBy('debut', 'desc'),
            'statut' => $query->orderByRaw("CASE statut WHEN 'en_cours' THEN 0 WHEN 'prevue' THEN 1 ELSE 2 END")->orderBy('debut', 'desc'),
            'type' => $query->orderBy('type')->orderBy('debut', 'desc'),
            default => $query->orderBy('debut', 'desc'),
        };

        // Pagination : 10 par page, tri conservé dans les liens de pages.
        $coupures = $query->paginate(10)->withQueryString();

        // Carte du back-office : TOUTES les coupures du périmètre (pas
        // seulement la page), avec les 3 statuts (vert = résolue).
        $marqueurs = [];
        $toutes = (clone $query)->with('quartier')->orderBy('debut', 'desc')->get();
        foreach ($toutes as $coupure) {
            if (! $coupure->quartier || ! $coupure->quartier->hasCoordinates()) {
                continue;
            }
            $marqueurs[] = [
                'lat' => $coupure->quartier->latitude,
                'lng' => $coupure->quartier->longitude,
                'statut' => $coupure->statut?->value ?? $coupure->statut,
                'titre' => ($coupure->type?->label() ?? $coupure->type).' — '.$coupure->quartier->nom,
                'detail' => ($coupure->lieu ? $coupure->lieu.' · ' : '').($coupure->statut?->label() ?? $coupure->statut)
                    .' · '.($coupure->debut?->format('d/m H:i') ?? '?')
                    .' → '.($coupure->fin?->format('d/m H:i') ?? '—'),
                'edit' => route('back.coupures.edit', $coupure->id),
            ];
        }

        // Centre : la zone du gestionnaire si elle est géolocalisée,
        // sinon le barycentre des quartiers, sinon Tunis par défaut.
        $maZone = $user?->residence?->quartier;
        if ($maZone && $maZone->hasCoordinates()) {
            $centre = [$maZone->latitude, $maZone->longitude];
        } else {
            $geo = Quartier::whereNotNull('latitude')->whereNotNull('longitude')->get();
            $centre = $geo->isNotEmpty()
                ? [$geo->avg('latitude'), $geo->avg('longitude')]
                : [36.8065, 10.1815];
        }

        return view('back.coupures.index', compact('coupures', 'tri', 'stats', 'marqueurs', 'centre'));
    }

    public function create()
    {
        $quartiers = $this->quartiersAccessibles();
        $coupure = null;

        return view('back.coupures.create', compact('quartiers', 'coupure'));
    }

    public function store(StoreCoupureRequest $request)
    {
        $data = $this->attributes($request);

        $data['user_id'] = request()->user()?->id;

        Coupure::create($data);

        return redirect()->route('back.coupures.index')
            ->with('success', 'Coupure publiée avec succès.');
    }

    public function edit(int $id)
    {
        $this->authorizeCoupure($id);

        $coupure = Coupure::findOrFail($id);
        $quartiers = $this->quartiersAccessibles();

        return view('back.coupures.edit', compact('coupure', 'quartiers'));
    }

    public function update(StoreCoupureRequest $request, int $id)
    {
        $this->authorizeCoupure($id);

        $coupure = Coupure::findOrFail($id);
        $coupure->update($this->attributes($request));

        return redirect()->route('back.coupures.index')
            ->with('success', 'Coupure mise à jour avec succès.');
    }

    public function destroy(int $id)
    {
        $this->authorizeCoupure($id);

        Coupure::findOrFail($id)->delete();

        return redirect()->route('back.coupures.index')
            ->with('success', 'Coupure supprimée.');
    }

    /**
     * Quartiers proposés dans le formulaire :
     * le gestionnaire ne voit que le sien, l'admin les voit tous.
     */
    private function quartiersAccessibles()
    {
        $user = request()->user();

        if ($user && $user->isGestionnaire()) {
            return Quartier::whereKey($user->residence?->quartier_id)->orderBy('nom')->get();
        }

        return Quartier::orderBy('nom')->get();
    }

    /**
     * Un gestionnaire ne touche que les coupures de sa zone.
     */
    private function authorizeCoupure(int $id): void
    {
        $user = request()->user();

        if ($user?->isGestionnaire()) {
            $coupure = Coupure::findOrFail($id);

            if ((int) $coupure->quartier_id !== (int) $user->residence?->quartier_id) {
                abort(403, 'Vous ne pouvez gérer que les coupures de votre zone.');
            }
        }
    }

    /**
     * Données validées + garde-fou gestionnaire :
     * on force la zone à la sienne même si le formulaire est trafiqué.
     *
     * @return array<string, mixed>
     */
    private function attributes(StoreCoupureRequest $request): array
    {
        $data = $request->validated();

        // Le back-office n'utilise que des zones existantes : on retire
        // les champs du mode « déclaration à la volée » (réservé au front).
        unset(
            $data['zone_mode'],
            $data['nouveau_quartier_nom'],
            $data['nouveau_quartier_ville'],
            $data['nouveau_quartier_code_postal'],
            $data['nouveau_latitude'],
            $data['nouveau_longitude'],
        );

        $user = request()->user();

        if ($user?->isGestionnaire() && $user->residence?->quartier_id) {
            $data['quartier_id'] = $user->residence->quartier_id;
        }

        // Le statut résolu/en cours/prevue est modifiable ici (back office),
        // contrairement au front où l'habitant ne crée que du "en cours".
        if (! isset($data['statut'])) {
            $data['statut'] = StatutCoupure::Prevue->value;
        }

        return $data;
    }
}
