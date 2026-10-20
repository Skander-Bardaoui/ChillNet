<?php

namespace App\Http\Controllers\Front;

use App\Http\Controllers\Controller;
use App\Models\Conseil;
use App\Models\Equipement;
use App\Services\ConseilAiService;
use App\Services\ConseilRiskService;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\View\View;

/**
 * Conseils personnalisés pour l'habitant — Module 4 (Ghazi).
 *
 * Pipeline d'enrichissement :
 *  1. Groq / message de secours  (ConseilAiService)
 *  2. Score de risque ML         (ConseilRiskService → microservice Python)
 *  3. Priorisation des conseils  (conseils liés aux équipements vitaux en tête)
 *
 * RÈGLE DE SÉCURITÉ ABSOLUE (héritée de ConseilRiskService) :
 *   criticité vitale → alerte_vitale = true QUOI QUE dise le modèle.
 *
 * En cas d'indisponibilité du service ML, ConseilRiskService retombe sur les
 * règles PHP déterministes. Cette page ne renvoie JAMAIS une 500.
 */
class ConseilController extends Controller
{
    public function __construct(
        private ConseilAiService  $ia,
        private ConseilRiskService $risk,
    ) {}

    public function index(Request $request): View
    {
        $user = $request->user();
        $user->loadMissing('residence.quartier');

        $lieu        = $user->lieuPrincipal();
        $quartierId  = $lieu?->quartier_id ?? $user->residence?->quartier_id;

        // Chargement des équipements UNE seule fois (évite le N+1 du contrôleur original)
        $equipements = $user->equipements()->with('type')->parCriticite()->get();

        // ------------------------------------------------------------------ //
        // 1. Score de risque ML (cache court : 10 min, invalidé par updated_at)
        // ------------------------------------------------------------------ //
        $eqCacheKey = $equipements->max('updated_at')?->timestamp ?? 0;
        $riskKey    = "risk:user:{$user->id}:zone:{$quartierId}:eq:{$eqCacheKey}";

        $risque = Cache::get($riskKey);

        if (! is_array($risque)) {
            $risque = $this->risk->evaluerFoyer($user, $quartierId);

            // Un repli « règles » (service ML momentanément arrêté) n'est
            // jamais mis en cache : dès que le service revient, le prochain
            // chargement récupère le modèle et sa distribution de
            // probabilités. Seul un résultat réel du modèle est mémorisé.
            if (($risque['source'] ?? null) === 'modele') {
                Cache::put($riskKey, $risque, now()->addMinutes(10));
            }
        }

        // ------------------------------------------------------------------ //
        // 2. Message Groq / fallback déterministe (cache 15 min)
        // ------------------------------------------------------------------ //
        $equipIds = $equipements->pluck('id')->sort()->implode(',');
        $groqKey  = "conseils:user:{$user->id}:zone:{$quartierId}:eq:{$equipIds}";

        $resultatGroq = Cache::remember(
            $groqKey,
            now()->addMinutes(15),
            fn () => $this->ia->recommandationsPour($user, $quartierId),
        );

        // ------------------------------------------------------------------ //
        // 3. Conseils DB priorisés par le niveau de risque ML
        // ------------------------------------------------------------------ //
        $conseils = $this->prioriserConseils(
            conseils:    $resultatGroq['conseils'],
            equipements: $equipements,
            niveauRisk:  $risque['niveau_final'],
        );

        // RÈGLE ABSOLUE : si le risque est critique ou si Groq détecte du vital,
        // alerte_vitale est vrai dans tous les cas.
        $alerteVitale = $risque['alerte_vitale'] || $resultatGroq['alerte_vitale'];

        $messageIa    = $resultatGroq['message_ia'];
        $analyseParEq = $risque['analyseParEq'] ?? [];
        $scenarios    = $risque['scenarios'] ?? [];

        return view('front.conseils', compact(
            'conseils',
            'messageIa',
            'alerteVitale',
            'equipements',
            'lieu',
            'risque',
            'analyseParEq',
            'scenarios',
        ));
    }

    /**
     * Priorise les conseils selon le niveau de risque ML :
     *  - niveau critique/eleve → conseils médicaux et équipements vitaux en tête
     *  - puis conseils liés aux types déclarés par l'habitant
     *  - puis conseils généraux
     *
     * @param  Collection<int, Conseil>       $conseils
     * @param  Collection<int, Equipement>    $equipements
     * @return Collection<int, Conseil>
     */
    private function prioriserConseils(
        Collection $conseils,
        Collection $equipements,
        string $niveauRisk,
    ): Collection {
        $typeIds = $equipements->pluck('equipement_type_id')->unique()->all();

        return $conseils->sortByDesc(function (Conseil $conseil) use ($typeIds, $niveauRisk): int {
            $score = 0;

            // Conseils médicaux prioritaires quand le risque est élevé/critique
            if (in_array($niveauRisk, ['eleve', 'critique'], true)
                && in_array($conseil->categorie->value, ['medical', 'equipements'], true)
            ) {
                $score += 30;
            }

            // Boost si le conseil est lié à un équipement déclaré par l'habitant
            $liees = $conseil->equipementTypes->pluck('id')->intersect($typeIds);
            $score += $liees->count() * 10;

            // Catégorie médicale toujours légèrement prioritaire
            if ($conseil->categorie->value === 'medical') {
                $score += 5;
            }

            // Ordre de base (ordre croissant inversé pour trier desc)
            $score -= $conseil->ordre;

            return $score;
        })->values();
    }
}
