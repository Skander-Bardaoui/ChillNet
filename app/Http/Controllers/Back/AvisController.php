<?php

namespace App\Http\Controllers\Back;

use App\Enums\Sentiment;
use App\Http\Controllers\Controller;
use App\Models\Avis;
use App\Models\PointFraicheur;
use App\Services\SentimentAvisService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Modération des avis (module 3) : liste filtrable par sentiment IA, par
 * note et par point, et suppression des avis abusifs.
 */
class AvisController extends Controller
{
    public function __construct(private SentimentAvisService $sentiment) {}

    public function index(Request $request): View
    {
        $filtres = $request->validate([
            'sentiment' => ['nullable', 'in:'.implode(',', array_column(Sentiment::cases(), 'value'))],
            'note' => ['nullable', 'integer', 'between:1,5'],
            'point' => ['nullable', 'integer', 'exists:points_fraicheur,id'],
        ]);

        $zone = $request->user()->isGestionnaire() ? $request->user()->residence?->quartier_id : null;

        $avis = Avis::query()
            ->with(['user', 'pointFraicheur'])
            ->whereHas('pointFraicheur', fn ($q) => $q->when($zone, fn ($z) => $z->where('quartier_id', $zone)))
            ->when($filtres['sentiment'] ?? null, fn ($q, string $s) => $q->where('sentiment', $s))
            ->when($filtres['note'] ?? null, fn ($q, $n) => $q->where('note', $n))
            ->when($filtres['point'] ?? null, fn ($q, $p) => $q->where('point_fraicheur_id', $p))
            ->latest()
            ->paginate(12)
            ->withQueryString();

        $points = PointFraicheur::query()
            ->when($zone, fn ($q) => $q->where('quartier_id', $zone))
            ->orderBy('nom')
            ->get(['id', 'nom']);

        $repartition = $this->sentiment->repartition();

        return view('back.avis.index', compact('avis', 'points', 'repartition', 'filtres'));
    }

    public function destroy(Request $request, Avis $avis): RedirectResponse
    {
        $zone = $request->user()->isGestionnaire() ? $request->user()->residence?->quartier_id : null;

        if ($zone !== null && (int) $avis->pointFraicheur?->quartier_id !== (int) $zone) {
            abort(403, 'Cet avis concerne un point hors de votre zone.');
        }

        $avis->delete();

        return back()->with('success', 'Avis supprimé.');
    }
}
