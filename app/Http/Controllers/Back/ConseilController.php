<?php

namespace App\Http\Controllers\Back;

use App\Enums\CategorieConseil;
use App\Http\Controllers\Controller;
use App\Http\Requests\StoreConseilRequest;
use App\Models\Conseil;
use App\Models\EquipementType;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Back office — gestion de la base de conseils (admin + gestionnaire).
 * Module 4 — Ghazi.
 */
class ConseilController extends Controller
{
    public function index(Request $request): View
    {
        $query = Conseil::with('equipementTypes')
            ->when(
                $request->filled('categorie'),
                fn ($q) => $q->where('categorie', $request->input('categorie'))
            )
            ->when(
                $request->filled('search'),
                fn ($q) => $q->where('titre', 'like', '%'.$request->input('search').'%')
            )
            ->orderBy('ordre')
            ->orderBy('titre');

        $conseils    = $query->paginate(15)->withQueryString();
        $categories  = CategorieConseil::cases();
        $filters     = $request->only(['categorie', 'search']);

        $stats = [
            'total'   => Conseil::count(),
            'actifs'  => Conseil::where('actif', true)->count(),
            'inactifs'=> Conseil::where('actif', false)->count(),
        ];

        return view('back.conseils.index', compact('conseils', 'categories', 'filters', 'stats'));
    }

    public function create(): View
    {
        $conseil        = null;
        $categories     = CategorieConseil::cases();
        $equipementTypes = EquipementType::actifs()->get();

        return view('back.conseils.create', compact('conseil', 'categories', 'equipementTypes'));
    }

    public function store(StoreConseilRequest $request): RedirectResponse
    {
        $data = $request->validated();
        $typeIds = $data['equipement_type_ids'] ?? [];
        unset($data['equipement_type_ids']);

        $data['user_id'] = $request->user()?->id;
        $data['actif']   = $request->boolean('actif', true);
        $data['ordre']   = (int) ($data['ordre'] ?? 0);

        $conseil = Conseil::create($data);
        $conseil->equipementTypes()->sync($typeIds);

        return redirect()->route('back.conseils.index')
            ->with('success', 'Conseil créé avec succès.');
    }

    public function edit(Conseil $conseil): View
    {
        $conseil->load('equipementTypes');
        $categories      = CategorieConseil::cases();
        $equipementTypes = EquipementType::actifs()->get();
        $selectedTypeIds = $conseil->equipementTypes->pluck('id')->all();

        return view('back.conseils.edit', compact('conseil', 'categories', 'equipementTypes', 'selectedTypeIds'));
    }

    public function update(StoreConseilRequest $request, Conseil $conseil): RedirectResponse
    {
        $data = $request->validated();
        $typeIds = $data['equipement_type_ids'] ?? [];
        unset($data['equipement_type_ids']);

        $data['actif'] = $request->boolean('actif', true);
        $data['ordre'] = (int) ($data['ordre'] ?? 0);

        $conseil->update($data);
        $conseil->equipementTypes()->sync($typeIds);

        return redirect()->route('back.conseils.index')
            ->with('success', 'Conseil mis à jour.');
    }

    public function destroy(Conseil $conseil): RedirectResponse
    {
        $conseil->equipementTypes()->detach();
        $conseil->delete();

        return redirect()->route('back.conseils.index')
            ->with('success', 'Conseil supprimé.');
    }
}
