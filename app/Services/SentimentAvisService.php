<?php

namespace App\Services;

use App\Enums\Sentiment;
use App\Enums\StatutPointFraicheur;
use App\Models\Avis;
use App\Models\PointFraicheur;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

/**
 * IA du module 3 — analyse de sentiment des avis :
 *
 *  1. SCORE D'UN AVIS dans [-1, 1] + polarité + thèmes évoqués :
 *     - via Groq (LLM, clé GROQ_API_KEY du .env) quand il est disponible ;
 *     - sinon analyse lexicale française (négations « pas propre »,
 *       intensifieurs « très sale ») mélangée à la note chiffrée (1–5).
 *  2. POINTS MAL NOTÉS RÉCURRENTS : points validés qui accumulent des avis
 *     négatifs sur la période, avec les thèmes qui reviennent → modération.
 *  3. SYNTHÈSE IA des problèmes d'un point pour l'admin (Groq, avec secours).
 */
class SentimentAvisService
{
    public function __construct(private GroqFraicheurClient $groq) {}

    /** Période d'observation pour la détection des points mal notés. */
    public const FENETRE_JOURS = 90;

    /** Nombre minimal d'avis pour juger un point (évite les faux positifs). */
    public const MIN_AVIS = 3;

    /** Part d'avis négatifs à partir de laquelle un point est signalé. */
    public const SEUIL_NEGATIFS = 0.5;

    /** Note moyenne en dessous de laquelle un point est signalé. */
    public const SEUIL_NOTE = 2.5;

    /** Poids de la note chiffrée dans le score final (le reste = texte). */
    private const POIDS_NOTE = 0.4;

    /**
     * Lexique (mots sans accents, en minuscules) => polarité.
     *
     * @var array<string, float>
     */
    private const LEXIQUE = [
        // Positif
        'excellent' => 3, 'parfait' => 3, 'genial' => 3, 'super' => 2.5, 'top' => 2.5,
        'formidable' => 3, 'magnifique' => 3, 'ideal' => 2.5, 'recommande' => 2.5,
        'bien' => 1.5, 'bon' => 1.5, 'bonne' => 1.5, 'agreable' => 2, 'calme' => 1.5,
        'frais' => 2, 'fraiche' => 2, 'fraicheur' => 2, 'propre' => 2, 'propres' => 2,
        'accueillant' => 2, 'accueillante' => 2, 'bienveillant' => 2, 'gentil' => 1.5,
        'sympa' => 1.5, 'pratique' => 1.5, 'confortable' => 2, 'spacieux' => 1.5,
        'ombrage' => 1.5, 'ombragee' => 1.5, 'climatise' => 1.5, 'climatisee' => 1.5,
        'merci' => 1.5, 'satisfait' => 2, 'content' => 2, 'rapide' => 1, 'accessible' => 1.5,
        'securise' => 1.5, 'tranquille' => 1.5, 'reposant' => 2, 'efficace' => 1.5,
        'potable' => 1, 'aime' => 2, 'adore' => 3, 'utile' => 1.5, 'soulagement' => 2,

        // Négatif
        'nul' => -3, 'horrible' => -3, 'catastrophique' => -3, 'inacceptable' => -3,
        'deplorable' => -3, 'honteux' => -3, 'mauvais' => -2, 'mauvaise' => -2,
        'sale' => -2.5, 'sales' => -2.5, 'salete' => -2.5, 'dechets' => -2, 'odeur' => -1.5,
        'pue' => -2.5, 'chaud' => -1.5, 'chaude' => -1.5, 'etouffant' => -2.5, 'suffocant' => -2.5,
        'panne' => -2, 'casse' => -2, 'cassee' => -2, 'ferme' => -1.5, 'fermee' => -1.5,
        'bonde' => -2, 'surpeuple' => -2, 'foule' => -1.5, 'attente' => -1, 'bruyant' => -1.5,
        'dangereux' => -2.5, 'insecurite' => -2.5, 'agressif' => -2.5, 'impoli' => -2,
        'desagreable' => -2, 'decevant' => -2, 'decu' => -2, 'decue' => -2, 'inutile' => -2,
        'insuffisant' => -1.5, 'introuvable' => -1.5, 'abandonne' => -2, 'degrade' => -2,
        'inaccessible' => -2, 'vide' => -1, 'probleme' => -1.5, 'problemes' => -1.5,
        'eviter' => -2, 'arnaque' => -3, 'jamais' => -0.5,
    ];

    /** Mots qui inversent la polarité du mot suivant (fenêtre de 3 mots). */
    private const NEGATIONS = ['pas', 'plus', 'jamais', 'aucun', 'aucune', 'ni', 'sans', 'rien'];

    /** Intensifieurs : multiplient la polarité du mot suivant. */
    private const INTENSIFIEURS = [
        'tres' => 1.5, 'vraiment' => 1.5, 'trop' => 1.4, 'extremement' => 2,
        'super' => 1.5, 'tellement' => 1.5, 'assez' => 0.8, 'peu' => 0.5,
    ];

    /**
     * Thèmes métier (problèmes récurrents) => mots déclencheurs.
     *
     * @var array<string, list<string>>
     */
    public const THEMES = [
        'Propreté' => ['sale', 'sales', 'salete', 'dechets', 'propre', 'propres', 'odeur', 'pue', 'poubelle', 'toilettes'],
        'Climatisation / chaleur' => ['clim', 'climatisation', 'climatise', 'climatisee', 'chaud', 'chaude', 'chaleur', 'etouffant', 'suffocant', 'frais', 'fraiche'],
        'Affluence' => ['bonde', 'monde', 'foule', 'attente', 'surpeuple', 'plein', 'queue'],
        'Accueil' => ['accueil', 'personnel', 'agent', 'gardien', 'impoli', 'agressif', 'accueillant', 'gentil'],
        'Horaires' => ['ferme', 'fermee', 'horaires', 'horaire', 'ouverture', 'ouvert'],
        'Eau' => ['eau', 'fontaine', 'robinet', 'potable', 'boire'],
        'Sécurité' => ['dangereux', 'insecurite', 'securite', 'securise', 'vol', 'agression'],
        'Ombre' => ['ombre', 'ombrage', 'ombragee', 'soleil', 'arbres'],
        'Accessibilité' => ['pmr', 'fauteuil', 'rampe', 'escalier', 'escaliers', 'accessible', 'inaccessible'],
        'Équipements' => ['panne', 'casse', 'cassee', 'banc', 'bancs', 'degrade', 'abandonne'],
    ];

    /**
     * Analyse par le LLM (Groq), avec repli sur l'analyse lexicale.
     * Utilisée à l'enregistrement d'un avis par un habitant.
     *
     * @return array{score: float, sentiment: Sentiment, themes: list<string>, source: string}
     */
    public function analyserAvecIa(?string $commentaire, int $note): array
    {
        $secours = $this->analyser($commentaire, $note);

        if ($commentaire === null || trim($commentaire) === '') {
            return $secours;
        }

        $themesAutorises = array_keys(self::THEMES);

        $json = $this->groq->completerJson(
            "Tu analyses le sentiment d'avis d'habitants sur des points de fraîcheur (parcs, salles climatisées, fontaines) "
            .'pendant les canicules en Tunisie. Réponds UNIQUEMENT par un objet JSON : '
            .'{"sentiment":"positif|neutre|negatif","score":nombre entre -1 et 1,"themes":[...]}. '
            .'Les thèmes possibles sont : '.implode(', ', $themesAutorises).'. '
            .'Tiens compte de la note chiffrée, des négations et de l\'ironie.',
            "Note : {$note}/5\nCommentaire : ".mb_substr($commentaire, 0, 1000),
            400,
        );

        $sentiment = Sentiment::tryFrom((string) ($json['sentiment'] ?? ''));

        if ($sentiment === null || ! is_numeric($json['score'] ?? null)) {
            return $secours;
        }

        $themes = array_values(array_intersect($themesAutorises, (array) ($json['themes'] ?? [])));

        return [
            'score' => round(max(-1.0, min(1.0, (float) $json['score'])), 3),
            'sentiment' => $sentiment,
            'themes' => $themes !== [] ? $themes : $secours['themes'],
            'source' => 'ia',
        ];
    }

    /**
     * Synthèse rédigée (2–3 phrases) des problèmes récurrents d'un point mal
     * noté, pour aider l'admin à agir. Mise en cache 6 h.
     *
     * @param  array{point: PointFraicheur, nb_avis: int, nb_negatifs: int, moyenne: float, themes: array<string, int>, message: string}  $diagnostic
     */
    public function synthese(array $diagnostic): string
    {
        $point = $diagnostic['point'];
        $commentaires = $point->avis
            ->filter(fn (Avis $a): bool => $a->sentiment === Sentiment::Negatif && $a->commentaire)
            ->take(10)
            ->map(fn (Avis $a): string => "- ({$a->note}/5) ".mb_substr((string) $a->commentaire, 0, 300))
            ->implode("\n");

        $cle = 'fraicheur:synthese:'.$point->id.':'.md5($commentaires);

        return cache()->remember($cle, now()->addHours(6), function () use ($diagnostic, $point, $commentaires): string {
            $texte = $this->groq->completer(
                'Tu es un assistant de modération pour une application municipale de lutte contre la canicule. '
                .'En 2 ou 3 phrases en français, sans liste ni markdown, résume les problèmes récurrents signalés '
                .'par les habitants sur ce point de fraîcheur et propose une action concrète à l\'administrateur.',
                "Point : {$point->nom} ({$point->type?->label()})\n{$diagnostic['message']}\nAvis négatifs :\n{$commentaires}",
                500,
            );

            return $texte ?? $this->syntheseDeSecours($diagnostic);
        });
    }

    /**
     * @param  array{themes: array<string, int>, message: string}  $diagnostic
     */
    private function syntheseDeSecours(array $diagnostic): string
    {
        $phrase = 'Les habitants signalent régulièrement des difficultés : '.$diagnostic['message'].'.';

        if ($diagnostic['themes'] !== []) {
            $phrase .= ' Une vérification sur place est conseillée (priorité : '.array_key_first($diagnostic['themes']).').';
        }

        return $phrase;
    }

    /**
     * Analyse lexicale (déterministe, sans réseau) : score [-1, 1], polarité,
     * thèmes. Repli de l'IA, et utilisée par les seeders / factories.
     *
     * @return array{score: float, sentiment: Sentiment, score_texte: float|null, themes: list<string>, source: string}
     */
    public function analyser(?string $commentaire, int $note): array
    {
        // La note 1..5 est ramenée sur [-1, 1] : 3 = neutre.
        $scoreNote = max(-1.0, min(1.0, ($note - 3) / 2));

        $mots = $this->tokeniser($commentaire);
        $scoreTexte = $mots === [] ? null : $this->scoreTexte($mots);

        $score = $scoreTexte === null
            ? $scoreNote
            : (1 - self::POIDS_NOTE) * $scoreTexte + self::POIDS_NOTE * $scoreNote;

        $score = round(max(-1.0, min(1.0, $score)), 3);

        return [
            'score' => $score,
            'sentiment' => Sentiment::fromScore($score),
            'score_texte' => $scoreTexte !== null ? round($scoreTexte, 3) : null,
            'themes' => $this->themes($mots),
            'source' => 'lexique',
        ];
    }

    /**
     * Points validés mal notés de manière récurrente sur la période,
     * triés du plus problématique au moins problématique.
     *
     * @return Collection<int, array{
     *     point: PointFraicheur,
     *     nb_avis: int,
     *     nb_negatifs: int,
     *     ratio_negatifs: float,
     *     moyenne: float,
     *     themes: array<string, int>,
     *     gravite: int,
     *     message: string
     * }>
     */
    public function pointsMalNotes(?int $quartierId = null): Collection
    {
        $depuis = Carbon::now()->subDays(self::FENETRE_JOURS);

        return PointFraicheur::query()
            ->where('statut', StatutPointFraicheur::Valide->value)
            ->when($quartierId, fn ($q) => $q->where('quartier_id', $quartierId))
            ->with(['avis' => fn ($q) => $q->where('created_at', '>=', $depuis)])
            ->get()
            ->map(function (PointFraicheur $point): ?array {
                $avis = $point->avis;
                $nb = $avis->count();

                if ($nb < self::MIN_AVIS) {
                    return null;
                }

                $negatifs = $avis->filter(fn (Avis $a): bool => $a->sentiment === Sentiment::Negatif);
                $ratio = $negatifs->count() / $nb;
                $moyenne = round((float) $avis->avg('note'), 1);

                if ($ratio < self::SEUIL_NEGATIFS && $moyenne > self::SEUIL_NOTE) {
                    return null;
                }

                // Thèmes qui reviennent dans les avis négatifs (au moins 2 fois).
                $themes = $negatifs
                    ->flatMap(fn (Avis $a): array => $this->analyser($a->commentaire, $a->note)['themes'])
                    ->countBy()
                    ->filter(fn (int $n): bool => $n >= 2)
                    ->sortDesc()
                    ->all();

                // Gravité 0–100 : part de négatifs + écart à la note idéale.
                $gravite = (int) round(min(100, $ratio * 60 + (5 - $moyenne) / 4 * 40));

                $message = sprintf(
                    '%d avis négatif(s) sur %d (%d %%) · note moyenne %s/5',
                    $negatifs->count(),
                    $nb,
                    (int) round($ratio * 100),
                    number_format($moyenne, 1, ',', ''),
                );

                if ($themes !== []) {
                    $message .= ' · problèmes récurrents : '.implode(', ', array_keys($themes));
                }

                return [
                    'point' => $point,
                    'nb_avis' => $nb,
                    'nb_negatifs' => $negatifs->count(),
                    'ratio_negatifs' => round($ratio, 2),
                    'moyenne' => $moyenne,
                    'themes' => $themes,
                    'gravite' => $gravite,
                    'message' => $message,
                ];
            })
            ->filter()
            ->sortByDesc('gravite')
            ->values();
    }

    /**
     * Répartition des sentiments (pour les compteurs du back-office).
     *
     * @return array<string, int>
     */
    public function repartition(): array
    {
        $compte = Avis::query()
            ->selectRaw('sentiment, count(*) as total')
            ->groupBy('sentiment')
            ->pluck('total', 'sentiment');

        return collect(Sentiment::cases())
            ->mapWithKeys(fn (Sentiment $s): array => [$s->value => (int) ($compte[$s->value] ?? 0)])
            ->all();
    }

    /**
     * @param  list<string>  $mots
     */
    private function scoreTexte(array $mots): float
    {
        $total = 0.0;
        $porteurs = 0;

        foreach ($mots as $i => $mot) {
            if (! isset(self::LEXIQUE[$mot])) {
                continue;
            }

            $valeur = self::LEXIQUE[$mot];

            // Intensifieur juste avant (« très sale »).
            $precedent = $mots[$i - 1] ?? null;
            if ($precedent !== null && isset(self::INTENSIFIEURS[$precedent]) && $precedent !== $mot) {
                $valeur *= self::INTENSIFIEURS[$precedent];
            }

            // Négation dans les 3 mots précédents (« n'est pas propre »).
            for ($j = max(0, $i - 3); $j < $i; $j++) {
                if (in_array($mots[$j], self::NEGATIONS, true)) {
                    $valeur *= -0.8;
                    break;
                }
            }

            $total += $valeur;
            $porteurs++;
        }

        if ($porteurs === 0) {
            return 0.0;
        }

        // Normalisation douce : tanh borne dans [-1, 1] sans saturer trop vite.
        return tanh($total / (2 + sqrt($porteurs)));
    }

    /**
     * @param  list<string>  $mots
     * @return list<string>
     */
    private function themes(array $mots): array
    {
        $trouves = [];

        foreach (self::THEMES as $theme => $declencheurs) {
            if (array_intersect($mots, $declencheurs) !== []) {
                $trouves[] = $theme;
            }
        }

        return $trouves;
    }

    /**
     * Minuscules, sans accents, apostrophes séparées (« n'est » → « n est »).
     *
     * @return list<string>
     */
    private function tokeniser(?string $texte): array
    {
        if ($texte === null || trim($texte) === '') {
            return [];
        }

        $texte = Str::lower(Str::ascii($texte));
        $texte = preg_replace('/[^a-z0-9]+/', ' ', $texte) ?? '';

        return array_values(array_filter(explode(' ', $texte), fn (string $m): bool => $m !== ''));
    }
}
