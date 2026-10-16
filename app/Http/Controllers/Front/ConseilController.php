<?php

namespace App\Http\Controllers\Front;

use App\Http\Controllers\Controller;
use App\Models\Conseil;
use App\Services\ConseilAiService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\View\View;

/**
 * Conseils personnalisés pour l'habitant.
 *
 * Croise : la météo actuelle du foyer + les coupures actives dans la zone
 * + les équipements sensibles déclarés → recommandations IA priorisées.
 * Module 4 — Ghazi.
 */
class ConseilController extends Controller
{
    public function __construct(
        private ConseilAiService $ia,
    ) {}

    public function index(Request $request): View
    {
        $user = $request->user();
        $user->loadMissing('residence.quartier');

        $lieu       = $user->lieuPrincipal();
        $quartierId = $lieu?->quartier_id ?? $user->residence?->quartier_id;

        // Clé de cache : propre à chaque habitant + zone + ses équipements.
        // TTL 15 min : le message change si une coupure démarre ou si la météo évolue.
        $equipIds = $user->equipements()->orderBy('id')->pluck('id')->implode(',');
        $cacheKey = "conseils:user:{$user->id}:zone:{$quartierId}:eq:{$equipIds}";

        $resultat = Cache::remember(
            $cacheKey,
            now()->addMinutes(15),
            fn () => $this->ia->recommandationsPour($user, $quartierId),
        );

        $conseils      = $resultat['conseils'];
        $messageIa     = $resultat['message_ia'];
        $alerteVitale  = $resultat['alerte_vitale'];

        // Équipements déclarés (pour la sidebar récapitulative).
        $equipements = $user->equipements()->with('type')->parCriticite()->get();

        return view('front.conseils', compact(
            'conseils', 'messageIa', 'alerteVitale', 'equipements', 'lieu'
        ));
    }
}
