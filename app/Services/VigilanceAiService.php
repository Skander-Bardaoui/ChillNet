<?php

namespace App\Services;

use App\Enums\NiveauAlerte;
use App\Enums\ProfilVulnerabilite;
use App\Models\Alerte;
use App\Models\User;
use App\Services\Ai\AiServiceClient;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Orchestrateur de la vigilance canicule.
 *
 * TOUTE la logique IA (classification ML, règles, rédaction du message) vit
 * dans le microservice Python (`ai-service/`). Cette classe se limite à :
 *
 *  - construire le CONTEXTE BRUT transmis au service (aucun feature engineering
 *    côté PHP : températures, humidité, prévisions, historique) ;
 *  - exposer les règles déterministes PHP (`niveauPour`), qui servent à la fois
 *    de PLANCHER DE SÉCURITÉ (le niveau final ne peut jamais être inférieur) et
 *    de REPLI INTÉGRAL quand le service est indisponible ;
 *  - rédiger un message de secours minimal (`messageDeSecours`).
 *
 * Cette classe ne lève jamais d'exception sur le chemin d'inférence : toute
 * défaillance retombe sur les règles seules.
 */
class VigilanceAiService
{
    public function __construct(private AiServiceClient $client) {}

    /**
     * Seuil de température par défaut (config services.weather.seuil_defaut).
     */
    public function seuilDefaut(): float
    {
        return (float) config('services.weather.seuil_defaut', 35);
    }

    /**
     * Règles déterministes (PLANCHER DE SÉCURITÉ et REPLI).
     *
     * Température effective = température + pénalité humidité + pénalité
     * canicule installée, puis seuils à +3°C et +6°C au-dessus du seuil,
     * et une escalade d'un cran si l'humidité est très élevée (>= 70 %)
     * ou la vague de chaleur durable (>= 3 jours).
     *
     * @param  array{jours_surchauffe?: int, temp_max?: float|null}  $historique
     */
    public function niveauPour(float $temperature, ?int $humidite = null, array $historique = [], ?float $seuil = null): NiveauAlerte
    {
        $seuil ??= $this->seuilDefaut();
        $joursSurchauffe = (int) ($historique['jours_surchauffe'] ?? 0);

        $effective = $temperature;

        // Air humide : au-delà de 60 %, le ressenti se dégrade (jusqu'à +3°C).
        if ($humidite !== null && $humidite >= 60) {
            $effective += min(3.0, ($humidite - 60) * 0.1);
        }

        // Vague de chaleur installée : +2°C d'aggravation.
        if ($joursSurchauffe >= 3) {
            $effective += 2.0;
        }

        $niveau = match (true) {
            $effective >= $seuil + 6 => NiveauAlerte::Rouge,
            $effective >= $seuil + 3 => NiveauAlerte::Orange,
            default => NiveauAlerte::Jaune,
        };

        // Escalade d'un cran dans les cas aggravants, plafonnée à rouge.
        if (($humidite !== null && $humidite >= 70) || $joursSurchauffe >= 3) {
            $niveau = match ($niveau) {
                NiveauAlerte::Jaune => NiveauAlerte::Orange,
                NiveauAlerte::Orange, NiveauAlerte::Rouge => NiveauAlerte::Rouge,
            };
        }

        return $niveau;
    }

    /**
     * Prédiction complète (niveau final, explicabilité) via le service Python,
     * repliée sur les règles PHP si le service est indisponible.
     *
     * @param  array<string, mixed>  $contexte  temperature, ressentie, humidite,
     *         seuil, previsions, historique, lieu_type, quartier, profils, etc.
     * @return array{
     *     niveau: NiveauAlerte,
     *     niveau_final: string,
     *     niveau_modele: ?NiveauAlerte,
     *     niveau_regles: ?NiveauAlerte,
     *     probabilites: array<string, float>,
     *     confiance: float,
     *     facteurs: array<int, array<string, mixed>>,
     *     desaccord: bool,
     *     raison: string,
     *     model_version: ?string,
     *     source: string,
     *     contexte: array<string, mixed>,
     *     modele_disponible: bool
     * }
     */
    public function predict(array $contexte): array
    {
        $seuil = is_numeric($contexte['seuil'] ?? null) ? (float) $contexte['seuil'] : $this->seuilDefaut();
        $temperature = is_numeric($contexte['temperature'] ?? null) ? (float) $contexte['temperature'] : null;
        $humidite = is_numeric($contexte['humidite'] ?? null) ? (int) $contexte['humidite'] : null;

        $niveauRegles = $this->niveauPour(
            $temperature ?? (float) config('ai.defaults.temperature', 25.0),
            $humidite,
            is_array($contexte['historique'] ?? null) ? $contexte['historique'] : [],
            $seuil,
        );

        $reponse = $this->client->analyze($this->contextePourAnalyse($contexte, $seuil));

        if ($reponse !== null) {
            return $this->depuisReponse($reponse, $contexte, $niveauRegles);
        }

        return $this->repliRegles($contexte, $niveauRegles, $seuil, $temperature, $humidite);
    }

    /**
     * Analyse + trace lisible, enrichie des sorties du service (probabilités,
     * confiance, facteurs, désaccord). Repli sur les règles si indisponible.
     *
     * @param  array{jours_surchauffe?: int, temp_max?: float|null}  $historique
     * @return array<string, mixed>
     */
    public function analyse(float $temperature, ?int $humidite = null, array $historique = [], ?float $seuil = null): array
    {
        $seuil ??= $this->seuilDefaut();

        $prediction = $this->predict([
            'temperature' => $temperature,
            'humidite' => $humidite,
            'historique' => $historique,
            'seuil' => $seuil,
        ]);

        return [
            'niveau' => $prediction['niveau'],
            'raison' => $prediction['raison'],
            'seuil' => $seuil,
            'niveau_modele' => $prediction['niveau_modele'],
            'niveau_regles' => $prediction['niveau_regles'],
            'probabilites' => $prediction['probabilites'],
            'confiance' => $prediction['confiance'],
            'facteurs' => $prediction['facteurs'],
            'desaccord' => $prediction['desaccord'],
            'model_version' => $prediction['model_version'],
            'source' => $prediction['source'],
            'modele_disponible' => $prediction['modele_disponible'],
        ];
    }

    /**
     * Historique récent pour un ou plusieurs quartiers (entrée "historique").
     * 100 % base de données, aucun appel externe.
     *
     * @param  int|array<int>  $quartierId
     * @return array{jours_surchauffe: int, temp_max: float|null}
     */
    public function historiquePourQuartier(int|array $quartierId, int $jours = 3): array
    {
        $depuis = Carbon::now()->subDays($jours);

        $alertes = Alerte::query()
            ->parQuartier($quartierId)
            ->where('created_at', '>=', $depuis)
            ->get(['temperature_actuelle', 'seuil_temperature', 'debut']);

        $joursSurchauffe = $alertes
            ->filter(fn (Alerte $alerte): bool => $alerte->temperature_actuelle !== null
                && $alerte->temperature_actuelle >= $alerte->seuil_temperature)
            ->map(fn (Alerte $alerte): ?string => $alerte->debut?->format('Y-m-d'))
            ->filter()
            ->unique()
            ->count();

        return [
            'jours_surchauffe' => $joursSurchauffe,
            'temp_max' => $alertes->max('temperature_actuelle'),
        ];
    }

    /**
     * Message personnalisé pour une alerte et le profil d'un foyer.
     */
    public function messagePersonnalise(Alerte $alerte, ?User $habitant = null): string
    {
        $profils = $habitant?->profilsVulnerabilite() ?? [];

        // Graine stable = identifiant d'alerte + profil → message en cache constant.
        $cleProfil = implode('-', array_map(
            fn (ProfilVulnerabilite $p): string => $p->value,
            $profils,
        )) ?: 'standard';

        return $this->messagePourContexte(
            $this->contextePourAlerte($alerte),
            $profils,
            "alerte:{$alerte->id}:{$cleProfil}",
        );
    }

    /**
     * Contexte factuel transmis au service de rédaction.
     *
     * @return array<string, mixed>
     */
    public function contextePourAlerte(Alerte $alerte): array
    {
        return [
            'titre' => $alerte->titre,
            'niveau' => $alerte->niveau?->value,
            'niveau_label' => $alerte->niveau?->label(),
            'quartiers' => $alerte->quartiers->map(fn ($quartier): string => $quartier->nom)->all(),
            'debut' => $alerte->debut?->format('d/m/Y H:i'),
            'fin' => $alerte->fin?->format('d/m/Y H:i'),
            'seuil_temperature' => $alerte->seuil_temperature,
            'temperature_actuelle' => $alerte->temperature_actuelle,
            'temperature_ressentie' => $alerte->temperature_ressentie,
            'humidite' => $alerte->humidite,
        ];
    }

    /**
     * Rédaction du message : service Python par défaut, Groq en option explicite.
     * Ne lève jamais : replie sur le message de secours déterministe.
     *
     * @param  array<string, mixed>  $contexte
     * @param  array<int, ProfilVulnerabilite|string>  $profils
     */
    public function messagePourContexte(array $contexte, array $profils = [], int|string|null $seed = null): string
    {
        if ($this->utiliserGroq()) {
            return $this->messageGroq($contexte, $profils)
                ?? $this->messageDeSecours($contexte, $profils);
        }

        $message = $this->messageViaService($contexte, $profils, $seed);

        return $message ?? $this->messageDeSecours($contexte, $profils);
    }

    /**
     * Message déterministe (français) — secours, seeder et tests.
     *
     * @param  array<string, mixed>  $contexte
     * @param  array<int, ProfilVulnerabilite|string>  $profils
     */
    public function messageDeSecours(array $contexte, array $profils = []): string
    {
        $niveau = $contexte['niveau_label'] ?? 'Vigilance canicule';

        $entete = $niveau;
        if (! empty($contexte['quartiers'])) {
            $entete .= ' sur '.implode(', ', $contexte['quartiers']);
        }
        if (isset($contexte['temperature_actuelle']) && $contexte['temperature_actuelle'] !== null) {
            $entete .= ' — température mesurée '.number_format((float) $contexte['temperature_actuelle'], 1, ',', '').'°C';
        }
        $entete .= '.';

        return trim(sprintf(
            '%s %s En cas de malaise, appelez le 190 ou le 198 ; plateforme canicule : 0800 06 66 66.',
            $entete,
            $this->conseilsParProfil($profils),
        ));
    }

    /**
     * Construit le CONTEXTE BRUT envoyé au service (aucun feature engineering).
     *
     * @param  array<string, mixed>  $contexte
     * @return array<string, mixed>
     */
    private function contextePourAnalyse(array $contexte, float $seuil): array
    {
        $historique = is_array($contexte['historique'] ?? null) ? $contexte['historique'] : [];

        return [
            'temperature' => is_numeric($contexte['temperature'] ?? null) ? (float) $contexte['temperature'] : null,
            'ressentie' => is_numeric($contexte['ressentie'] ?? null) ? (float) $contexte['ressentie'] : null,
            'humidite' => is_numeric($contexte['humidite'] ?? null) ? (int) $contexte['humidite'] : null,
            'seuil_temperature' => $seuil,
            'historique' => [
                'jours_surchauffe' => (int) ($historique['jours_surchauffe'] ?? 0),
                'temp_max' => is_numeric($historique['temp_max'] ?? null) ? (float) $historique['temp_max'] : null,
            ],
            'mois' => (int) ($contexte['mois'] ?? Carbon::now()->month),
            'previsions' => $this->previsionsBrutes($contexte['previsions'] ?? []),
            'lieu_type' => $contexte['lieu_type'] ?? null,
            'quartier' => $contexte['quartier'] ?? null,
            'profils' => $this->profilsBruts($contexte['profils'] ?? []),
            'alerte_id' => $contexte['alerte_id'] ?? null,
            'avec_message' => false,
        ];
    }

    /**
     * Convertit les prévisions horaires (objets MeteoHoraire) en couples
     * bruts {heure, temperature} attendus par le service.
     *
     * @return array<int, array{heure: string, temperature: float}>
     */
    private function previsionsBrutes(mixed $previsions): array
    {
        if (! is_iterable($previsions)) {
            return [];
        }

        $brutes = [];

        foreach ($previsions as $prevision) {
            $heure = is_object($prevision) ? ($prevision->heure ?? null) : ($prevision['heure'] ?? null);
            $temperature = is_object($prevision) ? ($prevision->temperature ?? null) : ($prevision['temperature'] ?? null);

            if ($heure === null || ! is_numeric($temperature)) {
                continue;
            }

            $brutes[] = [
                'heure' => $heure instanceof \DateTimeInterface ? $heure->format('H:i') : (string) $heure,
                'temperature' => (float) $temperature,
            ];
        }

        return array_slice($brutes, 0, 72);
    }

    /**
     * @param  array<int, ProfilVulnerabilite|string>  $profils
     * @return array<int, string>
     */
    private function profilsBruts(array $profils): array
    {
        return array_values(array_map(
            fn ($profil): string => $profil instanceof ProfilVulnerabilite ? $profil->value : (string) $profil,
            $profils,
        ));
    }

    /**
     * Mappe la réponse du service Python vers la structure interne.
     *
     * @param  array<string, mixed>  $reponse
     * @param  array<string, mixed>  $contexte
     * @return array<string, mixed>
     */
    private function depuisReponse(array $reponse, array $contexte, NiveauAlerte $niveauRegles): array
    {
        $niveauModele = $this->niveauDepuis($reponse['niveau_modele'] ?? null);
        $niveauReglesApi = $this->niveauDepuis($reponse['niveau_regles'] ?? null) ?? $niveauRegles;

        // Plancher = le plus grave entre les règles locales (référence Laravel) et
        // celles annoncées par le service (défense en profondeur : un service
        // défaillant ne doit jamais tirer le niveau sous les règles PHP).
        $plancher = $niveauReglesApi->gravite() >= $niveauRegles->gravite()
            ? $niveauReglesApi
            : $niveauRegles;

        $finalBrut = is_string($reponse['niveau_final'] ?? null)
            ? $reponse['niveau_final']
            : $plancher->value;

        // Plancher de sécurité : jamais sous les règles.
        if (($niveauFinal = $this->niveauDepuis($finalBrut)) === null
            || $niveauFinal->gravite() < $plancher->gravite()) {
            $finalBrut = $plancher->value;
        }

        return [
            'niveau' => $this->niveauDepuis($finalBrut) ?? NiveauAlerte::Jaune,
            'niveau_final' => $finalBrut,
            'niveau_modele' => $niveauModele,
            'niveau_regles' => $plancher,
            'probabilites' => is_array($reponse['probabilites'] ?? null) ? $reponse['probabilites'] : [],
            'confiance' => is_numeric($reponse['confiance'] ?? null) ? (float) $reponse['confiance'] : 0.0,
            'facteurs' => is_array($reponse['facteurs'] ?? null) ? $reponse['facteurs'] : [],
            'desaccord' => (bool) ($reponse['desaccord'] ?? false),
            'raison' => is_string($reponse['raison'] ?? null) ? $reponse['raison'] : '',
            'model_version' => $reponse['model_version'] ?? null,
            'source' => is_string($reponse['source'] ?? null) ? $reponse['source'] : 'modele',
            'contexte' => $contexte,
            'modele_disponible' => (bool) ($reponse['modele_disponible'] ?? true),
        ];
    }

    /**
     * Repli intégral sur les règles déterministes PHP (service indisponible).
     *
     * @param  array<string, mixed>  $contexte
     * @return array<string, mixed>
     */
    private function repliRegles(array $contexte, NiveauAlerte $niveauRegles, float $seuil, ?float $temperature, ?int $humidite): array
    {
        return [
            'niveau' => $niveauRegles,
            'niveau_final' => $niveauRegles->value,
            'niveau_modele' => null,
            'niveau_regles' => $niveauRegles,
            'probabilites' => [],
            'confiance' => 0.0,
            'facteurs' => [],
            'desaccord' => false,
            'raison' => $this->raisonRegles($temperature, $humidite, $niveauRegles, $seuil, is_array($contexte['historique'] ?? null) ? $contexte['historique'] : []),
            'model_version' => null,
            'source' => 'regles',
            'contexte' => $contexte,
            'modele_disponible' => false,
        ];
    }

    /**
     * Phrase d'explication des règles seules (repli).
     *
     * @param  array<string, mixed>  $historique
     */
    private function raisonRegles(?float $temperature, ?int $humidite, NiveauAlerte $niveau, float $seuil, array $historique): string
    {
        $joursSurchauffe = (int) ($historique['jours_surchauffe'] ?? 0);

        return sprintf(
            'Règles seules : température %s°C%s%s → %s (seuil %.1f°C, seuils +3/+6). Service IA indisponible — classification déterministe appliquée.',
            number_format($temperature ?? 0.0, 1, ',', ''),
            $humidite !== null ? sprintf(', humidité %d%%', $humidite) : '',
            $joursSurchauffe >= 3 ? sprintf(', %d jours de surchauffe', $joursSurchauffe) : '',
            mb_strtolower($niveau->label()),
            $seuil,
        );
    }

    /**
     * Convertit une classe brute du service en enum (null si « normal »).
     */
    private function niveauDepuis(mixed $classe): ?NiveauAlerte
    {
        if (! is_string($classe)) {
            return null;
        }

        return match (mb_strtolower(trim($classe))) {
            'jaune' => NiveauAlerte::Jaune,
            'orange' => NiveauAlerte::Orange,
            'rouge' => NiveauAlerte::Rouge,
            default => null,
        };
    }

    /**
     * Rédaction via le service Python.
     *
     * @param  array<string, mixed>  $contexte
     * @param  array<int, ProfilVulnerabilite|string>  $profils
     */
    private function messageViaService(array $contexte, array $profils, int|string|null $seed): ?string
    {
        $payload = [
            'niveau' => $contexte['niveau'] ?? null,
            'niveau_label' => $contexte['niveau_label'] ?? null,
            'quartiers' => array_values((array) ($contexte['quartiers'] ?? [])),
            'seuil_temperature' => $contexte['seuil_temperature'] ?? null,
            'temperature_actuelle' => $contexte['temperature_actuelle'] ?? null,
            'temperature_ressentie' => $contexte['temperature_ressentie'] ?? null,
            'humidite' => $contexte['humidite'] ?? null,
            'heure_pic' => $contexte['heure_pic'] ?? null,
            'lieu_type' => $contexte['lieu_type'] ?? null,
            'raison' => $contexte['raison'] ?? null,
        ];

        return $this->client->message($payload, $this->profilsBruts($profils), $seed);
    }

    /**
     * Le driver effectif doit-il utiliser Groq ?
     *
     * - ai.driver = 'groq'  → toujours.
     * - ai.driver = 'local' → uniquement si une clé est configurée ET que
     *   ai.use_groq_when_key_present est vrai.
     */
    private function utiliserGroq(): bool
    {
        $driver = (string) config('ai.driver', 'local');

        if ($driver === 'groq') {
            return true;
        }

        $clePresente = ! empty(config('services.groq.key'));

        if ($driver === 'local') {
            return $clePresente && (bool) config('ai.use_groq_when_key_present', true);
        }

        return $clePresente;
    }

    /**
     * Rédaction via le LLM Groq (option explicite). Renvoie null si indisponible.
     *
     * @param  array<string, mixed>  $contexte
     * @param  array<int, ProfilVulnerabilite|string>  $profils
     */
    private function messageGroq(array $contexte, array $profils): ?string
    {
        $key = config('services.groq.key');

        if (empty($key)) {
            return null;
        }

        try {
            $response = Http::withToken($key)
                ->timeout(20)
                ->acceptJson()
                ->post(rtrim((string) config('services.groq.base_url', 'https://api.groq.com/openai/v1'), '/').'/chat/completions', [
                    'model' => config('services.groq.model', 'openai/gpt-oss-120b'),
                    'messages' => [
                        ['role' => 'system', 'content' => $this->promptSysteme()],
                        ['role' => 'user', 'content' => $this->promptUtilisateur($contexte, $profils)],
                    ],
                    'temperature' => 0.4,
                    'reasoning_effort' => 'low',
                    'max_tokens' => 700,
                ]);
        } catch (\Throwable $e) {
            Log::warning('Groq : appel impossible', ['message' => $e->getMessage()]);

            return null;
        }

        $texte = data_get($response->json(), 'choices.0.message.content');

        if ($response->failed() || ! is_string($texte) || trim($texte) === '') {
            Log::warning('Groq : réponse inexploitable', ['status' => $response->status()]);

            return null;
        }

        return trim($texte);
    }

    /**
     * @param  array<int, ProfilVulnerabilite|string>  $profils
     */
    private function conseilsParProfil(array $profils): string
    {
        $catalogue = [
            ProfilVulnerabilite::PersonneAgee->value => 'Personne âgée : restez dans la pièce la plus fraîche, buvez régulièrement sans attendre la soif et évitez toute sortie entre 11h et 17h.',
            ProfilVulnerabilite::Enfant->value => 'Enfant en bas âge : ne le laissez jamais seul dans un véhicule, hydratez-le souvent et préférez les pièces ombragées.',
            ProfilVulnerabilite::EquipementMedical->value => 'Équipement médical : vérifiez l\'alimentation de secours et gardez les traitements au frais.',
        ];

        $parties = [];
        foreach ($profils as $profil) {
            $cle = $profil instanceof ProfilVulnerabilite ? $profil->value : (string) $profil;

            if (isset($catalogue[$cle])) {
                $parties[] = $catalogue[$cle];
            }
        }

        if ($parties === []) {
            return 'Hydratez-vous régulièrement, fermez volets et rideaux dès 10h00 et prenez des nouvelles de vos voisins isolés.';
        }

        return implode(' ', $parties);
    }

    private function promptSysteme(): string
    {
        return <<<'PROMPT'
        Tu es l'assistant canicule de ChillNet, un réseau de quartier solidaire.
        Rédige un message d'alerte en français, en tutoyant le lecteur, en 3 ou 4 phrases maximum.
        Rappelle les gestes essentiels adaptés au profil fourni (rester au frais, s'hydrater,
        éviter les sorties entre 11h et 17h, prendre des nouvelles des voisins isolés).
        Ne donne aucun diagnostic ni conseil médical.
        Termine par les numéros utiles : 190, 198, 0800 06 66 66.
        Réponds uniquement par le message, sans titre, sans liste et sans mise en forme.
        PROMPT;
    }

    /**
     * @param  array<string, mixed>  $contexte
     * @param  array<int, ProfilVulnerabilite|string>  $profils
     */
    private function promptUtilisateur(array $contexte, array $profils): string
    {
        $lignes = [
            'Titre : '.($contexte['titre'] ?? 'Alerte canicule'),
            'Niveau : '.($contexte['niveau_label'] ?? $contexte['niveau'] ?? 'inconnu'),
            'Quartiers : '.(! empty($contexte['quartiers']) ? implode(', ', $contexte['quartiers']) : '-'),
            'Début : '.($contexte['debut'] ?? '-'),
            'Fin : '.($contexte['fin'] ?? '-'),
        ];

        if (! empty($contexte['seuil_temperature'])) {
            $lignes[] = 'Seuil de température : '.$contexte['seuil_temperature'].'°C';
        }
        if (isset($contexte['temperature_actuelle']) && $contexte['temperature_actuelle'] !== null) {
            $lignes[] = 'Température actuelle : '.$contexte['temperature_actuelle'].'°C';
        }
        if (isset($contexte['humidite']) && $contexte['humidite'] !== null) {
            $lignes[] = 'Humidité : '.$contexte['humidite'].'%';
        }
        if (! empty($contexte['raison'])) {
            $lignes[] = 'Classification : '.$contexte['raison'];
        }

        $labels = array_map(
            fn ($profil): string => $profil instanceof ProfilVulnerabilite ? $profil->label() : (string) $profil,
            $profils,
        );
        $lignes[] = 'Profil du foyer : '.($labels !== [] ? implode(', ', $labels) : 'standard');

        return implode("\n", $lignes);
    }
}
