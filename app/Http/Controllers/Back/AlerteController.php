<?php

namespace App\Http\Controllers\Back;

use App\Enums\NiveauAlerte;
use App\Enums\ProfilVulnerabilite;
use App\Http\Controllers\Controller;
use App\Http\Requests\PredictAlerteRequest;
use App\Http\Requests\StoreAlerteRequest;
use App\Models\AiPrediction;
use App\Models\Alerte;
use App\Models\Quartier;
use App\Models\User;
use App\Services\MeteoActuelle;
use App\Services\VigilanceAiService;
use App\Services\WeatherService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

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

        // Périmètre du gestionnaire : sa zone uniquement (admin = tout).
        $base = Alerte::query()
            ->when($user && $user->isGestionnaire(), fn ($q) => $q->parQuartier((int) $quartierId));

        // Chiffres clés : calculés sur le périmètre SEUL, indépendamment des
        // filtres d'affichage — sinon « total » varierait selon le filtre.
        $stats = [
            'total' => (clone $base)->count(),
            'actives' => (clone $base)->actives()->count(),
            'programmees' => (clone $base)->programmees()->count(),
            'terminees' => (clone $base)->terminees()->count(),
            'brouillons' => (clone $base)->where('validee', false)->count(),
            'rouge' => (clone $base)->where('niveau', NiveauAlerte::Rouge->value)->count(),
            'orange' => (clone $base)->where('niveau', NiveauAlerte::Orange->value)->count(),
            'jaune' => (clone $base)->where('niveau', NiveauAlerte::Jaune->value)->count(),
        ];

        // Liste filtrable (filtres d'affichage uniquement).
        $query = (clone $base)->with('quartiers')
            ->when($request->filled('quartier_id'), fn ($q) => $q->parQuartier((int) $request->input('quartier_id')))
            ->when(in_array($request->input('niveau'), array_column(NiveauAlerte::cases(), 'value'), true),
                fn ($q) => $q->niveau(NiveauAlerte::from($request->input('niveau'))));

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

    public function show(int $id)
    {
        $this->authorizeAlerte($id);

        $alerte = Alerte::with(['quartiers', 'user', 'validateur'])->findOrFail($id);

        return view('back.alertes.show', compact('alerte'));
    }

    public function store(StoreAlerteRequest $request)
    {
        $data = $this->donnees($request);
        $data['user_id'] = $request->user()?->id;

        $alerte = Alerte::create($data);
        $alerte->quartiers()->sync($this->quartierIds($request->user(), (array) $request->input('quartier_ids', [])));

        // Boucle de retour : le gestionnaire a-t-il suivi la suggestion IA ?
        $this->finaliserPrediction($request, $alerte);

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

        $this->finaliserPrediction($request, $alerte);

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

        $alerte = Alerte::findOrFail($id);

        $alerte->update([
            'validee' => true,
            'validee_le' => now(),
            'validee_par' => $request->user()?->id,
        ]);

        // Publication = niveau effectivement retenu : on fige la correction sur
        // les prédictions IA rattachées (niveau_final connu côté modèle+règles).
        $this->finaliserPredictionsLiees($alerte);

        return redirect()->route('back.alertes.index')
            ->with('success', 'Alerte validée et publiée.');
    }

    /**
     * « Pré-remplir météo + IA » : récupère la météo (live ou override manuel),
     * classe le niveau via le moteur HYBRIDE (classifieur ML + plancher de
     * règles), expose les probabilités / facteurs / désaccord, et — à la
     * demande — rédige un message (générateur local par défaut).
     *
     * Chaque appel trace une ligne `ai_predictions` (features + niveaux) pour la
     * boucle de retour d'expérience. Ne renvoie jamais 500 : `ok:false` si la
     * météo est indisponible, repli sur les règles seules si l'IA défaille.
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
        $overrideManuel = $request->filled('temperature_actuelle');

        // Acquisition météo + prévisions : isolée dans un try/catch pour que
        // l'endpoint ne renvoie JAMAIS 500, même si WeatherService régresse.
        $previsions = [];

        try {
            if ($overrideManuel) {
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

            // Prévisions horaires : jamais en override manuel (pas d'appel
            // réseau quand la température est saisie à la main). Non bloquant.
            if (! $overrideManuel) {
                $previsions = ($latitude !== null && $longitude !== null)
                    ? $this->meteo->previsions($latitude, $longitude, 48)
                    : $this->meteo->previsionsPourQuartiers($quartiers, 48);
            }
        } catch (\Throwable $e) {
            Log::warning('Prévisions météo : repli sans prévision horaire', ['message' => $e->getMessage()]);
            $meteo = $meteo ?? null;
            $previsions = [];
        }

        if (! $meteo) {
            return response()->json([
                'ok' => false,
                'raison' => 'Météo indisponible (aucune coordonnée ou service injoignable). Saisissez la température manuellement.',
            ]);
        }

        // Historique sur TOUS les quartiers visés, pas seulement le premier.
        $historique = $ids !== [] ? $this->ia->historiquePourQuartier($ids, 3) : [];

        $contexte = [
            'temperature' => $meteo->temperature,
            'ressentie' => $meteo->ressentie,
            'humidite' => $meteo->humidite,
            'seuil' => $seuil,
            'historique' => $historique,
            'previsions' => $previsions,
        ];

        $prediction = $this->predireSansEchouer($contexte);
        $niveau = $prediction['niveau'];

        $payload = [
            'ok' => true,
            'niveau' => $niveau->value,
            'niveau_label' => $niveau->label(),
            'badge_classes' => $niveau->badgeClasses(),
            'niveau_modele' => $prediction['niveau_modele']?->value,
            'niveau_regles' => $prediction['niveau_regles']?->value,
            'temperature' => round($meteo->temperature, 1),
            'ressentie' => $meteo->ressentie,
            'humidite' => $meteo->humidite,
            'source' => $meteo->source,
            'ia_source' => $prediction['source'],
            'historique' => $historique,
            'raison' => $prediction['raison'],
            'probabilites' => $prediction['probabilites'],
            'confiance' => $prediction['confiance'],
            'facteurs' => $prediction['facteurs'],
            'desaccord' => $prediction['desaccord'],
            'model_version' => $prediction['model_version'],
            'modele_disponible' => $prediction['modele_disponible'],
            'message' => null,
            'prediction_id' => null,
        ];

        // Trace de la prédiction : contexte brut reproductible + niveaux, pour la
        // boucle de retour (l'alerte sera rattachée à l'enregistrement).
        try {
            $trace = AiPrediction::create([
                'alerte_id' => null,
                'quartier_ids' => $ids,
                'context' => $contexte,
                'niveau_modele' => $prediction['niveau_modele']?->value,
                'probabilites' => $prediction['probabilites'],
                'niveau_regles' => $prediction['niveau_regles']?->value,
                'niveau_final' => null,
                'model_version' => $prediction['model_version'],
                'source' => $prediction['source'],
            ]);

            $payload['prediction_id'] = $trace->id;
        } catch (\Throwable $e) {
            Log::warning('Trace IA canicule : enregistrement impossible', ['message' => $e->getMessage()]);
        }

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
                'temperature_ressentie' => $meteo->ressentie,
                'humidite' => $meteo->humidite,
                'raison' => $prediction['raison'],
            ], $profils);
        }

        return response()->json($payload);
    }

    /**
     * Prédiction hybride infaillible : le moteur ne lève jamais, mais on
     * garantit ici un repli « règles seules » même en cas d'erreur imprévue,
     * pour que `prefill` ne renvoie JAMAIS 500.
     *
     * @param  array<string, mixed>  $contexte
     * @return array<string, mixed>
     */
    private function predireSansEchouer(array $contexte): array
    {
        try {
            return $this->ia->predict($contexte);
        } catch (\Throwable $e) {
            Log::warning('Prévision IA canicule : repli sur les règles', ['message' => $e->getMessage()]);

            $analyse = $this->ia->analyse(
                (float) (is_numeric($contexte['temperature'] ?? null) ? $contexte['temperature'] : config('ai.defaults.temperature', 25.0)),
                is_numeric($contexte['humidite'] ?? null) ? (int) $contexte['humidite'] : null,
                is_array($contexte['historique'] ?? null) ? $contexte['historique'] : [],
                is_numeric($contexte['seuil'] ?? null) ? (float) $contexte['seuil'] : null,
            );

            return [
                'niveau' => $analyse['niveau'],
                'niveau_final' => $analyse['niveau']->value,
                'niveau_modele' => null,
                'niveau_regles' => $analyse['niveau_regles'],
                'probabilites' => [],
                'confiance' => 0.0,
                'facteurs' => [],
                'desaccord' => false,
                'raison' => $analyse['raison'],
                'model_version' => null,
                'source' => 'regles',
                'contexte' => $contexte,
                'modele_disponible' => false,
            ];
        }
    }

    /**
     * Rattache la prédiction tracée au formulaire (champ caché) et fige le
     * niveau réellement enregistré comme `niveau_final` (correction).
     */
    private function finaliserPrediction(Request $request, Alerte $alerte): void
    {
        $id = $request->input('ai_prediction_id');

        if ($id === null || $id === '') {
            return;
        }

        try {
            $prediction = AiPrediction::find((int) $id);

            if ($prediction) {
                $prediction->forceFill([
                    'alerte_id' => $alerte->id,
                    'niveau_final' => $alerte->niveau?->value,
                ])->save();
            }
        } catch (\Throwable $e) {
            Log::warning('Trace IA canicule : rattachement impossible', ['message' => $e->getMessage()]);
        }
    }

    /**
     * Fige le niveau publié sur les prédictions déjà rattachées à l'alerte
     * (cas d'un brouillon issu de `ai:predict-daily` validé sans repasser par
     * le formulaire).
     */
    private function finaliserPredictionsLiees(Alerte $alerte): void
    {
        try {
            AiPrediction::query()
                ->where('alerte_id', $alerte->id)
                ->whereNull('niveau_final')
                ->update(['niveau_final' => $alerte->niveau?->value]);
        } catch (\Throwable $e) {
            Log::warning('Trace IA canicule : finalisation impossible', ['message' => $e->getMessage()]);
        }
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
        unset($data['quartier_ids'], $data['ai_prediction_id']);

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
