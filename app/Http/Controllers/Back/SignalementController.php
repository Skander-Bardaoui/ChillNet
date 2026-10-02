<?php

namespace App\Http\Controllers\Back;

use App\Http\Controllers\Controller;
use App\Models\Signalement;
use App\Notifications\SignalementNotification;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class SignalementController extends Controller
{
    public function index(Request $request): View
    {
        $signalements = Signalement::with(['habitant', 'residence'])
            ->when($request->filled('categorie'), fn ($query) => $query->where('categorie', $request->string('categorie')))
            ->when($request->filled('urgence'), fn ($query) => $query->where('urgence', $request->string('urgence')))
            ->when($request->filled('statut'), fn ($query) => $query->where('statut', $request->string('statut')))
            ->orderByRaw("CASE urgence WHEN 'vitale' THEN 1 WHEN 'prioritaire' THEN 2 ELSE 3 END")
            ->latest()
            ->get();

        return view('back.signalements.index', [
            'signalements' => $signalements,
            'filters' => $request->only(['categorie', 'urgence', 'statut']),
        ]);
    }

    public function update(Request $request, Signalement $signalement): RedirectResponse
    {
        $data = $request->validate([
            'statut' => ['required', Rule::in(['nouveau', 'en_traitement', 'resolu'])],
            'urgence' => ['required', Rule::in(['normale', 'prioritaire', 'vitale'])],
        ]);

        if ($signalement->statut !== $data['statut'] || $signalement->urgence !== $data['urgence']) {
            $signalement->update($data);
            if ($signalement->statut !== $data['statut']) {
                $signalement->habitant->notify(new SignalementNotification($signalement, 'status_changed'));
            }
        }

        return to_route('back.signalements.index')->with('success', 'Le statut du signalement a été mis à jour.');
    }
}