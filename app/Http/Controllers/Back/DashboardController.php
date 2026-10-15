<?php

namespace App\Http\Controllers\Back;

use App\Enums\Role;
use App\Enums\StatutCoupure;
use App\Http\Controllers\Controller;
use App\Models\AiPrediction;
use App\Models\Alerte;
use App\Models\Coupure;
use App\Models\CoupureRiskSnapshot;
use App\Models\Lieu;
use App\Models\Quartier;
use App\Models\Residence;
use App\Models\Signalement;
use App\Models\User;
use App\Services\Ai\AiServiceClient;
use App\Services\Ai\CoupureAiClient;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Tableau de bord de l'espace de gestion.
 *
 *  - Admin        : vision globale du réseau (tous les modules, toutes zones).
 *  - Gestionnaire : mêmes sections, restreintes à SA zone (le quartier de sa
 *                   résidence) — alertes de la zone, coupures de la zone,
 *                   signalements de sa résidence.
 */
class DashboardController extends Controller
{
    public function __construct(
        private AiServiceClient $ia,
        private CoupureAiClient $iaCoupures,
    ) {}

    public function index(Request $request): View
    {
        $user = $request->user();
        $global = $user->isAdmin();

        // Périmètre : null = global (admin). Pour un gestionnaire sans zone,
        // le sentinelle 0 garantit des compteurs à zéro plutôt qu'une fuite.
        $quartierId = $global ? null : $user->residence?->quartier_id;
        $residenceId = $global ? null : $user->residence?->id;

        $alertes = Alerte::query()
            ->when(! $global, fn ($q) => $q->parQuartier($quartierId ?? 0));

        $coupures = Coupure::query()
            ->when(! $global, fn ($q) => $q->where('quartier_id', $quartierId ?? 0));

        $signalements = Signalement::query()
            ->when(! $global, fn ($q) => $q->where('residence_id', $residenceId ?? 0));

        $stats = [
            'quartiers' => $global ? Quartier::count() : ($quartierId ? 1 : 0),
            'residences' => $global ? Residence::count() : ($residenceId ? 1 : 0),

            'habitants' => $global
                ? User::where('role', Role::Habitant->value)->count()
                : User::where('role', Role::Habitant->value)
                    ->when($quartierId, fn ($q) => $q->whereHas('residence', fn ($qq) => $qq->where('quartier_id', $quartierId)))
                    ->count(),

            'lieux' => $global
                ? Lieu::count()
                : Lieu::when($quartierId, fn ($q) => $q->where('quartier_id', $quartierId))->count(),

            'alertes_actives' => (clone $alertes)->actives()->count(),
            'alertes_a_valider' => (clone $alertes)->where('validee', false)->count(),

            'coupures_en_cours' => (clone $coupures)->where('statut', StatutCoupure::EnCours->value)->count(),
            'coupures_prevues' => (clone $coupures)->where('statut', StatutCoupure::Prevue->value)->count(),

            'signalements_nouveaux' => (clone $signalements)->where('statut', 'nouveau')->count(),
        ];

        // Activité récente (5 plus récents par module, dans le périmètre).
        $dernieresAlertes = (clone $alertes)->with('quartiers')->recentes()->limit(5)->get();
        $dernieresCoupures = (clone $coupures)->with('quartier')->orderByDesc('debut')->limit(5)->get();
        $derniersSignalements = (clone $signalements)->with(['habitant', 'residence'])->latest()->limit(5)->get();

        // Performance IA (admin uniquement) : taux d'accord gestionnaire/IA,
        // nombre de corrections, version et date du modèle.
        $iaStats = $global ? $this->performanceIa() : null;
        $iaCoupures = $global ? $this->performanceCoupures() : null;

        return view('back.dashboard', compact(
            'stats',
            'dernieresAlertes',
            'dernieresCoupures',
            'derniersSignalements',
            'iaStats',
            'iaCoupures',
        ));
    }

    /**
     * Indicateurs de la boucle de retour d'expérience IA.
     *
     * « Accord » = le gestionnaire a retenu le niveau recommandé par le moteur
     * (le plus grave entre le modèle et les règles), parmi les prédictions dont
     * il a figé le niveau final. Les métriques de version proviennent du
     * microservice Python (/model-info), silencieusement ignorées s'il est
     * indisponible.
     *
     * @return array{predictions: int, corrections: int, accord: ?float, version: ?string, trained_at: ?string, n_samples: ?int}
     */
    private function performanceIa(): array
    {
        $meta = $this->ia->modelInfo();

        try {
            $predictions = AiPrediction::count();
            $corrigees = AiPrediction::corrigees()->get();
        } catch (\Throwable) {
            $predictions = 0;
            $corrigees = collect();
        }

        $accord = $corrigees->filter(function (AiPrediction $p): bool {
            $recommande = $this->plusGrave($p->niveau_modele, $p->niveau_regles);

            return $recommande !== null && $p->niveau_final === $recommande;
        })->count();

        return [
            'predictions' => $predictions,
            'corrections' => $corrigees->count(),
            'accord' => $corrigees->isNotEmpty()
                ? round($accord / $corrigees->count() * 100, 1)
                : null,
            'version' => $meta['version'] ?? null,
            'trained_at' => $meta['trained_at'] ?? $meta['date'] ?? null,
            'n_samples' => $meta['n_samples'] ?? ($meta['metrics']['n_samples'] ?? null),
        ];
    }

    /**
     * Performance du modèle IA du module 2 (coupures) : version, métriques du
     * hold-out (AUC, Brier, recall au palier « élevé »), nombre de snapshots
     * réels labellisés. Best-effort — `null` si le service est indisponible.
     *
     * @return array{version: ?string, trained_at: ?string, auc: ?float, brier: ?float, recall: ?float, auc_regles: ?float, snapshots: int}|null
     */
    private function performanceCoupures(): ?array
    {
        $insights = $this->iaCoupures->insights();

        try {
            $snapshots = CoupureRiskSnapshot::query()->labelises()->count();
        } catch (\Throwable) {
            $snapshots = 0;
        }

        if ($insights === null) {
            return $snapshots > 0
                ? ['version' => null, 'trained_at' => null, 'auc' => null, 'brier' => null, 'recall' => null, 'auc_regles' => null, 'snapshots' => $snapshots]
                : null;
        }

        $metrics = is_array($insights['metrics'] ?? null) ? $insights['metrics'] : [];
        $evaluation = is_array($insights['evaluation'] ?? null) ? $insights['evaluation'] : [];

        return [
            'version' => $insights['version'] ?? null,
            'trained_at' => $insights['trained_at'] ?? null,
            'auc' => $metrics['model24']['roc_auc'] ?? ($evaluation['modele_p24']['roc_auc'] ?? null),
            'brier' => $metrics['model24']['brier'] ?? ($evaluation['modele_p24']['brier'] ?? null),
            'recall' => $evaluation['modele_niveaux']['recall_eleve'] ?? null,
            'auc_regles' => $evaluation['regles']['roc_auc'] ?? null,
            'snapshots' => $insights['n_snapshots_labelises'] ?? $snapshots,
        ];
    }

    /**
     * Classe la plus grave parmi deux niveaux bruts (normal < jaune < orange < rouge).
     */
    private function plusGrave(?string ...$niveaux): ?string
    {
        $ordre = ['normal' => 0, 'jaune' => 1, 'orange' => 2, 'rouge' => 3];

        $meilleur = null;
        $meilleureGravite = -1;

        foreach ($niveaux as $niveau) {
            if (! is_string($niveau) || ! array_key_exists($niveau, $ordre)) {
                continue;
            }

            if ($ordre[$niveau] > $meilleureGravite) {
                $meilleureGravite = $ordre[$niveau];
                $meilleur = $niveau;
            }
        }

        return $meilleur;
    }
}
