<?php

namespace App\Http\Controllers\Back;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreResidenceRequest;
use App\Models\Quartier;
use App\Models\Residence;
use Illuminate\Http\Request;

class ResidenceController extends Controller
{
    public function index()
    {
        $residences = Residence::with('quartier')
            ->orderBy(Quartier::select('nom')->whereColumn('quartiers.id', 'residences.quartier_id'))
            ->orderBy('nom')
            ->get();

        return view('back.residences.index', compact('residences'));
    }

    public function create()
    {
        $quartiers = Quartier::orderBy('nom')->get();
        $residence = null;

        return view('back.residences.create', compact('quartiers', 'residence'));
    }

    public function store(StoreResidenceRequest $request)
    {
        Residence::create($this->attributes($request));

        return redirect()->route('back.residences.index')
            ->with('success', 'Résidence créée avec succès.');
    }

    public function edit(int $id)
    {
        $residence = Residence::findOrFail($id);
        $quartiers = Quartier::orderBy('nom')->get();

        return view('back.residences.edit', compact('residence', 'quartiers'));
    }

    public function update(StoreResidenceRequest $request, int $id)
    {
        $residence = Residence::findOrFail($id);
        $residence->update($this->attributes($request));

        return redirect()->route('back.residences.index')
            ->with('success', 'Résidence mise à jour avec succès.');
    }

    public function destroy(int $id)
    {
        Residence::findOrFail($id)->delete();

        return redirect()->route('back.residences.index')
            ->with('success', 'Résidence supprimée.');
    }

    /**
     * Attributs validés d'une résidence.
     *
     * Les cases à cocher ne sont envoyées que lorsqu'elles sont cochées : on les
     * normalise explicitement en booléens pour que la décoche soit bien prise en compte.
     *
     * @return array<string, mixed>
     */
    private function attributes(Request $request): array
    {
        return [
            ...$request->validated(),
            'salle_climatisee' => $request->boolean('salle_climatisee'),
            'point_fraicheur' => $request->boolean('point_fraicheur'),
        ];
    }
}
