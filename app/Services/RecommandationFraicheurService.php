<?php

namespace App\Services;

use App\Enums\Affluence;
use App\Enums\TypePointFraicheur;
use App\Models\Alerte;
use App\Models\Avis;
use App\Models\PointFraicheur;
use App\Models\Quartier;
use App\Models\User;
use App\Support\Geo;
use Carbon\CarbonInterface;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * IA du module 3 — moteur de recommandation des points de fraîcheur.
 *
 * Classe les points VALIDÉS autour de l'habitant selon un score 0–100
 * explicable, somme de 4 critères pondérés :
 *
 *   proximité   35 pts : décroissance exponentielle de la distance à pied ;
 *   préférences 25 pts : climatisé / ombragé / eau potable demandés ;
 *   affluence   25 pts : affluence ESTIMÉE (profil horaire du type de lieu,
 *                        vigilance canicule en cours, affluence ressentie
 *                        dans les avis récents, capacité d'accueil) ;
 *   qualité     15 pts : note moyenne bayésienne des avis.
 *
 * L'accessibilité PMR est une contrainte STRICTE (un point non accessible
 * est inutile pour une personne à mobilité réduite). Un point fermé à
 * l'heure demandée est fortement pénalisé. La pression thermique (alerte
 * canicule du module 1 + température live WeatherAPI) gonfle l'affluence et
 * rend prioritaires les lieux climatisés. Groq rédige ensuite le conseil.
 */
class RecommandationFraicheurService
{
    public function __construct(
        private WeatherService $weather,
        private GroqFraicheurClient $groq,
    ) {}

    public const POIDS_PROXIMITE = 35;

    public const POIDS_PREFERENCES = 25;

    public const POIDS_AFFLUENCE = 25;

    public const POIDS_QUALITE = 15;

    /** Distance caractéristique de la décroissance (km) : ~37 % du score à 1,2 km. */
    private const DISTANCE_CARACTERISTIQUE_KM = 1.2;

    /** Vitesse de marche moyenne (m/min). */
    private const VITESSE_MARCHE = 80;

    /** Prior bayésien : note moyenne a priori et poids (nombre d'avis fictifs). */
    private const NOTE_A_PRIORI = 3.5;

    private const POIDS_A_PRIORI = 3;

    /** Avis récents (jours) pris en compte pour l'affluence ressentie. */
    private const FENETRE_AFFLUENCE_JOURS = 30;

    /**
     * Profils horaires d'affluence (0–1) par type de lieu, heure par heure.
     *
     * @var array<string, array<int, float>>
     */
    private const PROFILS_HORAIRES = [
        // Parcs : faible à midi (soleil), pic en fin de journée.
        'parc' => [0.05, 0.03, 0.02, 0.02, 0.02, 0.05, 0.15, 0.25, 0.3, 0.3, 0.3, 0.25,
            0.2, 0.2, 0.25, 0.35, 0.5, 0.7, 0.85, 0.8, 0.6, 0.4, 0.2, 0.1],
        // Salles climatisées : pic aux heures les plus chaudes (13 h – 17 h).
        'salle_climatisee' => [0.02, 0.02, 0.02, 0.02, 0.02, 0.02, 0.05, 0.1, 0.2, 0.3, 0.45, 0.6,
            0.75, 0.85, 0.9, 0.85, 0.7, 0.5, 0.35, 0.25, 0.15, 0.08, 0.04, 0.02],
        // Fontaines : passages brefs, pic à la mi-journée.
        'fontaine' => [0.02, 0.02, 0.02, 0.02, 0.02, 0.05, 0.1, 0.2, 0.3, 0.4, 0.5, 0.6,
            0.7, 0.7, 0.65, 0.6, 0.55, 0.5, 0.4, 0.3, 0.2, 0.1, 0.05, 0.03],
    ];

    /**
     * Recommandations classées autour d'une position.
     *
     * @param  array{pmr?: bool, climatise?: bool, ombrage?: bool, eau?: bool}  $preferences
     * @return Collection<int, array{
     *     point: PointFraicheur,
     *     score: int,
     *     distance_m: int,
     *     minutes_marche: int,
     *     ouvert: bool,
     *     affluence: array{taux: float, niveau: Affluence, libelle: string},
     *     criteres: array<string, int>,
     *     raisons: list<string>
     * }>
     */
    public function recommander(
        float $latitude,
        float $longitude,
        array $preferences = [],
        ?string $type = null,
        float $rayonKm = 5.0,
        int $limite = 5,
        ?CarbonInterface $moment = null,
        ?int $pressionThermique = null,
    ): Collection {
        $moment ??= Carbon::now();
        $graviteCanicule = $pressionThermique ?? $this->graviteCanicule($latitude, $longitude);

        return PointFraicheur::query()
            ->valides()
            ->avecNotes()
            ->deType($type)
            ->when(! empty($preferences['pmr']), fn ($q) => $q->where('accessible_pmr', true))
            ->with(['quartier', 'avis' => fn ($q) => $q
                ->whereNotNull('affluence')
                ->where('created_at', '>=', Carbon::now()->subDays(self::FENETRE_AFFLUENCE_JOURS))])
            ->get()
            ->map(fn (PointFraicheur $point): array => $this->evaluer($point, $latitude, $longitude, $preferences, $moment, $graviteCanicule))
            ->filter(fn (array $reco): bool => $reco['distance_m'] <= $rayonKm * 1000)
            ->sortByDesc('score')
            ->take($limite)
            ->values();
    }

    /**
     * Évalue un point pour une position donnée (score + justification).
     *
     * @param  array{pmr?: bool, climatise?: bool, ombrage?: bool, eau?: bool}  $preferences
     * @return array<string, mixed>
     */
    public function evaluer(
        PointFraicheur $point,
        float $latitude,
        float $longitude,
        array $preferences,
        CarbonInterface $moment,
        int $graviteCanicule = 0,
    ): array {
        $raisons = [];

        // 1) Proximité.
        $distanceKm = Geo::distanceKm($latitude, $longitude, (float) $point->latitude, (float) $point->longitude);
        $proximite = self::POIDS_PROXIMITE * exp(-$distanceKm / self::DISTANCE_CARACTERISTIQUE_KM);
        $distanceM = (int) round($distanceKm * 1000);
        $minutes = max(1, (int) ceil($distanceM / self::VITESSE_MARCHE));
        $raisons[] = $distanceM < 1000
            ? "À {$distanceM} m ({$minutes} min à pied)"
            : 'À '.number_format($distanceKm, 1, ',', '')." km ({$minutes} min à pied)";

        // 2) Préférences (PMR déjà filtré en amont : contrainte stricte).
        $criteresPrefs = [
            'climatise' => [$point->climatise || $point->type === TypePointFraicheur::SalleClimatisee, 'climatisé'],
            'ombrage' => [$point->ombrage || $point->type === TypePointFraicheur::Parc, 'ombragé'],
            'eau' => [$point->eau_potable || $point->type === TypePointFraicheur::Fontaine, 'eau potable'],
        ];
        $demandes = array_filter($criteresPrefs, fn (string $cle): bool => ! empty($preferences[$cle]), ARRAY_FILTER_USE_KEY);

        if ($demandes === []) {
            // Sans préférence : on valorise la polyvalence du lieu.
            $nbAtouts = count(array_filter($criteresPrefs, fn (array $c): bool => $c[0]));
            $prefs = self::POIDS_PREFERENCES * (0.4 + 0.2 * $nbAtouts);
        } else {
            $satisfaits = array_filter($demandes, fn (array $c): bool => $c[0]);
            $prefs = self::POIDS_PREFERENCES * count($satisfaits) / count($demandes);

            if ($satisfaits !== []) {
                $raisons[] = 'Correspond à vos préférences : '.implode(', ', array_column($satisfaits, 1));
            }
            $manquants = array_diff_key($demandes, $satisfaits);
            if ($manquants !== []) {
                $raisons[] = 'Ne propose pas : '.implode(', ', array_column($manquants, 1));
            }
        }

        if (! empty($preferences['pmr'])) {
            $raisons[] = 'Accessible aux personnes à mobilité réduite';
        }

        // 3) Affluence estimée.
        $affluence = $this->affluenceEstimee($point, $moment, $graviteCanicule);
        $scoreAffluence = self::POIDS_AFFLUENCE * (1 - $affluence['taux']);
        $raisons[] = 'Affluence estimée : '.mb_strtolower($affluence['libelle']).' ('.(int) round($affluence['taux'] * 100).' %)';

        // 4) Qualité : moyenne bayésienne (peu d'avis → proche du prior).
        $nbAvis = (int) ($point->avis_count ?? 0);
        $moyenne = $point->noteMoyenne();
        $bayes = ($nbAvis * ($moyenne ?? 0) + self::POIDS_A_PRIORI * self::NOTE_A_PRIORI) / ($nbAvis + self::POIDS_A_PRIORI);
        $qualite = self::POIDS_QUALITE * ($bayes - 1) / 4;
        if ($moyenne !== null) {
            $raisons[] = 'Noté '.number_format($moyenne, 1, ',', '')."/5 ({$nbAvis} avis)";
        }

        // Pénalité : un point fermé maintenant n'est pas une solution immédiate.
        $ouvert = $point->estOuvert($moment);
        $total = $proximite + $prefs + $scoreAffluence + $qualite;
        if (! $ouvert) {
            $total *= 0.35;
            $raisons[] = 'Fermé à cette heure ('.$point->horaires().')';
        }

        // Canicule orange / rouge : un lieu climatisé devient prioritaire.
        if ($graviteCanicule >= 2 && $ouvert && ($point->climatise || $point->type === TypePointFraicheur::SalleClimatisee)) {
            $total = min(100, $total + 5 * ($graviteCanicule - 1));
            $raisons[] = 'Prioritaire : forte chaleur en cours et lieu climatisé';
        }

        return [
            'point' => $point,
            'score' => (int) round(max(0, min(100, $total))),
            'distance_m' => $distanceM,
            'minutes_marche' => $minutes,
            'ouvert' => $ouvert,
            'affluence' => $affluence,
            'criteres' => [
                'proximite' => (int) round($proximite),
                'preferences' => (int) round($prefs),
                'affluence' => (int) round($scoreAffluence),
                'qualite' => (int) round($qualite),
            ],
            'raisons' => $raisons,
        ];
    }

    /**
     * Affluence estimée (taux d'occupation 0–1) d'un point à un instant.
     *
     * @return array{taux: float, niveau: Affluence, libelle: string}
     */
    public function affluenceEstimee(PointFraicheur $point, CarbonInterface $moment, int $graviteCanicule = 0): array
    {
        $profil = self::PROFILS_HORAIRES[$point->type?->value ?? 'parc'] ?? self::PROFILS_HORAIRES['parc'];
        $taux = $profil[(int) $moment->format('G')];

        // La canicule pousse les habitants vers les points de fraîcheur.
        $taux *= 1 + 0.2 * $graviteCanicule;

        // Petite capacité = sature vite ; grande capacité = absorbe la foule.
        if ($point->capacite !== null) {
            $taux += match (true) {
                $point->capacite < 30 => 0.15,
                $point->capacite > 150 => -0.1,
                default => 0.0,
            };
        }

        // Affluence ressentie par les visiteurs récents (si avis disponibles).
        $ressentis = $point->relationLoaded('avis')
            ? $point->avis->filter(fn (Avis $a): bool => $a->affluence !== null)
            : collect();
        if ($ressentis->isNotEmpty()) {
            $tauxRessenti = $ressentis->avg(fn (Avis $a): float => $a->affluence->taux());
            $taux = 0.5 * $taux + 0.5 * $tauxRessenti;
        }

        $taux = round(max(0.05, min(1.0, $taux)), 2);
        $niveau = match (true) {
            $taux < 0.4 => Affluence::Faible,
            $taux < 0.7 => Affluence::Moyenne,
            default => Affluence::Forte,
        };

        return ['taux' => $taux, 'niveau' => $niveau, 'libelle' => $niveau->label()];
    }

    /**
     * Gravité (0–3) de la vigilance canicule active la plus grave couvrant
     * la position (lecture seule des alertes du module 1).
     */
    public function graviteCanicule(float $latitude, float $longitude): int
    {
        $alertes = Alerte::with('quartiers')->actives()->get();

        if ($alertes->isEmpty()) {
            return 0;
        }

        $quartierId = Quartier::plusProche($latitude, $longitude)?->id;

        return (int) Alerte::filtrerPourLieu($alertes, $quartierId, $latitude, $longitude)
            ->map(fn (Alerte $a): int => $a->niveau->gravite())
            ->max();
    }

    /**
     * Pression thermique (0–3) à la position : la plus forte entre la
     * vigilance canicule active (module 1) et la température mesurée en
     * direct (WeatherAPI, clé WEATHER_API_KEY du .env).
     */
    public function pressionThermique(float $latitude, float $longitude, ?MeteoActuelle $meteo): int
    {
        $parTemperature = match (true) {
            $meteo === null => 0,
            ($meteo->ressentie ?? $meteo->temperature) >= 42 => 3,
            ($meteo->ressentie ?? $meteo->temperature) >= 38 => 2,
            ($meteo->ressentie ?? $meteo->temperature) >= 34 => 1,
            default => 0,
        };

        return max($parTemperature, $this->graviteCanicule($latitude, $longitude));
    }

    public function meteo(float $latitude, float $longitude): ?MeteoActuelle
    {
        try {
            return $this->weather->actuelCache($latitude, $longitude);
        } catch (\Throwable) {
            return null;
        }
    }

    /**
     * Conseil personnalisé rédigé par Groq à partir du classement (le LLM
     * explique le choix, il ne le fait pas). Repli déterministe. Cache 30 min.
     *
     * @param  Collection<int, array<string, mixed>>  $recommandations
     * @param  array<string, bool>  $preferences
     */
    public function conseil(Collection $recommandations, array $preferences, ?MeteoActuelle $meteo, ?User $user = null): ?string
    {
        if ($recommandations->isEmpty()) {
            return null;
        }

        $top = $recommandations->take(3)->map(fn (array $r): string => sprintf(
            '- %s (%s) : score %d/100, %d m, %s, %s, raisons : %s',
            $r['point']->nom,
            $r['point']->type?->label(),
            $r['score'],
            $r['distance_m'],
            $r['ouvert'] ? 'ouvert' : 'fermé',
            mb_strtolower($r['affluence']['libelle']),
            implode(' ; ', $r['raisons']),
        ))->implode("\n");

        $profils = $user
            ? implode(', ', array_map(fn ($p): string => $p->label(), $user->profilsVulnerabilite())) ?: 'aucun profil particulier'
            : 'visiteur non connecté';
        $prefs = implode(', ', array_keys(array_filter($preferences))) ?: 'aucune';
        $temp = $meteo ? number_format($meteo->temperature, 1, ',', '').' °C (ressenti '.number_format($meteo->ressentie ?? $meteo->temperature, 1, ',', '').' °C)' : 'inconnue';

        $cle = 'fraicheur:conseil:'.md5($top.$profils.$prefs.$temp);

        return cache()->remember($cle, now()->addMinutes(30), function () use ($top, $profils, $prefs, $temp, $recommandations): string {
            $texte = $this->groq->completer(
                'Tu es l\'assistant canicule de l\'application ChillNet (Tunisie). En 3 phrases maximum, en français, '
                .'sans markdown, recommande à l\'habitant le point classé n°1 (ne change pas le classement), '
                .'explique pourquoi en t\'appuyant sur les raisons fournies, cite éventuellement le n°2 en alternative, '
                .'et donne un conseil pratique adapté à son profil (hydratation, horaire, trajet à l\'ombre).',
                "Température : {$temp}\nProfil du foyer : {$profils}\nPréférences : {$prefs}\nClassement calculé :\n{$top}",
                600,
            );

            return $texte ?? $this->conseilDeSecours($recommandations->first());
        });
    }

    /**
     * @param  array<string, mixed>  $meilleur
     */
    private function conseilDeSecours(array $meilleur): string
    {
        return sprintf(
            'Nous vous recommandons « %s » à %d min à pied (%s). Partez avec une bouteille d\'eau et privilégiez les rues ombragées.',
            $meilleur['point']->nom,
            $meilleur['minutes_marche'],
            mb_strtolower($meilleur['affluence']['libelle']),
        );
    }
}
