<?php

namespace App\Services;

use App\Enums\NiveauAlerte;
use App\Enums\NiveauRisque;
use App\Enums\StatutCoupure;
use App\Enums\TypeCoupure;
use App\Models\Alerte;
use App\Models\Coupure;
use App\Models\Quartier;
use App\Models\Residence;
use App\Services\Ai\CoupureAiClient;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * Module IA du module 2 (coupures).
 *
 * L'intelligence vit désormais dans le microservice Python (`ai-service/`) :
 * score de risque ENTRAÎNÉ et EXPLICABLE par zone, détecteur d'anomalies
 * spatial (DBSCAN + Poisson) et estimation du temps de rétablissement (ETR).
 *
 * Laravel n'est qu'un ORCHESTRATEUR : il envoie un contexte brut et reçoit un
 * résultat enrichi. Deux garanties non négociables :
 *
 *  - PLANCHER DE SÉCURITÉ : ``score_final = max(score_modele, score_regles)``.
 *    Le modèle ne peut JAMAIS faire baisser le risque sous les règles.
 *  - REPLI : si le service est indisponible (timeout, 5xx, JSON invalide,
 *    désactivé), on retombe sur les RÈGLES PHP déterministes
 *    (``scoreDepuisContexte``), strictement alignées sur Python.
 */
class CoupureRiskAiService
{
    /** Fenêtre d'observation de l'historique des coupures. */
    public const FENETRE_HISTORIQUE_JOURS = 14;

    /** Fenêtre glissante de détection d'un afflux de signalements. */
    public const FENETRE_ANOMALIE_MINUTES = 120;

    /** Nombre de signalements, dans la fenêtre, à partir duquel on alerte. */
    public const SEUIL_ANOMALIE = 3;

    public function __construct(private ?CoupureAiClient $client = null) {}

    /**
     * Client IA « ne lève jamais » (résolu paresseusement si absent).
     */
    private function client(): CoupureAiClient
    {
        return $this->client ??= app(CoupureAiClient::class);
    }

    /**
     * Score de risque d'une zone, avec sa justification détaillée.
     *
     * @return array{
     *     quartier: Quartier,
     *     score: int,
     *     niveau: NiveauRisque,
     *     raison: string,
     *     facteurs: list<string>,
     *     frequence: int,
     *     en_cours: int,
     *     temperature: float|null,
     *     p24: float|null,
     *     p48: float|null,
     *     score_modele: int|null,
     *     score_regles: int,
     *     niveau_modele: NiveauRisque|null,
     *     niveau_regles: NiveauRisque,
     *     desaccord: bool,
     *     source: string,
     *     model_version: string|null
     * }
     */
    public function score(Quartier $quartier, ?float $temperature = null): array
    {
        return $this->scoreAvecContexte($this->contexteZone($quartier, $temperature), $quartier);
    }

    /**
     * Capture d'une zone pour la boucle de retour d'expérience : les features
     * brutes (pour ré-entraîner) + le score enrichi courant.
     *
     * @return array{features: array<string, mixed>, score: array<string, mixed>}
     */
    public function capture(Quartier $quartier): array
    {
        $contexte = $this->contexteZone($quartier);

        return [
            'features' => $contexte,
            'score' => $this->scoreAvecContexte($contexte, $quartier),
        ];
    }

    /**
     * @param  array<string, mixed>  $contexte
     * @return array<string, mixed>
     */
    private function scoreAvecContexte(array $contexte, Quartier $quartier): array
    {
        $base = $this->scoreDepuisContexte($contexte);

        $resultat = [
            'quartier' => $quartier,
            'score' => $base['score'],
            'niveau' => $base['niveau'],
            'raison' => $base['raison'],
            'facteurs' => $base['facteurs'],
            'frequence' => $base['frequence'],
            'en_cours' => $base['en_cours'],
            'temperature' => $base['temperature'],
            'p24' => null,
            'p48' => null,
            'score_modele' => null,
            'score_regles' => $base['score'],
            'niveau_modele' => null,
            'niveau_regles' => $base['niveau'],
            'desaccord' => false,
            'source' => 'regles',
            'model_version' => null,
        ];

        $ia = $this->risqueIa($contexte, $quartier);

        if ($ia === null) {
            return $resultat;
        }

        $scoreRegles = (int) $base['score'];
        $scoreModele = $ia['score_modele'] !== null ? (int) $ia['score_modele'] : $scoreRegles;
        $scoreFinal = max($scoreModele, $scoreRegles);

        $resultat['score'] = $scoreFinal;
        $resultat['niveau'] = NiveauRisque::fromScore($scoreFinal);
        $resultat['p24'] = $ia['p24'] ?? null;
        $resultat['p48'] = $ia['p48'] ?? null;
        $resultat['score_modele'] = $ia['score_modele'] ?? null;
        $resultat['niveau_modele'] = $this->niveauRisque($ia['niveau_modele'] ?? null);
        $resultat['niveau_regles'] = $base['niveau'];
        $resultat['desaccord'] = (bool) ($ia['desaccord'] ?? false);
        $resultat['facteurs'] = is_array($ia['facteurs'] ?? null) && $ia['facteurs'] !== []
            ? array_values($ia['facteurs'])
            : $base['facteurs'];
        $resultat['raison'] = is_string($ia['raison'] ?? null) && $ia['raison'] !== ''
            ? $ia['raison']
            : $base['raison'];
        $resultat['source'] = 'modele';
        $resultat['model_version'] = $ia['model_version'] ?? null;

        return $resultat;
    }

    /**
     * Contexte envoyé au service IA : base des règles + features du modèle,
     * toutes dérivées de la base (aucun feature engineering côté Python pour
     * ces valeurs brutes).
     *
     * @return array<string, mixed>
     */
    private function contexteZone(Quartier $quartier, ?float $temperature = null): array
    {
        $maintenant = Carbon::now();
        $depuis = $maintenant->copy()->subDays(self::FENETRE_HISTORIQUE_JOURS);
        $ilYA7j = $maintenant->copy()->subDays(7);

        $coupures = Coupure::query()
            ->where('quartier_id', $quartier->id)
            ->where('debut', '>=', $depuis)
            ->get(['type', 'statut', 'confirmations', 'debut']);

        $alertes = Alerte::query()
            ->validees()
            ->where('fin', '>=', $maintenant)
            ->parQuartier($quartier->id)
            ->get(['niveau', 'temperature_actuelle']);

        $niveauChaleur = NiveauAlerte::plusGrave($alertes->pluck('niveau')->filter());
        $pic = $alertes->max('temperature_actuelle');
        $pic = $pic !== null ? (float) $pic : $temperature;

        $frequence = $coupures->count();
        $chaudes = $coupures->whereIn('type', [TypeCoupure::Surcharge, TypeCoupure::Delestage])->count();
        $confirmations = (int) $coupures->sum('confirmations');
        $coupures7j = $coupures->filter(
            fn (Coupure $c): bool => $c->debut !== null && $c->debut->greaterThanOrEqualTo($ilYA7j),
        )->count();

        $derniere = $coupures->max('debut');
        $heuresDepuis = $derniere !== null
            ? min(336.0, (float) $maintenant->diffInHours($derniere))
            : 336.0;

        return [
            // Base des règles (parité Python).
            'frequence' => $frequence,
            'en_cours' => $coupures->where('statut', StatutCoupure::EnCours)->count(),
            'chaudes' => $chaudes,
            'confirmations' => $confirmations,
            'niveau_chaleur' => $niveauChaleur?->value,
            'temperature' => $pic,
            // Features du modèle entraîné.
            'coupures_7j' => $coupures7j,
            'coupures_14j' => $frequence,
            'part_chaleur_14j' => $frequence > 0 ? round($chaudes / $frequence, 4) : 0.0,
            'heures_depuis_derniere' => $heuresDepuis,
            'confirmations_total' => $confirmations,
            'ressentie' => $pic,
            'heat_index' => $pic,
            'max_24h' => $pic,
            'max_48h' => $pic,
            'canicule_niveau' => $this->niveauCanicule($niveauChaleur),
            'jours_chauds_consecutifs' => 0,
            'heure' => (int) $maintenant->hour,
            'jour_semaine' => (int) $maintenant->dayOfWeekIso - 1,
            'nb_logements_zone' => (int) $quartier->residences()->sum('nombre_logements'),
            'nb_residences' => (int) $quartier->residences()->count(),
        ];
    }

    /**
     * Appel IA du score de risque d'une zone — ``null`` si indisponible.
     *
     * @param  array<string, mixed>  $contexte
     * @return array<string, mixed>|null
     */
    private function risqueIa(array $contexte, Quartier $quartier): ?array
    {
        $payload = $contexte + [
            'quartier_id' => $quartier->id,
            'nom' => $quartier->nom,
        ];

        $reponse = $this->client()->risk([$payload]);

        if (! is_array($reponse) || ! isset($reponse[0]) || ! is_array($reponse[0])) {
            return null;
        }

        return $reponse[0];
    }

    /**
     * Score déterministe calculé SANS accès base (portable et testable) à partir
     * d'un contexte brut. C'est le PLANCHER DE SÉCURITÉ du module 2 : sa formule
     * est reproduite à l'identique côté Python (`app/coupures/rules.py`) et
     * verrouillée par la fixture partagée `tests/fixtures/coupure_rules_cases.json`.
     *
     * @param  array{
     *     frequence?: int,
     *     en_cours?: int,
     *     chaudes?: int,
     *     confirmations?: int,
     *     niveau_chaleur?: string|null,
     *     temperature?: float|null
     * }  $contexte
     * @return array{
     *     score: int,
     *     niveau: NiveauRisque,
     *     raison: string,
     *     facteurs: list<string>,
     *     frequence: int,
     *     en_cours: int,
     *     temperature: float|null
     * }
     */
    public function scoreDepuisContexte(array $contexte): array
    {
        $frequence = max(0, (int) ($contexte['frequence'] ?? 0));
        $enCours = max(0, (int) ($contexte['en_cours'] ?? 0));
        $chaudes = max(0, (int) ($contexte['chaudes'] ?? 0));
        $confirmations = max(0, (int) ($contexte['confirmations'] ?? 0));
        $niveauChaleur = $this->niveauAlerte($contexte['niveau_chaleur'] ?? null);
        $temperature = isset($contexte['temperature']) && $contexte['temperature'] !== null
            ? (float) $contexte['temperature']
            : null;

        // 1) Fréquence récente (0–35).
        $scoreFrequence = min(35, $frequence * 7);

        // 2) Part des coupures « liées à la chaleur » : surcharge / délestage (0–20).
        $scoreType = $frequence > 0 ? (int) round(min(20, ($chaudes / $frequence) * 20)) : 0;

        // 3) Canicule : alerte couvrant la zone + pic de température (0–35).
        $scoreChaleur = match ($niveauChaleur) {
            NiveauAlerte::Rouge => 35,
            NiveauAlerte::Orange => 22,
            NiveauAlerte::Jaune => 10,
            default => ($temperature !== null && $temperature >= 35) ? 12 : 0,
        };

        // 4) Activité en cours + confirmations des voisins (0–10).
        $scoreActif = min(10, $enCours * 4 + ($confirmations >= 3 ? 2 : 0));

        $score = max(0, min(100, $scoreFrequence + $scoreType + $scoreChaleur + $scoreActif));

        return [
            'score' => $score,
            'niveau' => NiveauRisque::fromScore($score),
            'raison' => sprintf(
                '%d coupure(s) en %d j · alerte %s · %d en cours',
                $frequence,
                self::FENETRE_HISTORIQUE_JOURS,
                $niveauChaleur?->label() ?? 'aucune',
                $enCours,
            ),
            'facteurs' => $this->facteurs($frequence, $chaudes, $niveauChaleur, $temperature, $enCours),
            'frequence' => $frequence,
            'en_cours' => $enCours,
            'temperature' => $temperature,
        ];
    }

    /**
     * Normalise le niveau de chaleur (enum, chaîne ou null).
     */
    private function niveauAlerte(mixed $valeur): ?NiveauAlerte
    {
        if ($valeur instanceof NiveauAlerte) {
            return $valeur;
        }

        return is_string($valeur) ? NiveauAlerte::tryFrom($valeur) : null;
    }

    /**
     * Normalise le niveau de risque renvoyé par le service (chaîne ou null).
     */
    private function niveauRisque(mixed $valeur): ?NiveauRisque
    {
        if ($valeur instanceof NiveauRisque) {
            return $valeur;
        }

        return is_string($valeur) ? NiveauRisque::tryFrom($valeur) : null;
    }

    /**
     * Niveau de canicule entier (0–3) attendu par le modèle Python.
     */
    private function niveauCanicule(?NiveauAlerte $niveau): int
    {
        return match ($niveau) {
            NiveauAlerte::Rouge => 3,
            NiveauAlerte::Orange => 2,
            NiveauAlerte::Jaune => 1,
            default => 0,
        };
    }

    /**
     * Scores de toutes les zones, triés du plus risqué au moins risqué.
     *
     * @param  iterable<Quartier>  $quartiers
     * @return Collection<int, array<string, mixed>>
     */
    public function scoresPourQuartiers(iterable $quartiers): Collection
    {
        return collect($quartiers)
            ->map(fn (Quartier $quartier): array => $this->score($quartier))
            ->sortByDesc('score')
            ->values();
    }

    /**
     * Temps de rétablissement estimé (ETR) des coupures EN COURS.
     *
     * Best-effort : ``null``/vide si le service est indisponible ou le modèle
     * absent. Le résultat est indexé par identifiant de coupure.
     *
     * @param  iterable<Coupure>  $coupures
     * @return array<int, array<string, mixed>>
     */
    public function tempsRetablissement(iterable $coupures): array
    {
        $payload = [];

        foreach ($coupures as $coupure) {
            $payload[] = [
                'id' => $coupure->id,
                'type' => $coupure->type?->value ?? $coupure->type,
                'heure' => (int) ($coupure->debut?->hour ?? 12),
                'jour_semaine' => max(0, (int) ($coupure->debut?->dayOfWeekIso ?? 1) - 1),
                'confirmations' => (int) $coupure->confirmations,
            ];
        }

        if ($payload === []) {
            return [];
        }

        $resultats = $this->client()->etr($payload);

        if (! is_array($resultats)) {
            return [];
        }

        $map = [];

        foreach ($resultats as $resultat) {
            if (is_array($resultat) && isset($resultat['id'])) {
                $map[(int) $resultat['id']] = $resultat;
            }
        }

        return $map;
    }

    /**
     * Zones où un afflux de signalements récents suggère un incident majeur
     * non déclaré. Le service IA (DBSCAN + Poisson) est appelé en premier ;
     * en cas d'indisponibilité, on retombe sur la règle fixe (≥ seuil / zone).
     *
     * @return Collection<int, array<string, mixed>>
     */
    public function detecterAnomalies(?int $fenetreMinutes = null, ?int $seuil = null): Collection
    {
        $fenetre = $fenetreMinutes ?? self::FENETRE_ANOMALIE_MINUTES;
        $seuil = $seuil ?? self::SEUIL_ANOMALIE;

        $incidents = $this->client()->anomalies($this->payloadAnomalies($fenetre, $seuil));

        if (is_array($incidents)) {
            return $this->incidentsDepuisIa($incidents, $fenetre);
        }

        return $this->anomaliesDepuisRegles($fenetre, $seuil);
    }

    /**
     * Payload brut envoyé au détecteur Python (signalements + résidences + zones).
     *
     * @return array<string, mixed>
     */
    private function payloadAnomalies(int $fenetre, int $seuil): array
    {
        $maintenant = Carbon::now();
        $depuis = $maintenant->copy()->subMinutes($fenetre);

        $coupures = Coupure::query()
            ->where('created_at', '>=', $depuis)
            ->where('statut', '!=', StatutCoupure::Resolue->value)
            ->get(['id', 'quartier_id', 'latitude', 'longitude', 'statut', 'confirmations', 'created_at']);

        $signalements = $coupures->map(fn (Coupure $c): array => [
            'id' => $c->id,
            'lat' => $c->latitude,
            'lng' => $c->longitude,
            'quartier_id' => $c->quartier_id,
            'statut' => $c->statut?->value,
            'confirmations' => (int) $c->confirmations,
            'minutes_ago' => $c->created_at !== null
                ? (float) $maintenant->diffInMinutes($c->created_at)
                : null,
        ])->values()->all();

        $quartiers = Quartier::query()
            ->whereNotNull('latitude')
            ->whereNotNull('longitude')
            ->get(['id', 'nom', 'latitude', 'longitude']);

        $total30j = Coupure::query()
            ->where('debut', '>=', $maintenant->copy()->subDays(30))
            ->whereNotNull('quartier_id')
            ->selectRaw('quartier_id, COUNT(*) as total')
            ->groupBy('quartier_id')
            ->pluck('total', 'quartier_id');

        $zones = $quartiers->map(fn (Quartier $q): array => [
            'quartier_id' => $q->id,
            'nom' => $q->nom,
            'lat' => (float) $q->latitude,
            'lng' => (float) $q->longitude,
            // Attendu par le Poisson Python : occurrences 2h sur 30 j ≈ total / 12.
            'n_30j' => (int) round(((int) ($total30j[$q->id] ?? 0)) / 12),
        ])->values()->all();

        $residences = Residence::query()
            ->whereNotNull('latitude')
            ->whereNotNull('longitude')
            ->get(['latitude', 'longitude', 'nombre_logements', 'quartier_id'])
            ->map(fn (Residence $r): array => [
                'lat' => (float) $r->latitude,
                'lng' => (float) $r->longitude,
                'nombre_logements' => (int) $r->nombre_logements,
                'quartier_id' => $r->quartier_id,
            ])->values()->all();

        return [
            'signalements' => $signalements,
            'residences' => $residences,
            'zones' => $zones,
            'fenetre_minutes' => $fenetre,
            'seuil' => $seuil,
        ];
    }

    /**
     * @param  array<int, array<string, mixed>>  $incidents
     * @return Collection<int, array<string, mixed>>
     */
    private function incidentsDepuisIa(array $incidents, int $fenetre): Collection
    {
        $ids = collect($incidents)
            ->flatMap(fn (array $i): array => (array) ($i['quartier_ids'] ?? []))
            ->unique()
            ->filter()
            ->all();

        $quartiers = Quartier::whereKey($ids)->get()->keyBy('id');

        return collect($incidents)->map(function (array $incident) use ($quartiers, $fenetre): array {
            $quartier = $quartiers->get((int) ($incident['quartier_ids'][0] ?? 0));

            return [
                'quartier' => $quartier,
                'nombre' => (int) ($incident['n_reports'] ?? 0),
                'fenetre_minutes' => $fenetre,
                'message' => (string) ($incident['message'] ?? ''),
                'centroid' => $incident['centroid'] ?? null,
                'radius_m' => $incident['radius_m'] ?? null,
                'quartier_ids' => $incident['quartier_ids'] ?? [],
                'logements_estimes' => (int) ($incident['logements_estimes'] ?? 0),
                'confidence' => (float) ($incident['confidence'] ?? 0.0),
                'severity' => $incident['severity'] ?? 'modere',
                'source' => $incident['source'] ?? 'modele',
            ];
        })->values();
    }

    /**
     * Repli : afflux ≥ ``seuil`` signalements par quartier (règle fixe).
     *
     * @return Collection<int, array<string, mixed>>
     */
    private function anomaliesDepuisRegles(int $fenetre, int $seuil): Collection
    {
        $depuis = Carbon::now()->subMinutes($fenetre);

        $parZone = Coupure::query()
            ->where('created_at', '>=', $depuis)
            ->where('statut', '!=', StatutCoupure::Resolue->value)
            ->whereNotNull('quartier_id')
            ->pluck('quartier_id')
            ->countBy()
            ->filter(fn (int $nombre): bool => $nombre >= $seuil)
            ->sortDesc();

        if ($parZone->isEmpty()) {
            return collect();
        }

        $quartiers = Quartier::whereKey($parZone->keys())->get()->keyBy('id');

        return $parZone
            ->map(function (int $nombre, $quartierId) use ($quartiers, $fenetre, $seuil): ?array {
                $quartier = $quartiers->get((int) $quartierId);

                if (! $quartier) {
                    return null;
                }

                return [
                    'quartier' => $quartier,
                    'nombre' => $nombre,
                    'fenetre_minutes' => $fenetre,
                    'message' => sprintf(
                        '%d signalements en %d min sur %s — possible incident majeur non déclaré.',
                        $nombre,
                        $fenetre,
                        $quartier->nom,
                    ),
                    'centroid' => $quartier->hasCoordinates()
                        ? [(float) $quartier->latitude, (float) $quartier->longitude]
                        : null,
                    'radius_m' => 300.0,
                    'quartier_ids' => [(int) $quartierId],
                    'logements_estimes' => (int) $quartier->residences()->sum('nombre_logements'),
                    'confidence' => min(1.0, $nombre / ($seuil * 2)),
                    'severity' => $nombre >= $seuil + 2 ? 'eleve' : 'modere',
                    'source' => 'regles',
                ];
            })
            ->filter()
            ->values();
    }

    /**
     * @return list<string>
     */
    private function facteurs(int $frequence, int $chaudes, ?NiveauAlerte $niveau, ?float $temperature, int $enCours): array
    {
        $facteurs = [];

        if ($frequence > 0) {
            $facteurs[] = sprintf('%d coupure(s) ces %d derniers jours', $frequence, self::FENETRE_HISTORIQUE_JOURS);
        }
        if ($chaudes > 0) {
            $facteurs[] = sprintf('%d coupure(s) de type surcharge/délestage', $chaudes);
        }
        if ($niveau !== null) {
            $facteurs[] = $niveau->label().' en cours';
        }
        if ($temperature !== null) {
            $facteurs[] = sprintf('Pic de température %s°C', number_format($temperature, 1, ',', ''));
        }
        if ($enCours > 0) {
            $facteurs[] = sprintf('%d coupure(s) en cours', $enCours);
        }

        return $facteurs;
    }
}
