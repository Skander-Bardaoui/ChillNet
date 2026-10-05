<?php

namespace App\Http\Controllers\Back;

use App\Enums\Role;
use App\Http\Controllers\Controller;
use App\Models\Residence;
use App\Models\Signalement;
use App\Models\User;
use App\Notifications\SignalementNotification;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class SignalementController extends Controller
{
    public function index(Request $request): View
    {
        $stats = $this->accessibleSignalements($request)
            ->selectRaw(
                "COUNT(*) as total,
                SUM(CASE WHEN statut = 'nouveau' THEN 1 ELSE 0 END) as nouveaux,
                SUM(CASE WHEN statut = 'en_traitement' THEN 1 ELSE 0 END) as en_traitement,
                SUM(CASE WHEN statut = 'resolu' THEN 1 ELSE 0 END) as resolus,
                SUM(CASE WHEN urgence = 'vitale' THEN 1 ELSE 0 END) as urgences_vitales"
            )
            ->first();

        $signalements = $this->accessibleSignalements($request)
            ->with(['habitant', 'residence'])
            ->when($request->filled('categorie'), fn ($query) => $query->where('categorie', $request->string('categorie')))
            ->when($request->filled('urgence'), fn ($query) => $query->where('urgence', $request->string('urgence')))
            ->when($request->filled('statut'), fn ($query) => $query->where('statut', $request->string('statut')))
            ->orderByRaw("CASE urgence WHEN 'vitale' THEN 1 WHEN 'prioritaire' THEN 2 ELSE 3 END")
            ->latest()
            ->get();

        return view('back.signalements.index', [
            'signalements' => $signalements,
            'stats' => $stats,
            'filters' => $request->only(['categorie', 'urgence', 'statut']),
        ]);
    }

    public function create(Request $request): View
    {
        $this->ensureManagerHasResidence($request);

        return view('back.signalements.create', [
            'signalement' => null,
            'habitants' => $this->accessibleHabitants($request),
            'residences' => $this->accessibleResidences($request),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $this->ensureManagerHasResidence($request);
        $data = $this->validateReport($request);
        $this->ensureNoDuplicate($data);
        $data['photo_path'] = $request->hasFile('photo')
            ? $request->file('photo')->store('signalements', 'public')
            : null;
        unset($data['photo']);

        $signalement = Signalement::create($data);
        $signalement->habitant->notify(new SignalementNotification($signalement, 'created'));

        return to_route('back.signalements.index')->with('success', 'Signalement créé avec succès.');
    }

    public function edit(Request $request, Signalement $signalement): View
    {
        $this->authorizeSignalement($request, $signalement);

        return view('back.signalements.edit', [
            'signalement' => $signalement,
            'habitants' => $this->accessibleHabitants($request),
            'residences' => $this->accessibleResidences($request),
        ]);
    }

    public function update(Request $request, Signalement $signalement): RedirectResponse
    {
        $this->authorizeSignalement($request, $signalement);
        $data = $this->validateReport($request);
        $this->ensureNoDuplicate($data, $signalement);

        $anciennePhoto = $signalement->photo_path;
        if ($request->hasFile('photo')) {
            $data['photo_path'] = $request->file('photo')->store('signalements', 'public');
        } else {
            unset($data['photo']);
        }

        $statutChanged = $signalement->statut !== $data['statut'];
        $signalement->update($data);

        if ($request->hasFile('photo') && $anciennePhoto) {
            Storage::disk('public')->delete($anciennePhoto);
        }
        if ($statutChanged) {
            $signalement->habitant->notify(new SignalementNotification($signalement, 'status_changed'));
        }

        return to_route('back.signalements.index')->with('success', 'Signalement mis à jour avec succès.');
    }

    public function destroy(Request $request, Signalement $signalement): RedirectResponse
    {
        $this->authorizeSignalement($request, $signalement);

        if ($signalement->photo_path) {
            Storage::disk('public')->delete($signalement->photo_path);
        }
        $signalement->delete();

        return to_route('back.signalements.index')->with('success', 'Signalement supprimé.');
    }

    public function updateTreatment(Request $request, Signalement $signalement): RedirectResponse
    {
        $this->authorizeSignalement($request, $signalement);
        $data = $request->validate([
            'statut' => ['required', Rule::in(['nouveau', 'en_traitement', 'resolu'])],
            'urgence' => ['required', Rule::in(['normale', 'prioritaire', 'vitale'])],
        ]);

        $statutChanged = $signalement->statut !== $data['statut'];
        $signalement->update($data);
        if ($statutChanged) {
            $signalement->habitant->notify(new SignalementNotification($signalement, 'status_changed'));
        }

        return to_route('back.signalements.index')->with('success', 'Le statut du signalement a été mis à jour.');
    }

    /**
     * @return array<string, mixed>
     */
    private function validateReport(Request $request): array
    {
        return $request->validate([
            'user_id' => [
                'required',
                'integer',
                Rule::exists('users', 'id')->where('role', Role::Habitant->value),
            ],
            'residence_id' => ['required', 'integer', Rule::exists('residences', 'id')],
            'categorie' => ['required', Rule::in(['fuite', 'panne_locale', 'personne_vulnerable', 'autre'])],
            'categorie_autre' => ['required_if:categorie,autre', 'nullable', 'string', 'max:100'],
            'urgence' => ['required', Rule::in(['normale', 'prioritaire', 'vitale'])],
            'description' => ['required', 'string', 'min:20', 'max:5000'],
            'statut' => ['required', Rule::in(['nouveau', 'en_traitement', 'resolu'])],
            'date_signalement' => ['required', 'date'],
            'photo' => ['nullable', 'image', 'max:5120'],
        ]);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function ensureNoDuplicate(array $data, ?Signalement $signalement = null): void
    {
        $duplicate = Signalement::query()
            ->where('user_id', $data['user_id'])
            ->where('residence_id', $data['residence_id'])
            ->where('categorie', $data['categorie'])
            ->whereDate('date_signalement', $data['date_signalement'])
            ->when($signalement, fn ($query) => $query->where('id', '!=', $signalement->id))
            ->exists();

        if ($duplicate) {
            throw \Illuminate\Validation\ValidationException::withMessages([
                'categorie' => 'Cet habitant a déjà un signalement de cette catégorie pour cette résidence à cette date.',
            ]);
        }
    }

    private function authorizeSignalement(Request $request, Signalement $signalement): void
    {
        // L'espace signalements du back-office donne accès aux dossiers de toutes les résidences.
    }

    private function ensureManagerHasResidence(Request $request): void
    {
        abort_if(
            $request->user()->isGestionnaire() && ! $request->user()->residence_id,
            403,
            'Votre compte gestionnaire doit être associé à une résidence.',
        );
    }

    private function accessibleSignalements(Request $request)
    {
        return Signalement::query();
    }

    private function accessibleResidences(Request $request)
    {
        return Residence::query()
            ->orderBy('nom')
            ->get(['id', 'nom']);
    }

    private function accessibleHabitants(Request $request)
    {
        return User::query()
            ->where('role', Role::Habitant->value)
            ->orderBy('name')
            ->get(['id', 'name', 'email']);
    }
}