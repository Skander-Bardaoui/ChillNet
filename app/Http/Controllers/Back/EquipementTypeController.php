<?php

namespace App\Http\Controllers\Back;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreEquipementTypeRequest;
use App\Models\EquipementType;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Illuminate\Support\Str;

/**
 * Back office — catalogue des types d'équipements sensibles (admin uniquement).
 * Module 4 — Ghazi.
 */
class EquipementTypeController extends Controller
{
    public function index(Request $request): View
    {
        $types = EquipementType::withCount('equipements')
            ->orderBy('ordre')
            ->orderBy('nom')
            ->paginate(20);

        return view('back.equipement-types.index', compact('types'));
    }

    public function create(): View
    {
        $type = null;

        return view('back.equipement-types.create', compact('type'));
    }

    public function store(StoreEquipementTypeRequest $request): RedirectResponse
    {
        $data          = $request->validated();
        $data['actif'] = $request->boolean('actif', true);
        $data['medical'] = $request->boolean('medical', false);
        $data['ordre'] = (int) ($data['ordre'] ?? 0);
        $data['slug']  = Str::slug($data['slug'], '_');

        EquipementType::create($data);

        return redirect()->route('back.equipement-types.index')
            ->with('success', 'Type d\'équipement créé.');
    }

    public function edit(EquipementType $equipementType): View
    {
        $type = $equipementType;

        return view('back.equipement-types.edit', compact('type'));
    }

    public function update(StoreEquipementTypeRequest $request, EquipementType $equipementType): RedirectResponse
    {
        $data = $request->validated();
        $data['actif']   = $request->boolean('actif', true);
        $data['medical'] = $request->boolean('medical', false);
        $data['ordre']   = (int) ($data['ordre'] ?? 0);
        $data['slug']    = Str::slug($data['slug'], '_');

        $equipementType->update($data);

        return redirect()->route('back.equipement-types.index')
            ->with('success', 'Type d\'équipement mis à jour.');
    }

    public function destroy(EquipementType $equipementType): RedirectResponse
    {
        // Refuse la suppression si des habitants ont déclaré ce type.
        if ($equipementType->equipements()->exists()) {
            return redirect()->route('back.equipement-types.index')
                ->with('error', 'Impossible de supprimer : des habitants ont déclaré des équipements de ce type ('.$equipementType->equipements()->count().' équipement(s)).');
        }

        $equipementType->conseils()->detach();
        $equipementType->delete();

        return redirect()->route('back.equipement-types.index')
            ->with('success', 'Type d\'équipement supprimé.');
    }
}
