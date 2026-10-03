<?php

namespace App\Http\Controllers\Back;

use App\Enums\NiveauAlerte;
use App\Enums\ProfilVulnerabilite;
use App\Http\Controllers\Controller;
use App\Http\Requests\PredictAlerteRequest;
use App\Http\Requests\StoreAlerteRequest;
use App\Models\Alerte;
use App\Models\Quartier;
use App\Models\User;
use App\Services\MeteoActuelle;
use App\Services\VigilanceAiService;
use App\Services\WeatherService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Back office des alertes canicule (module 1).
 *
 *  - Admin        : CRUD + validation sur tous les quartiers.
 *  - Gestionnaire : uniquement les alertes de SA zone (le quartier de sa
 *                   résidence) ; le quartier est forcé même si le formulaire
 *                   est trafiqué.
 *
 * Le niveau est classé par règles déterministes et le message rédigé par
 * l'IA (Groq) — voir VigilanceAiService ; l'endpoint `prefill` alimente le
 * formulaire (« Pré-remplir météo + IA »).
 */
class AlerteController extends Controller
{
    public function __construct(
        private WeatherService $meteo,
        private VigilanceAiService $ia,
    ) {}

    public function index(Request $request)
    {
        $user = $request->user();
        $quartierId = $user?->residence?->quartier_id;

        $query = Alerte::with('quartiers')
            ->when($user && $user->isGestionnaire(), fn ($q) => $q->parQuartier((int) $quartierId))
            ->when($request->filled('quartier_id'), fn ($q) => $q->parQuartier((int) $request->input('quartier_id')))
            ->when(in_array($request->input('niveau'), array_column(NiveauAlerte::cases(), 'value'), true),
                fn ($q) => $q->niveau(NiveauAlerte::from($request->input('niveau'))));

        $stats = [
            'total' => (clone $query)->count(),
            'actives' => (clone $query)->actives()->count(),
            'programmees' => (clone $query)->programmees()->count(),
            'terminees' => (clone $query)->terminees()->count(),
            'brouillons' => (clone $query)->where('validee', false)->count(),
            'rouge' => (clone $query)->where('niveau', NiveauAlerte::Rouge->value)->count(),
            'orange' => (clone $query)->where('niveau', NiveauAlerte::Orange->value)->count(),
            'jaune' => (clone $query)->where('niveau', NiveauAlerte::Jaune->value)->count(),
        ];

        // Marqueurs de la carte : un cercle par alerte géolocalisée, sinon une
        // entrée par (alerte × quartier) géolocalisé pour les alertes héritées.
        $marqueurs = [];
        foreach ((clone $query)->orderByDesc('debut')->get() as $alerte) {
            $detail = ($alerte->niveau?->label() ?? '').' · '.rtrim(rtrim(number_format((float) $alerte->seuil_temperature, 1, ',', ''), '0'), ',').'°C · '
                .($alerte->debut?->format('d/m H:i') ?? '?').' → '.($alerte->fin?->format('d/m H:i') ?? '—');

            if ($alerte->hasCoordinates()) {
                $marqueurs[] = [
                    'lat' => (float) $alerte->latitude,
                    'lng' => (float) $alerte->longitude,
                    'rayon' => $alerte->rayonMetres(),
                    'niveau' => $alerte->niveau?->value,
                    'couleur' => $alerte->niveau?->couleurHex(),
                    'titre' => $alerte->titre,
                    'detail' => $detail,
                    'edit' => route('back.alertes.edit', $alerte->id),
                ];

                continue;
            }

            foreach ($alerte->quartiers as $quartier) {
                if (! $quartier->hasCoordinates()) {
                    continue;
                }

                $marqueurs[] = [
                    'lat' => $quartier->latitude,
                    'lng' => $quartier->longitude,
                    'rayon' => null,
                    'niveau' => $alerte->niveau?->value,
                    'couleur' => $alerte->niveau?->couleurHex(),
                    'titre' => $alerte->titre.' — '.$quartier->nom,
                    'detail' => $detail,
                    'edit' => route('back.alertes.edit', $alerte->id),
                ];
            }
        }

        $tri = $request->input('tri', 'recent');
        if (! in_array($tri, ['recent', 'ancien', 'niveau', 'statut', 'zone'], true)) {
            $tri = 'recent';
        }

        match ($tri) {
            'ancien' => $query->orderBy('debut'),
            'niveau' => $query->orderByRaw("CASE niveau WHEN 'rouge' THEN 0 WHEN 'orange' THEN 1 ELSE 2 END")->orderByDesc('debut'),
            'statut' => $query->orderByRaw('CASE WHEN debut > ? THEN 0 WHEN fin < ? THEN 2 ELSE 1 END', [now(), now()])->orderByDesc('debut'),
            'zone' => $query->orderBy(
                Quartier::select('nom')
                    ->join('alerte_quartier', 'alerte_quartier.quartier_id', '=', 'quartiers.id')
                    ->whereColumn('alerte_quartier.alerte_id', 'alertes.id')
                    ->orderBy('nom')
                    ->limit(1)
            )->orderByDesc('debut'),
            default => $query->orderByDesc('debut'),
        };

        $alertes = $query->paginate(10)->withQueryString();

        $quartiers = $this->quartiersAccessibles();

        // Centre de la carte : la zone du gestionnaire si géolocalisée,
        // sinon le barycentre des quartiers, sinon Tunis.
        $maZone = $user?->residence?->quartier;
        if ($maZone && $maZone->hasCoordinates()) {
            $centre = [$maZone->latitude, $maZone->longitude];
        } else {
            $geo = Quartier::whereNotNull('latitude')->whereNotNull('longitude')->get();
            $centre = $geo->isNotEmpty()
                ? [$geo->avg('latitude'), $geo->avg('longitude')]
                : [36.8065, 10.1815];
        }

        return view('back.alertes.index', compact('alertes', 'tri', 'stats', 'marqueurs', 'centre', 'quartiers'));
    }

    public function create()
    {
        $quartiers = $this->quartiersAccessibles();
        $alerte = null;
        $niveaux = NiveauAlerte::cases();
        [$meteo, $meteoLieu] = $this->meteoGenerale();

        return view('back.alertes.create', compact('quartiers', 'alerte', 'niveaux', 'meteo', 'meteoLieu'));
    }

    public function store(StoreAlerteRequest $request)
    {
        $data = $this->donnees($request);
        $data['user_id'] = $request->user()?->id;

        $alerte = Alerte::create($data);
        $alerte->quartiers()->sync($this->quartierIds($request->user(), (array) $request->input('quartier_ids', [])));

        return redirect()->route('back.alertes.index')
            ->with('success', 'Alerte créée avec succès.');
    }

    public function edit(int $id)
    {
        $this->authorizeAlerte($id);

        $alerte = Alerte::with('quartiers')->findOrFail($id);
        $quartiers = $this->quartiersAccessibles();
        $niveaux = NiveauAlerte::cases();
        [$meteo, $meteoLieu] = $this->meteoGenerale();

        return view('back.alertes.edit', compact('alerte', 'quartiers', 'niveaux', 'meteo', 'meteoLieu'));
    }

    public function update(StoreAlerteRequest $request, int $id)
    {
        $this->authorizeAlerte($id);

        $alerte = Alerte::findOrFail($id);
        $alerte->update($this->donnees($request));
        $alerte->quartiers()->sync($this->quartierIds($request->user(), (array) $request->input('quartier_ids', [])));

        return redirect()->route('back.alertes.index')
            ->with('success', 'Alerte mise à jour avec succès.');
    }

    public function destroy(int $id)
    {
        $this->authorizeAlerte($id);

        Alerte::findOrFail($id)->delete();

        return redirect()->route('back.alertes.index')
            ->with('success', 'Alerte supprimée.');
    }

    /**
     * Validation par le gestionnaire : gate d'affichage côté habitant.
     */
    public function valider(Request $request, int $id)
    {
        $this->authorizeAlerte($id);

        Alerte::findOrFail($id)->update([
            'validee' => true,
            'validee_le' => now(),
            'validee_par' => $request->user()?->id,
        ]);

        return redirect()->route('back.alertes.index')
            ->with('success', 'Alerte validée et publiée.');
    }

    /**
     * « Pré-remplir météo + IA » : récupère la météo (live ou override
     * manuel), classe le niveau par règles, et — à la demande — rédige un
     * message via Groq. Ne renvoie jamais 500 : `ok:false` si pas de météo.
     */
    public function prefill(PredictAlerteRequest $request): JsonResponse
    {
        $user = $request->user();
        $ids = $this->quartierIds($user, (array) $request->input('quartier_ids', []));
        $quartiers = Quartier::whereKey($ids)->get();

        $seuil = $request->filled('seuil_temperature')
            ? (float) $request->input('seuil_temperature')
            : $this->ia->seuilDefaut();

        $latitude = $request->filled('latitude') ? (float) $request->input('latitude') : null;
        $longitude = $request->filled('longitude') ? (float) $request->input('longitude') : null;

        if ($request->filled('temperature_actuelle')) {
            // Override manuel : aucun appel WeatherAPI.
            $meteo = new MeteoActuelle(
                temperature: (float) $request->input('temperature_actuelle'),
                humidite: $request->filled('humidite') ? (int) $request->input('humidite') : null,
                source: 'manuel',
            );
        } elseif ($latitude !== null && $longitude !== null) {
            // Ciblage géographique : météo au point exact.
            $meteo = $this->meteo->actuel($latitude, $longitude);
        } else {
            $meteo = $this->meteo->actuelPourQuartiers($quartiers);
        }

        if (! $meteo) {
            return response()->json([
                'ok' => false,
                'raison' => 'Météo indisponible (aucune coordonnée ou service injoignable). Saisissez la température manuellement.',
            ]);
        }

        $historique = $ids !== [] ? $this->ia->historiquePourQuartier((int) $ids[0], 3) : [];
        $analyse = $this->ia->analyse($meteo->temperature, $meteo->humidite, $historique, $seuil);
        $niveau = $analyse['niveau'];

        $payload = [
            'ok' => true,
            'niveau' => $niveau->value,
            'niveau_label' => $niveau->label(),
            'badge_classes' => $niveau->badgeClasses(),
            'temperature' => round($meteo->temperature, 1),
            'ressentie' => $meteo->ressentie,
            'humidite' => $meteo->humidite,
            'source' => $meteo->source,
            'historique' => $historique,
            'raison' => $analyse['raison'],
            'message' => null,
        ];

        if ($request->boolean('avec_message') || $request->filled('profil')) {
            $profils = [];
            if ($profil = ProfilVulnerabilite::tryFrom((string) $request->input('profil'))) {
                $profils = [$profil];
            }

            $payload['message'] = $this->ia->messagePourContexte([
                'titre' => 'Alerte canicule',
                'niveau' => $niveau->value,
                'niveau_label' => $niveau->label(),
                'quartiers' => $quartiers->pluck('nom')->all() ?: ['Zone ciblée par cercle'],
                'seuil_temperature' => $seuil,
                'temperature_actuelle' => round($meteo->temperature, 1),
                'humidite' => $meteo->humidite,
                'raison' => $analyse['raison'],
            ], $profils);
        }

        return response()->json($payload);
    }

    /**
     * Relevé météo « général » affiché à droite du formulaire d'alerte, avant
     * toute saisie : la zone du gestionnaire si géolocalisée, sinon le
     * barycentre des quartiers géolocalisés, sinon rien. Ne lève jamais
     * d'exception (renvoie `[null, null]` si indisponible).
     *
     * @return array{0: ?MeteoActuelle, 1: ?string}
     */
    private function meteoGenerale(): array
    {
        $maZone = request()->user()?->residence?->quartier;

        if ($maZone && $maZone->hasCoordinates()) {
            return [$this->meteo->actuel((float) $maZone->latitude, (float) $maZone->longitude), $maZone->nom];
        }

        $geo = Quartier::whereNotNull('latitude')->whereNotNull('longitude')->get();

        if ($geo->isEmpty()) {
            return [null, null];
        }

        $meteo = $this->meteo->actuel((float) $geo->avg('latitude'), (float) $geo->avg('longitude'));

        return [$meteo, null];
    }

    /**
     * Quartiers proposés dans le formulaire : le gestionnaire ne voit que le
     * sien, l'admin les voit tous.
     *
     * @return \Illuminate\Database\Eloquent\Collection<int, Quartier>
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
     * Un gestionnaire ne touche que les alertes qui couvrent sa zone.
     */
    private function authorizeAlerte(int $id): void
    {
        $user = request()->user();

        if ($user?->isGestionnaire()) {
            $alerte = Alerte::with('quartiers')->findOrFail($id);

            if (! $alerte->couvreQuartier((int) $user->residence?->quartier_id)) {
                abort(403, 'Vous ne pouvez gérer que les alertes de votre zone.');
            }
        }
    }

    /**
     * Données validées prêtes pour la base (les quartiers passent par sync()).
     *
     * @return array<string, mixed>
     */
    private function donnees(StoreAlerteRequest $request): array
    {
        $data = $request->validated();
        unset($data['quartier_ids']);

        if (empty($data['source_meteo'])) {
            $data['source_meteo'] = 'manuel';
        }

        return $data;
    }

    /**
     * Quartiers réellement associés : un gestionnaire est TOUJOURS ramené à
     * sa zone, même si le formulaire envoie d'autres identifiants.
     *
     * @param  array<int|string>  $ids
     * @return array<int>
     */
    private function quartierIds(?User $user, array $ids): array
    {
        if ($user?->isGestionnaire() && $user->residence?->quartier_id) {
            return [(int) $user->residence->quartier_id];
        }

        return array_values(array_map('intval', $ids));
    }
}
