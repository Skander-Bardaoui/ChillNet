<?php

namespace App\Http\Controllers\Back;

use App\Enums\StatutCoupure;
use App\Http\Controllers\Controller;
use App\Http\Requests\StoreCoupureRequest;
use App\Models\Coupure;
use App\Models\Quartier;
use App\Services\CoupureRiskAiService;

/**
 * Back office des coupures (module 2).
 *
 *  - Admin        : CRUD global sur toutes les zones.
 *  - Gestionnaire : SA zone uniquement (le quartier de sa résidence).
 *                   Il publie surtout des coupures PRÉVUES
 *                   (maintenance, délestage programmé).
 *
 * Le score de risque IA par zone et les anomalies (afflux de signalements)
 * sont affichés sur le tableau de bord, dans le périmètre du gestionnaire.
 */
class CoupureController extends Controller
{
    public function __construct(private CoupureRiskAiService $risk) {}

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
        // Marqueurs : le point posé sur la carte s'il existe (ciblage libre),
        // sinon le centre géolocalisé du quartier (coupures historiques).
        $marqueurs = [];
        $toutes = (clone $query)->with('quartier')->orderBy('debut', 'desc')->get();
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

            $zoneNom = $coupure->quartier?->nom ?? $coupure->lieu ?? 'Zone';

            $marqueurs[] = [
                'lat' => $lat,
                'lng' => $lng,
                'statut' => $coupure->statut?->value ?? $coupure->statut,
                'titre' => ($coupure->type?->label() ?? $coupure->type).' — '.$zoneNom,
                'detail' => ($coupure->lieu ? $coupure->lieu.' · ' : '').($coupure->statut?->label() ?? $coupure->statut)
                    .' · '.($coupure->debut?->format('d/m H:i') ?? '?')
                    .' → '.($coupure->fin?->format('d/m H:i') ?? '—'),
                'edit' => route('back.coupures.edit', $coupure->id),
            ];
        }

        $centre = $this->centreCarte();

        // Brique IA : risque de coupure par zone (historique + canicule) et
        // anomalies — restreints au périmètre du gestionnaire.
        $risques = $this->risk->scoresPourQuartiers($this->quartiersAccessibles());
        $anomalies = $this->risk->detecterAnomalies();

        if ($user && $user->isGestionnaire()) {
            $anomalies = $anomalies
                ->filter(fn (array $a): bool => (int) $a['quartier']->id === (int) $quartierId)
                ->values();
        }

        return view('back.coupures.index', compact('coupures', 'tri', 'stats', 'marqueurs', 'centre', 'risques', 'anomalies'));
    }

    public function create()
    {
        $quartiers = $this->quartiersAccessibles();
        $coupure = null;
        $centre = $this->centreCarte();

        return view('back.coupures.create', compact('quartiers', 'coupure', 'centre'));
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
        $centre = $this->centreCarte();

        return view('back.coupures.edit', compact('coupure', 'quartiers', 'centre'));
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
     * Centre par défaut de la carte : la zone du gestionnaire si elle est
     * géolocalisée, sinon le barycentre des quartiers, sinon Tunis.
     *
     * @return array{0: float, 1: float}
     */
    private function centreCarte(): array
    {
        $maZone = request()->user()?->residence?->quartier;

        if ($maZone && $maZone->hasCoordinates()) {
            return [(float) $maZone->latitude, (float) $maZone->longitude];
        }

        $geo = Quartier::whereNotNull('latitude')->whereNotNull('longitude')->get();

        return $geo->isNotEmpty()
            ? [(float) $geo->avg('latitude'), (float) $geo->avg('longitude')]
            : [36.8065, 10.1815];
    }

    /**
     * Données validées + garde-fous :
     *  - le gestionnaire est TOUJOURS ramené à sa zone, même si le formulaire
     *    est trafiqué ;
     *  - un point posé sans quartier est rattaché au quartier le plus proche
     *    (interne), sans jamais dépendre de son existence (nullable).
     *
     * @return array<string, mixed>
     */
    private function attributes(StoreCoupureRequest $request): array
    {
        $data = $request->validated();

        $user = request()->user();

        if ($user?->isGestionnaire() && $user->residence?->quartier_id) {
            $data['quartier_id'] = $user->residence->quartier_id;
        } elseif (empty($data['quartier_id']) && ! empty($data['latitude']) && ! empty($data['longitude'])) {
            $data['quartier_id'] = Quartier::plusProche(
                (float) $data['latitude'],
                (float) $data['longitude'],
            )?->id;
        }

        // Le statut résolu/en cours/prevue est saisi ici (back office),
        // contrairement au front où l'habitant ne crée que du "en cours".
        return $data;
    }
}
