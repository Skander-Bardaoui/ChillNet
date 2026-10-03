<?php

namespace App\Http\Controllers\Back;

use App\Enums\Role;
use App\Enums\StatutCoupure;
use App\Http\Controllers\Controller;
use App\Models\Alerte;
use App\Models\Coupure;
use App\Models\Lieu;
use App\Models\Quartier;
use App\Models\Residence;
use App\Models\Signalement;
use App\Models\User;
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

        return view('back.dashboard', compact(
            'stats',
            'dernieresAlertes',
            'dernieresCoupures',
            'derniersSignalements',
        ));
    }
}
