<?php

namespace App\Http\Controllers\Front;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreAvisRequest;
use App\Models\Avis;
use App\Models\PointFraicheur;
use App\Services\SentimentAvisService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Avis des habitants sur les points de fraîcheur (module 3) :
 * dépôt, modification et suppression de SON avis. Le sentiment est analysé
 * par l'IA (Groq, repli lexical) à chaque enregistrement.
 */
class AvisController extends Controller
{
    public function __construct(private SentimentAvisService $sentiment) {}

    public function store(StoreAvisRequest $request, PointFraicheur $point): RedirectResponse
    {
        abort_unless($point->estValide(), 404);

        $avis = new Avis($request->validated());
        $avis->point_fraicheur_id = $point->id;
        $avis->user_id = $request->user()->id;
        $this->analyser($avis);
        $avis->save();

        return redirect()->route('points.show', $point)->withFragment('avis')
            ->with('success', 'Merci pour votre avis !');
    }

    public function edit(Request $request, Avis $avis): View
    {
        $this->autoriser($request, $avis);

        $avis->load('pointFraicheur');

        return view('front.avis.edit', compact('avis'));
    }

    public function update(StoreAvisRequest $request, Avis $avis): RedirectResponse
    {
        $this->autoriser($request, $avis);

        $avis->fill($request->validated());
        if ($avis->isDirty(['note', 'commentaire'])) {
            $this->analyser($avis);
        }
        $avis->save();

        return redirect()->route('points.show', $avis->point_fraicheur_id)->withFragment('avis')
            ->with('success', 'Votre avis a été modifié.');
    }

    public function destroy(Request $request, Avis $avis): RedirectResponse
    {
        $this->autoriser($request, $avis);

        $pointId = $avis->point_fraicheur_id;
        $avis->delete();

        return redirect()->route('points.show', $pointId)->withFragment('avis')
            ->with('success', 'Votre avis a été supprimé.');
    }

    private function analyser(Avis $avis): void
    {
        $analyse = $this->sentiment->analyserAvecIa($avis->commentaire, (int) $avis->note);
        $avis->sentiment = $analyse['sentiment'];
        $avis->score_sentiment = $analyse['score'];
    }

    private function autoriser(Request $request, Avis $avis): void
    {
        abort_unless((int) $avis->user_id === (int) $request->user()->id, 403, 'Cet avis ne vous appartient pas.');
    }
}
