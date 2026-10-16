<?php

namespace App\Services;

use App\Enums\CriticiteEquipement;
use App\Models\Conseil;
use App\Models\Coupure;
use App\Models\Equipement;
use App\Models\User;
use App\Enums\StatutCoupure;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Service IA de conseils personnalisés — Module 4 (Ghazi).
 *
 * Approche hybride identique à VigilanceAiService :
 *  - sélection déterministe des conseils pertinents (base de données)
 *  - rédaction / priorisation dynamique via Groq LLM
 *  - repli sur un message de secours si l'API est indisponible
 *
 * Le contexte croisé : météo actuelle + coupures en cours dans la zone
 * + équipements sensibles déclarés → recommandations priorisées.
 */
class ConseilAiService
{
    public function __construct(
        private WeatherService $meteo,
    ) {}

    /**
     * Génère les recommandations personnalisées pour un habitant.
     * Point d'entrée principal appelé par le Front\ConseilController.
     *
     * @return array{conseils: Collection<int,Conseil>, message_ia: string, alerte_vitale: bool}
     */
    public function recommandationsPour(User $user, ?int $quartierId): array
    {
        $equipements = $user->equipements()->with('type')->parCriticite()->get();

        $typeIds = $equipements->pluck('equipement_type_id')->unique()->values()->all();

        // Conseils de la base pertinents pour ces équipements.
        $conseils = Conseil::with('equipementTypes')
            ->actifs()
            ->pourEquipements($typeIds)
            ->orderBy('ordre')
            ->get();

        // Contexte météo du foyer.
        $lieu = $user->lieuPrincipal();
        $meteo = null;
        if ($lieu?->hasCoordinates()) {
            $meteo = $this->meteo->actuelCache((float) $lieu->latitude, (float) $lieu->longitude);
        }

        // Coupures actives / prévues dans la zone interne.
        $coupures = $quartierId !== null
            ? Coupure::where('quartier_id', $quartierId)
                ->whereIn('statut', [StatutCoupure::EnCours->value, StatutCoupure::Prevue->value])
                ->get()
            : collect();

        $alerte_vitale = $this->aEquipementVital($equipements);

        $message = $this->genererMessage($equipements, $meteo, $coupures, $alerte_vitale);

        return [
            'conseils'      => $conseils,
            'message_ia'    => $message,
            'alerte_vitale' => $alerte_vitale,
        ];
    }

    /**
     * Génère le message d'assistant conversationnel.
     * Appelle Groq ; replie sur le message déterministe si indisponible.
     *
     * @param  Collection<int, Equipement>  $equipements
     * @param  Collection<int, Coupure>     $coupures
     */
    public function genererMessage(
        Collection $equipements,
        ?MeteoActuelle $meteo,
        Collection $coupures,
        bool $alerteVitale,
    ): string {
        $contexte = $this->construireContexte($equipements, $meteo, $coupures, $alerteVitale);
        $key = config('services.groq.key');

        if (empty($key)) {
            return $this->messageDeSecours($contexte);
        }

        try {
            $response = Http::withToken($key)
                ->timeout(20)
                ->acceptJson()
                ->post(
                    rtrim((string) config('services.groq.base_url', 'https://api.groq.com/openai/v1'), '/').'/chat/completions',
                    [
                        'model'            => config('services.groq.model', 'openai/gpt-oss-120b'),
                        'messages'         => [
                            ['role' => 'system', 'content' => $this->promptSysteme()],
                            ['role' => 'user',   'content' => $this->promptUtilisateur($contexte)],
                        ],
                        'temperature'      => 0.4,
                        'reasoning_effort' => 'low',
                        'max_tokens'       => 600,
                    ]
                );
        } catch (\Throwable $e) {
            Log::warning('ConseilAI : appel Groq impossible', ['message' => $e->getMessage()]);

            return $this->messageDeSecours($contexte);
        }

        $texte = data_get($response->json(), 'choices.0.message.content');

        if ($response->failed() || ! is_string($texte) || trim($texte) === '') {
            Log::warning('ConseilAI : réponse Groq inexploitable', ['status' => $response->status()]);

            return $this->messageDeSecours($contexte);
        }

        return trim($texte);
    }

    /**
     * Construit le tableau de contexte transmis au LLM.
     *
     * @param  Collection<int, Equipement>  $equipements
     * @param  Collection<int, Coupure>     $coupures
     * @return array<string, mixed>
     */
    public function construireContexte(
        Collection $equipements,
        ?MeteoActuelle $meteo,
        Collection $coupures,
        bool $alerteVitale,
    ): array {
        return [
            'equipements'    => $equipements->map(fn (Equipement $e): array => [
                'nom'             => $e->nom,
                'type'            => $e->type?->nom ?? 'Équipement',
                'medical'         => (bool) $e->type?->medical,
                'criticite'       => $e->criticite->label(),
                'contact_urgence' => $e->contact_urgence,
            ])->all(),
            'temperature'    => $meteo?->temperature,
            'ressentie'      => $meteo?->ressentie,
            'humidite'       => $meteo?->humidite,
            'condition'      => $meteo?->condition,
            'coupures'       => $coupures->map(fn (Coupure $c): array => [
                'statut' => $c->statut->label(),
                'debut'  => $c->debut?->format('H:i'),
                'fin'    => $c->fin?->format('H:i'),
                'lieu'   => $c->lieu,
            ])->all(),
            'alerte_vitale'  => $alerteVitale,
        ];
    }

    /**
     * Message déterministe de secours (pas de LLM).
     *
     * @param  array<string, mixed>  $contexte
     */
    public function messageDeSecours(array $contexte): string
    {
        $parties = [];

        $temperature = $contexte['temperature'] ?? null;
        if ($temperature !== null) {
            $parties[] = sprintf('Température actuelle : %.1f°C (ressenti %.1f°C).', $temperature, $contexte['ressentie'] ?? $temperature);
        }

        $coupures = $contexte['coupures'] ?? [];
        if (! empty($coupures)) {
            $desc = [];
            foreach ($coupures as $c) {
                $s = 'Coupure '.$c['statut'];
                if ($c['debut'] && $c['fin']) {
                    $s .= ' de '.$c['debut'].' à '.$c['fin'];
                }
                $desc[] = $s;
            }
            $parties[] = implode('. ', $desc).'.';
        }

        $vitaux = array_filter(
            $contexte['equipements'] ?? [],
            fn ($e): bool => $e['medical'] || $e['criticite'] === 'Vitale (médical)',
        );

        if (! empty($vitaux)) {
            $noms = implode(', ', array_column($vitaux, 'nom'));
            $parties[] = "Équipements vitaux détectés : {$noms}. Assurez-vous que la batterie de secours est chargée et contactez votre relais santé en cas de coupure.";
        }

        $parties[] = 'En urgence vitale, appelez le 190 (SAMU) ou le 198 (Protection Civile). Canicule Info : 0800 06 66 66.';

        return implode(' ', $parties);
    }

    /**
     * Y a-t-il au moins un équipement vital (médical ou criticité vitale) ?
     *
     * @param  Collection<int, Equipement>  $equipements
     */
    public function aEquipementVital(Collection $equipements): bool
    {
        return $equipements->contains(
            fn (Equipement $e): bool => (bool) $e->type?->medical || $e->criticite === CriticiteEquipement::Vitale,
        );
    }

    // -------------------------------------------------------------------------
    // Prompts
    // -------------------------------------------------------------------------

    private function promptSysteme(): string
    {
        return <<<'PROMPT'
Tu es un assistant de résilience urbaine canicule / coupure de courant.
Tu aides les habitants à protéger leurs équipements sensibles et leur santé.
Réponds toujours en français, de manière concise (4-6 phrases maximum).
Commence par la recommandation la plus urgente.
Si un équipement médical est présent et qu'une coupure est prévue, indique-le en priorité absolue.
Termine toujours par les numéros d'urgence tunisiens : 190 (SAMU), 198 (Protection Civile), 0800 06 66 66 (Canicule Info).
Ne mentionne jamais de données fictives.
PROMPT;
    }

    /**
     * @param  array<string, mixed>  $contexte
     */
    private function promptUtilisateur(array $contexte): string
    {
        $lignes = [];

        if (! empty($contexte['equipements'])) {
            $lignes[] = 'Équipements déclarés :';
            foreach ($contexte['equipements'] as $e) {
                $ligne = '- '.$e['nom'].' ('.$e['type'].', criticité : '.$e['criticite'].')';
                if ($e['medical']) {
                    $ligne .= ' [MÉDICAL]';
                }
                if ($e['contact_urgence']) {
                    $ligne .= ' — contact : '.$e['contact_urgence'];
                }
                $lignes[] = $ligne;
            }
        }

        if ($contexte['temperature'] !== null) {
            $lignes[] = sprintf(
                'Météo : %.1f°C (ressenti %.1f°C), humidité %d%%, %s.',
                $contexte['temperature'],
                $contexte['ressentie'] ?? $contexte['temperature'],
                $contexte['humidite'] ?? 0,
                $contexte['condition'] ?? 'conditions inconnues',
            );
        }

        if (! empty($contexte['coupures'])) {
            $lignes[] = 'Coupures dans la zone :';
            foreach ($contexte['coupures'] as $c) {
                $ligne = '- '.$c['statut'];
                if ($c['debut'] && $c['fin']) {
                    $ligne .= ' de '.$c['debut'].' à '.$c['fin'];
                }
                $lignes[] = $ligne;
            }
        }

        if ($contexte['alerte_vitale']) {
            $lignes[] = '⚠ ALERTE : équipement médical vital présent — priorité maximale.';
        }

        $lignes[] = 'Génère des recommandations personnalisées et priorisées pour cet habitant.';

        return implode("\n", $lignes);
    }
}
