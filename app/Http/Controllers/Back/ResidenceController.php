<?php

namespace App\Http\Controllers\Back;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreResidenceRequest;
use App\Models\Quartier;
use App\Models\Residence;
use Illuminate\Http\Request;

/**
 * Séparation des rôles (voulue) :
 *  - Admin        : CRUD global sur toutes les résidences + quartiers.
 *  - Gestionnaire : SA résidence uniquement (lecture + modification).
 *                   Pas de création, pas de suppression, pas d'accès aux
 *                   résidences des autres. Sans résidence rattachée, liste vide
 *                   avec consigne de contacter un admin.
 */
class ResidenceController extends Controller
{
    public function index()
    {
        $user = request()->user();

        if ($user && $user->isGestionnaire()) {
            $residences = Residence::with('quartier')
                ->whereKey($user->residence_id)
                ->get();

            return view('back.residences.index', compact('residences'));
        }

        $residences = Residence::with('quartier')
            ->orderBy(Quartier::select('nom')->whereColumn('quartiers.id', 'residences.quartier_id'))
            ->orderBy('nom')
            ->get();

        return view('back.residences.index', compact('residences'));
    }

    public function create()
    {
        // Seul l'admin déclare de nouvelles résidences dans le référentiel.
        // Le gestionnaire, lui, a déclaré la sienne à l'inscription et ne
        // gère ensuite que celle-ci (modification).
        abort_if(request()->user()?->isGestionnaire(), 403, "Votre résidence est déjà rattachée : contactez un admin pour en déclarer une autre.");

        $quartiers = Quartier::orderBy('nom')->get();
        $residence = null;

        return view('back.residences.create', compact('quartiers', 'residence'));
    }

    public function store(StoreResidenceRequest $request)
    {
        abort_if(request()->user()?->isGestionnaire(), 403, 'Seul un administrateur peut créer une résidence.');

        Residence::create($this->attributes($request));

        return redirect()->route('back.residences.index')
            ->with('success', 'Résidence créée avec succès.');
    }

    public function edit(int $id)
    {
        $this->authorizeResidence($id);

        $residence = Residence::findOrFail($id);
        $quartiers = Quartier::orderBy('nom')->get();

        return view('back.residences.edit', compact('residence', 'quartiers'));
    }

    public function update(StoreResidenceRequest $request, int $id)
    {
        $this->authorizeResidence($id);

        $residence = Residence::findOrFail($id);
        $residence->update($this->attributes($request));

        return redirect()->route('back.residences.index')
            ->with('success', 'Résidence mise à jour avec succès.');
    }

    public function destroy(int $id)
    {
        // La suppression d'une résidence (avec ses foyers rattachés) est une
        // action globale réservée à l'admin.
        abort_if(request()->user()?->isGestionnaire(), 403, 'Seul un administrateur peut supprimer une résidence.');

        Residence::findOrFail($id)->delete();

        return redirect()->route('back.residences.index')
            ->with('success', 'Résidence supprimée.');
    }

    /**
     * Un gestionnaire ne peut ouvrir / modifier que sa propre résidence.
     */
    private function authorizeResidence(int $id): void
    {
        $user = request()->user();

        if ($user?->isGestionnaire() && (int) $user->residence_id !== (int) $id) {
            abort(403, 'Vous ne pouvez gérer que votre propre résidence.');
        }
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
