<?php

namespace App\Http\Controllers\Back;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreQuartierRequest;
use App\Models\Quartier;

class QuartierController extends Controller
{
    public function index()
    {
        $quartiers = Quartier::withCount('residences')
            ->orderBy('nom')
            ->get();

        return view('back.quartiers.index', compact('quartiers'));
    }

    public function create()
    {
        $quartier = null;

        return view('back.quartiers.create', compact('quartier'));
    }

    public function store(StoreQuartierRequest $request)
    {
        Quartier::create($request->validated());

        return redirect()->route('back.quartiers.index')
            ->with('success', 'Quartier créé avec succès.');
    }

    public function edit(int $id)
    {
        $quartier = Quartier::findOrFail($id);

        return view('back.quartiers.edit', compact('quartier'));
    }

    public function update(StoreQuartierRequest $request, int $id)
    {
        $quartier = Quartier::findOrFail($id);
        $quartier->update($request->validated());

        return redirect()->route('back.quartiers.index')
            ->with('success', 'Quartier mis à jour avec succès.');
    }

    public function destroy(int $id)
    {
        $quartier = Quartier::findOrFail($id);

        if ($quartier->residences()->exists()) {
            return back()->with('error', 'Impossible de supprimer un quartier qui contient des résidences.');
        }

        $quartier->delete();

        return redirect()->route('back.quartiers.index')
            ->with('success', 'Quartier supprimé.');
    }
}
