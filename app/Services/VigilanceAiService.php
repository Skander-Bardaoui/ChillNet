<?php

namespace App\Services;

use App\Enums\NiveauAlerte;
use App\Enums\ProfilVulnerabilite;
use App\Models\Alerte;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Module IA de la vigilance canicule — approche HYBRIDE :
 *  - la CLASSIFICATION du niveau (jaune/orange/rouge) est déterministe
 *    (règles de seuils sur température / humidité / historique) : fiable
 *    et explicable ;
 *  - la RÉDACTION du message personnalisé est confiée à un LLM (Groq),
 *    avec un message de secours déterministe si l'API est indisponible.
 */
class VigilanceAiService
{
    /**
     * Seuil de température par défaut (config services.weather.seuil_defaut).
     */
    public function seuilDefaut(): float
    {
        return (float) config('services.weather.seuil_defaut', 35);
    }

    /**
     * Classification du risque à partir des données météo et de l'historique.
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
     * Analyse complète + trace lisible de la règle appliquée.
     *
     * @param  array{jours_surchauffe?: int, temp_max?: float|null}  $historique
     * @return array{niveau: NiveauAlerte, raison: string, seuil: float}
     */
    public function analyse(float $temperature, ?int $humidite = null, array $historique = [], ?float $seuil = null): array
    {
        $seuil ??= $this->seuilDefaut();
        $niveau = $this->niveauPour($temperature, $humidite, $historique, $seuil);
        $joursSurchauffe = (int) ($historique['jours_surchauffe'] ?? 0);

        $raison = sprintf(
            'Température %s°C%s%s → %s (seuil %.1f°C, seuils +3/+6).',
            number_format($temperature, 1, ',', ''),
            $humidite !== null ? sprintf(', humidité %d%%', $humidite) : '',
            $joursSurchauffe >= 3 ? sprintf(', %d jours de surchauffe', $joursSurchauffe) : '',
            mb_strtolower($niveau->label()),
            $seuil,
        );

        return ['niveau' => $niveau, 'raison' => $raison, 'seuil' => $seuil];
    }

    /**
     * Historique récent pour un quartier (input "historique" de niveauPour).
     * 100 % base de données, aucun appel externe.
     *
     * @return array{jours_surchauffe: int, temp_max: float|null}
     */
    public function historiquePourQuartier(int $quartierId, int $jours = 3): array
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

        return $this->messagePourContexte($this->contextePourAlerte($alerte), $profils);
    }

    /**
     * Contexte factuel transmis au LLM (et au message de secours).
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
     * Rédaction du message (Groq). Retombe sur le message de secours si la
     * clé est absente, si l'appel échoue ou si la réponse est inexploitable.
     *
     * @param  array<string, mixed>  $contexte
     * @param  array<int, ProfilVulnerabilite|string>  $profils
     */
    public function messagePourContexte(array $contexte, array $profils = []): string
    {
        $key = config('services.groq.key');

        if (empty($key)) {
            return $this->messageDeSecours($contexte, $profils);
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
                    // Les modèles gpt-oss consomment des jetons de raisonnement
                    // qui comptent dans `max_tokens` : on limite le raisonnement
                    // et on garde de la marge pour le message visible.
                    'reasoning_effort' => 'low',
                    'max_tokens' => 700,
                ]);
        } catch (\Throwable $e) {
            Log::warning('Groq : appel impossible', ['message' => $e->getMessage()]);

            return $this->messageDeSecours($contexte, $profils);
        }

        $texte = data_get($response->json(), 'choices.0.message.content');

        if ($response->failed() || ! is_string($texte) || trim($texte) === '') {
            Log::warning('Groq : réponse inexploitable', ['status' => $response->status()]);

            return $this->messageDeSecours($contexte, $profils);
        }

        return trim($texte);
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
