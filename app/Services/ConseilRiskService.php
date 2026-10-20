<?php

namespace App\Services;

use App\Enums\CriticiteEquipement;
use App\Models\Coupure;
use App\Models\Equipement;
use App\Models\Lieu;
use App\Models\User;
use App\Enums\StatutCoupure;
use App\Services\Ai\ConseilAiClient;
use App\Services\WeatherService;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;

/**
 * Orchestrateur du risque équipements sensibles — Module 4 (Ghazi).
 *
 * Deux garanties non négociables, identiques au module 2 :
 *
 *  1. PLANCHER DE SÉCURITÉ : niveau final = max(modèle, règles déterministes).
 *     Le modèle ML ne peut jamais RÉDUIRE le risque sous ce que les règles
 *     dictent.
 *
 *  2. RÈGLE DE SÉCURITÉ ABSOLUE : tout foyer avec au moins un équipement en
 *     criticité « vitale » reçoit toujours `alerte_vitale = true` et le niveau
 *     final est au moins « critique », QUOI QUE dise le modèle.
 *
 *  3. REPLI : si le service Python est indisponible (timeout, 5xx, désactivé),
 *     on tombe sur les règles PHP déterministes. La page ne renvoie JAMAIS 500.
 */
class ConseilRiskService
{
    /** Niveaux de risque, du moins grave au plus grave. */
    public const NIVEAUX = ['faible', 'modere', 'eleve', 'critique'];

    /** Labels FR pour l'affichage côté vue. */
    public const LABELS_FR = [
        'faible'   => 'Faible',
        'modere'   => 'Modéré',
        'eleve'    => 'Élevé',
        'critique' => 'Critique',
    ];

    /** Classes Tailwind pour les badges de chaque niveau. */
    public const BADGE_CLASSES = [
        'faible'   => 'bg-green-100 text-green-800',
        'modere'   => 'bg-amber-100 text-amber-800',
        'eleve'    => 'bg-orange-100 text-orange-800',
        'critique' => 'bg-red-100 text-red-800',
    ];

    /** Icônes Material Symbols pour chaque niveau. */
    public const ICONES = [
        'faible'   => 'check_circle',
        'modere'   => 'warning',
        'eleve'    => 'report',
        'critique' => 'emergency',
    ];

    public function __construct(
        private ?ConseilAiClient $client = null,
        private ?WeatherService $meteo = null,
    ) {}

    private function client(): ConseilAiClient
    {
        return $this->client ??= app(ConseilAiClient::class);
    }

    private function meteo(): WeatherService
    {
        return $this->meteo ??= app(WeatherService::class);
    }

    /**
     * Définition UNIQUE de « équipement vital » : criticité vitale OU type
     * médical. Identique à ConseilAiService::aEquipementVital et à
     * `alerte_vitale` côté Python (risk.py).
     */
    private function estVital(Equipement $e): bool
    {
        return $e->criticite === CriticiteEquipement::Vitale || (bool) $e->type?->medical;
    }

    /**
     * Niveau de canicule 0..3 dérivé de la température — mêmes seuils que le
     * simulateur et `build_features` côté Python (35 / 38 / 41 °C).
     */
    public static function niveauCaniculeDepuisTemperature(?float $temperature): int
    {
        return match (true) {
            $temperature === null => 0,
            $temperature >= 41    => 3,
            $temperature >= 38    => 2,
            $temperature >= 35    => 1,
            default               => 0,
        };
    }

    /**
     * Météo du lieu principal + état des coupures de la zone : source unique
     * pour le plancher de règles ET le payload du modèle (ils ne divergent
     * donc jamais). Météo indisponible → valeurs `null` (le service Python
     * applique alors ses défauts sûrs).
     *
     * @return array<string, mixed>
     */
    private function contexteExterne(User $user, ?int $quartierId): array
    {
        $releve = null;
        $lieu   = $user->lieuPrincipal();

        if ($lieu?->hasCoordinates()) {
            try {
                $releve = $this->meteo()->actuelCache((float) $lieu->latitude, (float) $lieu->longitude);
            } catch (\Throwable $e) {
                Log::warning('ConseilRiskService : météo indisponible', ['message' => $e->getMessage()]);
            }
        }

        $coupureEnCours = 0;
        $coupures7j     = 0;
        $dureeMoyenneH  = 0.0;

        if ($quartierId !== null) {
            $coupureEnCours = Coupure::where('quartier_id', $quartierId)
                ->where('statut', StatutCoupure::EnCours->value)
                ->count();

            $recentes   = Coupure::where('quartier_id', $quartierId)
                ->where('debut', '>=', Carbon::now()->subDays(7))
                ->get(['debut', 'fin']);
            $coupures7j = $recentes->count();

            $durees = $recentes
                ->filter(fn (Coupure $c) => $c->debut !== null && $c->fin !== null && $c->fin->greaterThan($c->debut))
                ->map(fn (Coupure $c) => (float) $c->debut->diffInMinutes($c->fin, true) / 60);

            $dureeMoyenneH = $durees->isNotEmpty() ? round(min(72.0, (float) $durees->avg()), 1) : 0.0;
        }

        return [
            'temperature'          => $releve?->temperature,
            'ressentie'            => $releve?->ressentie,
            'humidite'             => $releve?->humidite,
            'canicule_niveau'      => self::niveauCaniculeDepuisTemperature($releve?->temperature),
            'coupures_en_cours'    => $coupureEnCours,
            'coupures_7j'          => $coupures7j,
            'duree_moy_coupures_h' => $dureeMoyenneH,
        ];
    }

    /**
     * Évalue le risque du foyer d'un habitant et retourne le résultat enrichi.
     *
     * @return array<string, mixed>
     */
    public function evaluerFoyer(User $user, ?int $quartierId): array
    {
        $user->loadMissing(['equipements.type']);
        $equipements = $user->equipements->sortByDesc(fn (Equipement $e) => $e->criticite->priorite());

        // --- Règle de sécurité absolue ---
        $alerteVitale = $equipements->contains(fn (Equipement $e) => $this->estVital($e));

        // Météo + coupures : relevées UNE fois, partagées règles / payload ML.
        $externe = $this->contexteExterne($user, $quartierId);

        // --- Plancher déterministe PHP ---
        $contexteRegles = $this->construireContexteRegles($user, $equipements, $quartierId, $externe);
        $niveauRegles   = $this->niveauDepuisRegles($contexteRegles);

        // RÈGLE ABSOLUE : vital → au moins critique
        if ($alerteVitale) {
            $niveauRegles = $this->niveauPlusGrave($niveauRegles, 'critique');
        }

        // --- Appel ML ---
        $payloadMl = $this->construirePayloadMl($user, $equipements, $externe);
        $resultMl  = $this->appelerMl($payloadMl);

        if ($resultMl === null) {
            // Service indisponible → règles seules
            return $this->formaterResultat(
                niveauFinal:  $niveauRegles,
                niveauModele: null,
                niveauRegles: $niveauRegles,
                alerteVitale: $alerteVitale,
                confiance:    0.0,
                facteurs:     $this->facteursDepuisRegles($contexteRegles),
                raison:       'Service ML indisponible — règles déterministes appliquées.',
                source:       'regles',
                desaccord:    false,
            ) + [
                'probabilites'  => [],
                'analyseParEq'  => $this->analyserParEquipement($equipements, $contexteRegles),
                'scenarios'     => $this->simulerScenarios($equipements, $contexteRegles),
            ];
        }

        // --- Plancher : max(modèle, règles) ---
        $niveauModele = (string) ($resultMl['niveau_final'] ?? $niveauRegles);
        $niveauFinal  = $this->niveauPlusGrave($niveauModele, $niveauRegles);

        // RÈGLE ABSOLUE côté PHP (double-check indépendant du service Python)
        if ($alerteVitale) {
            $niveauFinal = $this->niveauPlusGrave($niveauFinal, 'critique');
        }

        return $this->formaterResultat(
            niveauFinal:  $niveauFinal,
            niveauModele: $niveauModele,
            niveauRegles: $niveauRegles,
            alerteVitale: $alerteVitale || (bool) ($resultMl['alerte_vitale'] ?? false),
            confiance:    (float) ($resultMl['confiance'] ?? 0.0),
            facteurs:     is_array($resultMl['facteurs'] ?? null) && $resultMl['facteurs'] !== []
                ? array_values($resultMl['facteurs'])
                : $this->facteursDepuisRegles($contexteRegles),
            raison:       is_string($resultMl['raison'] ?? null) && $resultMl['raison'] !== ''
                ? $resultMl['raison']
                : '',
            source:       'modele',
            desaccord:    (bool) ($resultMl['desaccord'] ?? false),
        ) + [
            'probabilites'  => is_array($resultMl['probabilites'] ?? null) ? $resultMl['probabilites'] : [],
            'analyseParEq'  => $this->analyserParEquipement($equipements, $contexteRegles),
            'scenarios'     => $this->simulerScenarios($equipements, $contexteRegles),
        ];
    }

    /**
     * Construit le contexte utilisé par les règles PHP déterministes.
     *
     * @param  Collection<int, Equipement>  $equipements
     * @return array<string, mixed>
     */
    public function construireContexteRegles(
        User $user,
        Collection $equipements,
        ?int $quartierId,
        ?array $externe = null,
    ): array {
        $externe ??= $this->contexteExterne($user, $quartierId);

        $nbMedicaux = $equipements->filter(
            fn (Equipement $e) => (bool) $e->type?->medical,
        )->count();

        $nbVitaux = $equipements->filter(
            fn (Equipement $e) => $e->criticite === CriticiteEquipement::Vitale,
        )->count();

        // priorite() : 1=normale 2=elevee 3=vitale ; foyer sans équipement → 0.
        $criticiteMax = max(0, ($equipements->max(
            fn (Equipement $e) => $e->criticite->priorite(),
        ) ?? 1) - 1);

        return [
            'nb_equipements'           => $equipements->count(),
            'nb_medicaux'              => $nbMedicaux,
            'nb_vitaux'                => $nbVitaux,
            'criticite_max'            => $criticiteMax,  // 0=normale 1=elevee 2=vitale
            'temperature'              => $externe['temperature'],
            'canicule_niveau'          => $externe['canicule_niveau'],
            'jours_chauds_consecutifs' => 0,
            'coupures_en_cours'        => $externe['coupures_en_cours'],
            'coupures_7j'              => $externe['coupures_7j'],
        ];
    }

    /**
     * Règles PHP déterministes — miroir de ``app/conseils/rules.py``.
     * Plancher de sécurité : jamais remplacé par le modèle.
     *
     * @param  array<string, mixed>  $ctx
     */
    public function niveauDepuisRegles(array $ctx): string
    {
        $nbMedicaux  = (int) ($ctx['nb_medicaux']   ?? 0);
        $nbVitaux    = (int) ($ctx['nb_vitaux']     ?? 0);
        $critMax     = (int) ($ctx['criticite_max'] ?? 0);
        $canicule    = (int) ($ctx['canicule_niveau'] ?? 0);
        $coupure     = (int) ($ctx['coupures_en_cours'] ?? 0);
        $joursChauds = (int) ($ctx['jours_chauds_consecutifs'] ?? 0);

        // Règle 1 : équipement vital
        if ($nbVitaux > 0) {
            return $coupure ? 'critique' : 'eleve';
        }

        // Règle 2 : équipement médical
        if ($nbMedicaux > 0) {
            if ($coupure && $canicule >= 2) return 'critique';
            if ($coupure || $canicule >= 2) return 'eleve';
            return 'modere';
        }

        // Règle 3 : criticité élevée
        if ($critMax >= 1) {
            if ($coupure && $canicule >= 2) return 'eleve';
            if ($coupure || $canicule >= 2) return 'modere';
            return 'faible';
        }

        // Règle 4 : aucun équipement sensible
        if ($coupure && $canicule >= 2) return 'modere';
        if ($canicule >= 3 && $joursChauds >= 3) return 'modere';
        return 'faible';
    }

    /**
     * Construit le payload JSON envoyé au service Python.
     *
     * @param  Collection<int, Equipement>  $equipements
     * @param  array<string, mixed>         $externe  météo + coupures (contexteExterne)
     * @return array<string, mixed>
     */
    private function construirePayloadMl(User $user, Collection $equipements, array $externe): array
    {
        $eqPayload = $equipements->map(fn (Equipement $e) => [
            'type'            => $e->type?->slug ?? 'autre',
            'criticite'       => $e->criticite->value,
            'medical'         => (bool) $e->type?->medical,
            'contact_urgence' => $e->contact_urgence,
        ])->values()->all();

        return [
            'foyer_id'                 => $user->id,
            'equipements'              => $eqPayload,
            'temperature'              => $externe['temperature'],
            'ressentie'                => $externe['ressentie'],
            'humidite'                 => $externe['humidite'],
            'canicule_niveau'          => $externe['canicule_niveau'],
            'jours_chauds_consecutifs' => 0,
            'mois'                     => (int) Carbon::now()->month,
            'heure'                    => (int) Carbon::now()->hour,
            'coupures_en_cours'        => $externe['coupures_en_cours'],
            'coupures_7j'              => $externe['coupures_7j'],
            'duree_moy_coupures_h'     => $externe['duree_moy_coupures_h'],
        ];
    }

    /**
     * Appelle le service ML et retourne le premier résultat, ou null si échec.
     *
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>|null
     */
    private function appelerMl(array $payload): ?array
    {
        try {
            $resultats = $this->client()->risk([$payload]);
        } catch (\Throwable $e) {
            Log::warning('ConseilRiskService : appel ML impossible', ['message' => $e->getMessage()]);
            return null;
        }

        if (! is_array($resultats) || ! isset($resultats[0]) || ! is_array($resultats[0])) {
            return null;
        }

        return $resultats[0];
    }

    /**
     * Facteurs lisibles dérivés des règles (affichés quand ML indisponible).
     *
     * @param  array<string, mixed>  $ctx
     * @return list<string>
     */
    public function facteursDepuisRegles(array $ctx): array
    {
        $facteurs = [];

        if ((int) ($ctx['nb_vitaux'] ?? 0) > 0) {
            $facteurs[] = 'Équipement de criticité vitale déclaré';
        }
        if ((int) ($ctx['nb_medicaux'] ?? 0) > 0) {
            $facteurs[] = sprintf('%d équipement(s) médical/médicaux', $ctx['nb_medicaux']);
        }
        if ((int) ($ctx['coupures_en_cours'] ?? 0) > 0) {
            $facteurs[] = 'Coupure de courant en cours dans votre zone';
        }
        if ((int) ($ctx['canicule_niveau'] ?? 0) >= 2) {
            $facteurs[] = 'Canicule orange ou rouge en cours';
        }
        if ((int) ($ctx['coupures_7j'] ?? 0) > 0) {
            $facteurs[] = sprintf('%d coupure(s) sur les 7 derniers jours', $ctx['coupures_7j']);
        }

        return $facteurs;
    }

    /**
     * Retourne le niveau de risque le plus grave entre deux.
     */
    public function niveauPlusGrave(string $a, string $b): string
    {
        $ordre = array_flip(self::NIVEAUX);
        return ($ordre[$a] ?? 0) >= ($ordre[$b] ?? 0) ? $a : $b;
    }

    /**
     * Analyse le risque individuel de chaque équipement du foyer.
     * Permet à l'habitant de voir lequel de ses appareils est le plus dangereux.
     *
     * @param  Collection<int, Equipement>  $equipements
     * @param  array<string, mixed>         $ctx
     * @return list<array<string, mixed>>
     */
    public function analyserParEquipement(Collection $equipements, array $ctx): array
    {
        return $equipements->map(function (Equipement $eq) use ($ctx): array {
            $medical  = (bool) $eq->type?->medical;
            $vital    = $eq->criticite === CriticiteEquipement::Vitale;
            $coupure  = (int) ($ctx['coupures_en_cours'] ?? 0);
            $canicule = (int) ($ctx['canicule_niveau'] ?? 0);

            // Niveau de risque spécifique à cet équipement
            if ($vital || $medical) {
                if ($coupure) {
                    $niveau = 'critique';
                    $conseil = 'Contactez votre relais santé immédiatement. Batterie de secours ?';
                } elseif ($canicule >= 2) {
                    $niveau  = 'eleve';
                    $conseil = 'Vérifiez la batterie de secours avant 17h (pic réseau).';
                } else {
                    $niveau  = 'modere';
                    $conseil = 'Maintenez la batterie chargée et gardez le contact d\'urgence à portée.';
                }
            } elseif ($eq->criticite === CriticiteEquipement::Elevee) {
                $niveau  = ($coupure || $canicule >= 2) ? 'modere' : 'faible';
                $conseil = $coupure
                    ? 'Ne pas ouvrir / débrancher pendant la coupure.'
                    : 'Surveillez les annonces de délestage dans votre zone.';
            } else {
                $niveau  = 'faible';
                $conseil = 'Aucune action urgente requise dans les conditions actuelles.';
            }

            return [
                'id'             => $eq->id,
                'nom'            => $eq->nom,
                'type_nom'       => $eq->type?->nom ?? 'Équipement',
                'type_icone'     => $eq->type?->icone ?? 'devices',
                'criticite'      => $eq->criticite->value,
                'criticite_label'=> $eq->criticite->label(),
                'medical'        => $medical,
                'vital'          => $vital,
                'niveau'         => $niveau,
                'label'          => self::LABELS_FR[$niveau] ?? $niveau,
                'badge_classes'  => self::BADGE_CLASSES[$niveau] ?? 'bg-gray-100 text-gray-700',
                'icone_risque'   => self::ICONES[$niveau] ?? 'info',
                'conseil'        => $conseil,
                'contact_urgence'=> $eq->contact_urgence,
            ];
        })->values()->all();
    }

    /**
     * Simule 3 scénarios pour montrer comment le risque évolue.
     * Rend le modèle pédagogique : "si une coupure démarre, voici ce qui change".
     *
     * @param  Collection<int, Equipement>  $equipements
     * @param  array<string, mixed>         $ctx
     * @return list<array<string, mixed>>
     */
    public function simulerScenarios(Collection $equipements, array $ctx): array
    {
        $scenarios = [];

        // Scénario 1 : coupure soudaine
        $ctxCoupure = array_merge($ctx, ['coupures_en_cours' => 1]);
        $niveauCoupure = $this->niveauDepuisRegles($ctxCoupure);
        if ($equipements->contains(fn (Equipement $e) => $this->estVital($e))) {
            $niveauCoupure = $this->niveauPlusGrave($niveauCoupure, 'critique');
        }
        $scenarios[] = [
            'titre'       => 'Si une coupure démarre maintenant',
            'icone'       => 'power_off',
            'niveau'      => $niveauCoupure,
            'label'       => self::LABELS_FR[$niveauCoupure],
            'badge'       => self::BADGE_CLASSES[$niveauCoupure],
            'action'      => $niveauCoupure === 'critique'
                ? 'Appelez le 190 — équipement médical vital concerné.'
                : ($niveauCoupure === 'eleve' ? 'Sortez vos batteries et glacières.' : 'Surveillez la durée.'),
        ];

        // Scénario 2 : canicule rouge
        $ctxCanicule = array_merge($ctx, ['canicule_niveau' => 3, 'jours_chauds_consecutifs' => 4]);
        $niveauCanicule = $this->niveauDepuisRegles($ctxCanicule);
        if ($equipements->contains(fn (Equipement $e) => $this->estVital($e))) {
            $niveauCanicule = $this->niveauPlusGrave($niveauCanicule, 'eleve');
        }
        $scenarios[] = [
            'titre'  => 'Si une canicule rouge s\'installe',
            'icone'  => 'local_fire_department',
            'niveau' => $niveauCanicule,
            'label'  => self::LABELS_FR[$niveauCanicule],
            'badge'  => self::BADGE_CLASSES[$niveauCanicule],
            'action' => $niveauCanicule === 'critique'
                ? 'Rejoignez un point de fraîcheur avec votre équipement.'
                : 'Limitez les sorties entre 11h et 17h.',
        ];

        // Scénario 3 : coupure + canicule combinées
        $ctxCombine = array_merge($ctx, ['coupures_en_cours' => 1, 'canicule_niveau' => 3]);
        $niveauCombine = $this->niveauDepuisRegles($ctxCombine);
        if ($equipements->contains(fn (Equipement $e) => $this->estVital($e))) {
            $niveauCombine = 'critique';
        }
        $scenarios[] = [
            'titre'  => 'Coupure + canicule simultanées',
            'icone'  => 'emergency_home',
            'niveau' => $niveauCombine,
            'label'  => self::LABELS_FR[$niveauCombine],
            'badge'  => self::BADGE_CLASSES[$niveauCombine],
            'action' => $niveauCombine === 'critique'
                ? 'Urgence absolue — 190 (SAMU) ou 198 (Protection Civile).'
                : 'Refugiez-vous dans un point de fraîcheur. En urgence, appelez le 190 ou le 198.',
        ];

        return $scenarios;
    }

    /**
     * @param  list<string>  $facteurs
     * @return array<string, mixed>
     */
    private function formaterResultat(
        string $niveauFinal,
        ?string $niveauModele,
        string $niveauRegles,
        bool $alerteVitale,
        float $confiance,
        array $facteurs,
        string $raison,
        string $source,
        bool $desaccord,
    ): array {
        $niveauFinal = in_array($niveauFinal, self::NIVEAUX, true) ? $niveauFinal : 'faible';

        return [
            'niveau_final'   => $niveauFinal,
            'niveau_modele'  => $niveauModele,
            'niveau_regles'  => $niveauRegles,
            'alerte_vitale'  => $alerteVitale,
            'confiance'      => $confiance,
            'facteurs'       => $facteurs,
            'label'          => self::LABELS_FR[$niveauFinal] ?? ucfirst($niveauFinal),
            'badge_classes'  => self::BADGE_CLASSES[$niveauFinal] ?? 'bg-gray-100 text-gray-700',
            'icone'          => self::ICONES[$niveauFinal] ?? 'info',
            'raison'         => $raison,
            'source'         => $source,
            'desaccord'      => $desaccord,
        ];
    }
}
