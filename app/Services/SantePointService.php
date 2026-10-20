<?php

namespace App\Services;

use App\Enums\Affluence;
use App\Enums\Sentiment;
use App\Enums\SantePoint;
use App\Enums\TypePointFraicheur;
use App\Models\Avis;
use App\Models\PointFraicheur;
use App\Services\Ai\FraicheurAiClient;
use Carbon\CarbonInterface;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * IA du module 3 — SANTÉ PRÉDITE d'un point de fraîcheur, à partir de ses AVIS.
 *
 * Question posée au modèle : « ce point va-t-il recevoir au moins deux avis
 * négatifs dans les 14 prochains jours ? ». Le contexte relie les deux tables
 * du module : les caractéristiques du point (type, capacité, équipements,
 * horaires, ancienneté) et l'historique de ses avis (notes, tendance, part de
 * négatifs, affluence ressentie, sentiment, plaintes récurrentes), plus la
 * pression thermique (vigilance canicule du module 1 + météo live).
 *
 * L'intelligence vit dans le microservice Python (`ai-service/app/fraicheur/`,
 * classifieur entraîné et calibré). Laravel n'est qu'un ORCHESTRATEUR, avec
 * deux garanties non négociables :
 *
 *  - PLANCHER DE SÉCURITÉ : `score_final = max(score_modele, score_regles)`.
 *  - REPLI : service indisponible → RÈGLES PHP (`scoreDepuisContexte`),
 *    strictement alignées sur Python (fixture tests/fixtures/fraicheur_rules_cases.json).
 */
class SantePointService
{
    /** Fenêtre « récente » des avis (jours). */
    public const FENETRE_JOURS = 30;

    /** Fenêtre de comparaison pour la tendance : (J-120, J-30]. */
    public const FENETRE_PRECEDENTE_JOURS = 90;

    /** Horizon de la prédiction (jours). */
    public const HORIZON_JOURS = 14;

    /** Nombre d'avis négatifs qui définit une dégradation sur l'horizon. */
    public const SEUIL_NEGATIFS = 2;

    /** Plafond de « jours depuis le dernier avis négatif » (aucun = 90). */
    private const CAP_JOURS_NEGATIF = 90;

    /** Prior bayésien de la note (identique au moteur de recommandation). */
    private const NOTE_A_PRIORI = 3.5;

    private const POIDS_A_PRIORI = 3;

    /** Codes des types de point (alignés sur `features.TYPE_CODES` Python). */
    private const TYPE_CODES = ['parc' => 0, 'salle_climatisee' => 1, 'fontaine' => 2];

    /**
     * Thèmes de l'analyse de sentiment → famille de plainte du modèle.
     *
     * @var array<string, list<string>>
     */
    private const PLAINTES = [
        'plaintes_proprete_30j' => ['Propreté'],
        'plaintes_chaleur_30j' => ['Climatisation / chaleur', 'Ombre', 'Eau'],
        'plaintes_equipements_30j' => ['Équipements'],
    ];

    /** Seuils de canicule (°C) dérivés de la température : 35 / 38 / 41. */
    private const SEUILS_CANICULE = [35.0, 38.0, 41.0];

    public function __construct(
        private SentimentAvisService $sentiment,
        private RecommandationFraicheurService $reco,
        private ?FraicheurAiClient $client = null,
    ) {}

    private function client(): FraicheurAiClient
    {
        return $this->client ??= app(FraicheurAiClient::class);
    }

    /**
     * Un avis est NÉGATIF si sa note est <= 2 ou si son commentaire l'est.
     */
    public static function estNegatif(Avis $avis): bool
    {
        return (int) $avis->note <= 2 || $avis->sentiment === Sentiment::Negatif;
    }

    /**
     * Santé prédite d'un point.
     *
     * @return array<string, mixed>
     */
    public function evaluer(PointFraicheur $point, ?CarbonInterface $moment = null): array
    {
        return $this->evaluerPlusieurs([$point], $moment)->get($point->id);
    }

    /**
     * Santé prédite de plusieurs points : requêtes groupées et UN SEUL appel IA.
     *
     * @param  iterable<PointFraicheur>  $points
     * @return Collection<int, array{
     *     point: PointFraicheur,
     *     score: int,
     *     niveau: SantePoint,
     *     p14: float|null,
     *     score_modele: int|null,
     *     score_regles: int,
     *     niveau_modele: SantePoint|null,
     *     niveau_regles: SantePoint,
     *     desaccord: bool,
     *     facteurs: list<string>,
     *     actions: list<string>,
     *     raison: string,
     *     source: string,
     *     model_version: string|null,
     *     contexte: array<string, mixed>
     * }>  indexée par l'id du point
     */
    public function evaluerPlusieurs(iterable $points, ?CarbonInterface $moment = null): Collection
    {
        $points = collect($points)->filter(fn ($p): bool => $p instanceof PointFraicheur)->keyBy('id');

        if ($points->isEmpty()) {
            return collect();
        }

        $contextes = $this->contextes($points, $moment);

        $ia = $this->santeIa($contextes, $points);

        return $points->map(function (PointFraicheur $point) use ($contextes, $ia): array {
            $contexte = $contextes[$point->id];

            return $this->resultat($point, $contexte, $ia[$point->id] ?? null);
        });
    }

    /**
     * Contexte BRUT (features du modèle) de plusieurs points, calculé avec les
     * mêmes définitions que le simulateur Python (`features.py`).
     *
     * @param  Collection<int, PointFraicheur>  $points  indexée par id
     * @return array<int, array<string, mixed>>
     */
    public function contextes(Collection $points, ?CarbonInterface $moment = null): array
    {
        $moment = Carbon::instance($moment ?? Carbon::now());
        $ids = $points->keys()->all();

        // Totaux historiques (note moyenne bayésienne).
        $totaux = Avis::query()
            ->whereIn('point_fraicheur_id', $ids)
            ->where('created_at', '<=', $moment)
            ->selectRaw('point_fraicheur_id, count(*) as nb, sum(note) as somme')
            ->groupBy('point_fraicheur_id')
            ->get()
            ->keyBy('point_fraicheur_id');

        // Avis des 120 derniers jours : fenêtre récente + fenêtre précédente.
        $debutPrecedente = $moment->copy()->subDays(self::FENETRE_JOURS + self::FENETRE_PRECEDENTE_JOURS);
        $recents = Avis::query()
            ->whereIn('point_fraicheur_id', $ids)
            ->where('created_at', '>', $debutPrecedente)
            ->where('created_at', '<=', $moment)
            ->get(['id', 'point_fraicheur_id', 'note', 'commentaire', 'affluence', 'sentiment', 'score_sentiment', 'created_at'])
            ->groupBy('point_fraicheur_id');

        $thermique = $this->thermique($points);

        return $points->map(fn (PointFraicheur $point): array => $this->contextePoint(
            $point,
            $moment,
            (int) ($totaux[$point->id]->nb ?? 0),
            (float) ($totaux[$point->id]->somme ?? 0),
            $recents->get($point->id, collect()),
            $thermique,
        ))->all();
    }

    /**
     * @param  Collection<int, Avis>  $avis  avis des 120 derniers jours
     * @param  array{temperature: float|null, canicule: int}  $thermique
     * @return array<string, mixed>
     */
    private function contextePoint(
        PointFraicheur $point,
        Carbon $moment,
        int $nbTotal,
        float $sommeNotes,
        Collection $avis,
        array $thermique,
    ): array {
        $debut30 = $moment->copy()->subDays(self::FENETRE_JOURS);

        $avis30 = $avis->filter(fn (Avis $a): bool => $a->created_at->greaterThan($debut30));
        $avisPrecedents = $avis->reject(fn (Avis $a): bool => $a->created_at->greaterThan($debut30));
        $negatifs30 = $avis30->filter(fn (Avis $a): bool => self::estNegatif($a));

        $n30 = $avis30->count();
        $nPrec = $avisPrecedents->count();

        $noteMoyenne = ($sommeNotes + self::POIDS_A_PRIORI * self::NOTE_A_PRIORI) / ($nbTotal + self::POIDS_A_PRIORI);
        $moyenne30 = $n30 > 0 ? (float) $avis30->avg('note') : null;
        $moyennePrec = $nPrec > 0 ? (float) $avisPrecedents->avg('note') : null;

        $evolution = ($moyenne30 !== null && $moyennePrec !== null && $n30 >= 2 && $nPrec >= 2)
            ? $moyenne30 - $moyennePrec
            : 0.0;

        // Jours depuis le dernier avis négatif (plafonné à 90).
        $dernierNegatif = $avis->filter(fn (Avis $a): bool => self::estNegatif($a))->max('created_at');
        $joursNegatif = $dernierNegatif !== null
            ? min(self::CAP_JOURS_NEGATIF, (int) floor($dernierNegatif->diffInDays($moment, true)))
            : self::CAP_JOURS_NEGATIF;

        $renseignees = $avis30->filter(fn (Avis $a): bool => $a->affluence !== null);
        $fortes = $renseignees->filter(fn (Avis $a): bool => $a->affluence === Affluence::Forte)->count();

        // Plaintes : avis négatifs récents dont le commentaire évoque le thème.
        $plaintes = array_fill_keys(array_keys(self::PLAINTES), 0);
        foreach ($negatifs30 as $negatif) {
            $themes = $this->sentiment->analyser($negatif->commentaire, (int) $negatif->note)['themes'];
            foreach (self::PLAINTES as $cle => $familles) {
                if (array_intersect($themes, $familles) !== []) {
                    $plaintes[$cle]++;
                }
            }
        }

        $type = $point->type?->value ?? TypePointFraicheur::Parc->value;
        $depuis = $point->valide_le ?? $point->created_at ?? $moment;

        return [
            'point_id' => $point->id,
            'nom' => $point->nom,
            // --- Le point ---
            'type' => $type,
            'type_code' => self::TYPE_CODES[$type] ?? 0,
            'capacite' => (int) ($point->capacite ?? 0),
            'climatise' => (bool) $point->climatise,
            'ombrage' => (bool) $point->ombrage,
            'eau_potable' => (bool) $point->eau_potable,
            'accessible_pmr' => (bool) $point->accessible_pmr,
            'heures_ouverture_jour' => $this->heuresOuverture($point),
            'anciennete_jours' => max(0, (int) floor(Carbon::instance($depuis)->diffInDays($moment, false))),
            'a_photo' => $point->photo !== null,
            // --- Ses avis ---
            'note_moyenne' => round($noteMoyenne, 3),
            'nb_avis_30j' => $n30,
            'note_moyenne_30j' => round($moyenne30 ?? $noteMoyenne, 3),
            'evolution_note_30j' => round($evolution, 3),
            'part_negatifs_30j' => $n30 > 0 ? round($negatifs30->count() / $n30, 4) : 0.0,
            'jours_depuis_dernier_negatif' => $joursNegatif,
            'part_affluence_forte_30j' => $renseignees->isNotEmpty() ? round($fortes / $renseignees->count(), 4) : 0.0,
            'sentiment_moyen_30j' => $n30 > 0 ? round((float) $avis30->avg(fn (Avis $a): float => (float) $a->score_sentiment), 4) : 0.0,
            ...$plaintes,
            // --- Le contexte ---
            'temperature' => $thermique['temperature'],
            'canicule_niveau' => $thermique['canicule'],
            'mois' => (int) $moment->month,
        ];
    }

    /**
     * Heures d'ouverture par jour (24 si ouvert 24h/24, horaires de nuit gérés).
     */
    private function heuresOuverture(PointFraicheur $point): float
    {
        if ($point->ouvert_24h) {
            return 24.0;
        }

        if (! $point->heure_ouverture || ! $point->heure_fermeture) {
            return 12.0;
        }

        [$ho, $mo] = array_map('intval', explode(':', substr($point->heure_ouverture, 0, 5)));
        [$hf, $mf] = array_map('intval', explode(':', substr($point->heure_fermeture, 0, 5)));

        $minutes = ($hf * 60 + $mf) - ($ho * 60 + $mo);
        if ($minutes <= 0) {
            $minutes += 24 * 60; // fermeture après minuit
        }

        return round($minutes / 60, 2);
    }

    /**
     * Pression thermique du réseau : température live (WeatherAPI) au centre
     * des points + niveau de canicule = max(vigilance active, seuils 35/38/41 °C).
     * Calculée UNE fois pour tous les points (même ville).
     *
     * @param  Collection<int, PointFraicheur>  $points
     * @return array{temperature: float|null, canicule: int}
     */
    private function thermique(Collection $points): array
    {
        $latitude = (float) $points->avg('latitude');
        $longitude = (float) $points->avg('longitude');

        $meteo = $this->reco->meteo($latitude, $longitude);
        $temperature = $meteo !== null ? round((float) $meteo->temperature, 1) : null;

        $parTemperature = 0;
        foreach (self::SEUILS_CANICULE as $niveau => $seuil) {
            if ($temperature !== null && $temperature >= $seuil) {
                $parTemperature = $niveau + 1;
            }
        }

        return [
            'temperature' => $temperature,
            'canicule' => max($parTemperature, $this->reco->graviteCanicule($latitude, $longitude)),
        ];
    }

    /**
     * Appel IA groupé — tableau indexé par point, vide si indisponible.
     *
     * @param  array<int, array<string, mixed>>  $contextes
     * @param  Collection<int, PointFraicheur>  $points
     * @return array<int, array<string, mixed>>
     */
    private function santeIa(array $contextes, Collection $points): array
    {
        $reponse = $this->client()->sante(array_values($contextes));

        if (! is_array($reponse)) {
            return [];
        }

        $parPoint = [];
        foreach ($reponse as $ligne) {
            if (is_array($ligne) && isset($ligne['point_id']) && $points->has((int) $ligne['point_id'])) {
                $parPoint[(int) $ligne['point_id']] = $ligne;
            }
        }

        return $parPoint;
    }

    /**
     * Combine règles (plancher) et réponse IA (si disponible).
     *
     * @param  array<string, mixed>  $contexte
     * @param  array<string, mixed>|null  $ia
     * @return array<string, mixed>
     */
    private function resultat(PointFraicheur $point, array $contexte, ?array $ia): array
    {
        $base = $this->scoreDepuisContexte($contexte);

        $resultat = [
            'point' => $point,
            'score' => $base['score'],
            'niveau' => $base['niveau'],
            'p14' => null,
            'score_modele' => null,
            'score_regles' => $base['score'],
            'niveau_modele' => null,
            'niveau_regles' => $base['niveau'],
            'desaccord' => false,
            'facteurs' => $base['facteurs'],
            'actions' => $base['actions'],
            'raison' => $base['raison'],
            'source' => 'regles',
            'model_version' => null,
            'contexte' => $contexte,
        ];

        if ($ia === null || ($ia['source'] ?? null) !== 'modele' || ! is_numeric($ia['score_modele'] ?? null)) {
            return $resultat;
        }

        // Plancher recalculé côté Laravel : jamais sous les règles PHP.
        $scoreModele = max(0, min(100, (int) $ia['score_modele']));
        $scoreFinal = max($scoreModele, $base['score']);

        return array_merge($resultat, [
            'score' => $scoreFinal,
            'niveau' => SantePoint::fromScore($scoreFinal),
            'p14' => is_numeric($ia['p14'] ?? null) ? (float) $ia['p14'] : null,
            'score_modele' => $scoreModele,
            'niveau_modele' => SantePoint::fromScore($scoreModele),
            'desaccord' => SantePoint::fromScore($scoreModele) !== $base['niveau'],
            'facteurs' => is_array($ia['facteurs'] ?? null) && $ia['facteurs'] !== [] ? array_values($ia['facteurs']) : $base['facteurs'],
            'actions' => $this->actions($contexte, $scoreFinal),
            'raison' => is_string($ia['raison'] ?? null) && $ia['raison'] !== '' ? $ia['raison'] : $base['raison'],
            'source' => 'modele',
            'model_version' => is_string($ia['model_version'] ?? null) ? $ia['model_version'] : null,
        ]);
    }

    /**
     * Score déterministe calculé SANS accès base (portable et testable) à partir
     * d'un contexte brut. PLANCHER DE SÉCURITÉ du module 3 : formule reproduite
     * à l'identique côté Python (`app/fraicheur/rules.py`) et verrouillée par la
     * fixture partagée `tests/fixtures/fraicheur_rules_cases.json`.
     *
     *   note récente (0–35) · négatifs (0–25) · tendance (0–15)
     *   plaintes (0–15) · pression canicule / affluence (0–10)
     *
     * @param  array<string, mixed>  $contexte
     * @return array{score: int, niveau: SantePoint, raison: string, facteurs: list<string>, actions: list<string>, composantes: array<string, int>}
     */
    public function scoreDepuisContexte(array $contexte): array
    {
        $v = $this->lecture($contexte);
        $c = $this->composantes($v);

        $score = max(0, min(100, array_sum($c)));

        $noteTexte = $v['nb'] >= 1 && $v['note'] !== null ? $this->formatNote($v['note']).'/5' : '—';

        return [
            'score' => $score,
            'niveau' => SantePoint::fromScore($score),
            'raison' => sprintf(
                '%d avis sur %d j · note récente %s · %d %% négatifs',
                $v['nb'],
                self::FENETRE_JOURS,
                $noteTexte,
                $this->arrondi($v['part_negatifs'] * 100),
            ),
            'facteurs' => $this->facteurs($v, $c),
            'actions' => $this->actions($contexte, $score),
            'composantes' => $c,
        ];
    }

    /**
     * Actions conseillées à l'administrateur (ordre fixe, parité Python).
     *
     * @param  array<string, mixed>  $contexte
     * @return list<string>
     */
    public function actions(array $contexte, int $score): array
    {
        $v = $this->lecture($contexte);
        $c = $this->composantes($v);
        $actions = [];

        if ($v['proprete'] > 0) {
            $actions[] = 'Programmer un nettoyage du site';
        }
        if ($v['chaleur'] > 0) {
            $actions[] = "Vérifier la climatisation, l'ombrage et l'accès à l'eau";
        }
        if ($v['equipements'] > 0) {
            $actions[] = 'Réparer les équipements signalés';
        }
        if ($v['nb'] >= 2 && $v['part_affluence'] >= 0.5) {
            $actions[] = "Augmenter la capacité d'accueil ou orienter vers un point voisin";
        }
        if ($c['note'] >= 25 || $c['tendance'] >= 8) {
            $actions[] = 'Inspecter le site et contacter son gestionnaire';
        }
        if ($actions === [] && $score >= 25) {
            $actions[] = 'Surveiller les prochains avis';
        }

        return $actions;
    }

    /**
     * Normalise le contexte brut (mêmes conversions que `rules._lecture`).
     *
     * @param  array<string, mixed>  $contexte
     * @return array{nb: int, note: float|null, part_negatifs: float, evolution: float, proprete: int, chaleur: int, equipements: int, canicule: int, part_affluence: float}
     */
    private function lecture(array $contexte): array
    {
        $note = $this->nombre($contexte['note_moyenne_30j'] ?? null);

        return [
            'nb' => max(0, $this->entier($contexte['nb_avis_30j'] ?? null)),
            'note' => $note !== null ? max(1.0, min(5.0, $note)) : null,
            'part_negatifs' => $this->part($contexte['part_negatifs_30j'] ?? null),
            'evolution' => $this->nombre($contexte['evolution_note_30j'] ?? null) ?? 0.0,
            'proprete' => max(0, $this->entier($contexte['plaintes_proprete_30j'] ?? null)),
            'chaleur' => max(0, $this->entier($contexte['plaintes_chaleur_30j'] ?? null)),
            'equipements' => max(0, $this->entier($contexte['plaintes_equipements_30j'] ?? null)),
            'canicule' => max(0, min(3, $this->entier($contexte['canicule_niveau'] ?? null))),
            'part_affluence' => $this->part($contexte['part_affluence_forte_30j'] ?? null),
        ];
    }

    /**
     * @param  array{nb: int, note: float|null, part_negatifs: float, evolution: float, proprete: int, chaleur: int, equipements: int, canicule: int, part_affluence: float}  $v
     * @return array{note: int, negatifs: int, tendance: int, plaintes: int, pression: int}
     */
    private function composantes(array $v): array
    {
        // 1) Note récente (0–35) : au moins un avis sur 30 j.
        $note = 0;
        if ($v['nb'] >= 1 && $v['note'] !== null) {
            $note = match (true) {
                $v['note'] <= 2.0 => 35,
                $v['note'] <= 2.5 => 25,
                $v['note'] <= 3.0 => 15,
                default => 0,
            };
        }

        // 2) Part d'avis négatifs (0–25) : au moins deux avis.
        $negatifs = $v['nb'] >= 2 ? $this->arrondi($v['part_negatifs'] * 25) : 0;

        // 3) Tendance de la note vs les 90 j précédents (0–15).
        $tendance = match (true) {
            $v['nb'] >= 2 && $v['evolution'] <= -1.0 => 15,
            $v['nb'] >= 2 && $v['evolution'] <= -0.5 => 8,
            default => 0,
        };

        // 4) Plaintes récurrentes (0–15).
        $plaintes = min(15, 5 * ($v['proprete'] + $v['chaleur'] + $v['equipements']));

        // 5) Pression : canicule forte + saturation ressentie (0–10).
        $pression = ($v['canicule'] >= 2 ? 5 : 0) + ($v['nb'] >= 2 && $v['part_affluence'] >= 0.5 ? 5 : 0);

        return [
            'note' => $note,
            'negatifs' => $negatifs,
            'tendance' => $tendance,
            'plaintes' => $plaintes,
            'pression' => $pression,
        ];
    }

    /**
     * @param  array{nb: int, note: float|null, part_negatifs: float, evolution: float, proprete: int, chaleur: int, equipements: int, canicule: int, part_affluence: float}  $v
     * @param  array{note: int, negatifs: int, tendance: int, plaintes: int, pression: int}  $c
     * @return list<string>
     */
    private function facteurs(array $v, array $c): array
    {
        $facteurs = [];

        if ($c['note'] > 0) {
            $facteurs[] = sprintf('Note moyenne sur %d j : %s/5', self::FENETRE_JOURS, $this->formatNote((float) $v['note']));
        }
        if ($c['negatifs'] > 0) {
            $facteurs[] = sprintf("%d %% d'avis négatifs sur %d j", $this->arrondi($v['part_negatifs'] * 100), self::FENETRE_JOURS);
        }
        if ($c['tendance'] > 0) {
            $facteurs[] = sprintf('Note en baisse de %s point(s)', $this->formatNote(abs($v['evolution'])));
        }
        if ($v['proprete'] > 0) {
            $facteurs[] = "{$v['proprete']} plainte(s) « propreté »";
        }
        if ($v['chaleur'] > 0) {
            $facteurs[] = "{$v['chaleur']} plainte(s) « chaleur / climatisation »";
        }
        if ($v['equipements'] > 0) {
            $facteurs[] = "{$v['equipements']} plainte(s) « équipements »";
        }
        if ($v['canicule'] >= 2) {
            $facteurs[] = "Vigilance canicule {$v['canicule']}/3";
        }
        if ($v['nb'] >= 2 && $v['part_affluence'] >= 0.5) {
            $facteurs[] = sprintf('Affluence forte signalée dans %d %% des avis', $this->arrondi($v['part_affluence'] * 100));
        }

        return $facteurs;
    }

    /**
     * Arrondi « .5 vers le haut » robuste, identique au Python `rules._arrondi`.
     */
    private function arrondi(float $valeur): int
    {
        return (int) floor($valeur + 0.5 + 1e-9);
    }

    private function formatNote(float $valeur): string
    {
        return number_format($valeur, 1, ',', '');
    }

    private function nombre(mixed $valeur): ?float
    {
        if ($valeur === null || is_bool($valeur) || ! is_numeric($valeur)) {
            return null;
        }

        $nombre = (float) $valeur;

        return is_finite($nombre) ? $nombre : null;
    }

    private function entier(mixed $valeur): int
    {
        $nombre = $this->nombre($valeur);

        return $nombre !== null ? (int) $nombre : 0;
    }

    private function part(mixed $valeur): float
    {
        $nombre = $this->nombre($valeur);

        return $nombre !== null ? max(0.0, min(1.0, $nombre)) : 0.0;
    }
}
