<?php

namespace App\Http\Controllers\Front;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreEquipementRequest;
use App\Models\Equipement;
use App\Models\EquipementType;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Gestion des équipements sensibles déclarés par un habitant.
 * Toutes les actions sont strictement scopées à l'utilisateur connecté.
 * Module 4 — Ghazi.
 */
class EquipementController extends Controller
{
    public function index(Request $request): View
    {
        $user = $request->user();

        $equipements = $user->equipements()
            ->with('type')
            ->parCriticite()
            ->get();

        $types = EquipementType::actifs()->get();

        return view('front.equipements', compact('equipements', 'types'));
    }

    public function store(StoreEquipementRequest $request): RedirectResponse
    {
        $request->user()->equipements()->create($request->validated());

        return to_route('equipements.index')
            ->with('success', 'Équipement déclaré avec succès.');
    }

    public function edit(Request $request, Equipement $equipement): View
    {
        $this->autoriser($request, $equipement);

        $types = EquipementType::actifs()->get();

        return view('front.equipements-edit', compact('equipement', 'types'));
    }

    public function update(StoreEquipementRequest $request, Equipement $equipement): RedirectResponse
    {
        $this->autoriser($request, $equipement);

        $equipement->update($request->validated());

        return to_route('equipements.index')
            ->with('success', 'Équipement mis à jour.');
    }

    public function destroy(Request $request, Equipement $equipement): RedirectResponse
    {
        $this->autoriser($request, $equipement);

        $equipement->delete();

        return to_route('equipements.index')
            ->with('success', 'Équipement supprimé.');
    }

    private function autoriser(Request $request, Equipement $equipement): void
    {
        abort_unless((int) $equipement->user_id === (int) $request->user()->id, 403);
    }
}
