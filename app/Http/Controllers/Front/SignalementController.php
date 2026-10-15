<?php

namespace App\Http\Controllers\Front;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreSignalementRequest;
use App\Models\Residence;
use App\Models\Signalement;
use App\Notifications\SignalementNotification;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;

class SignalementController extends Controller
{
    public function index(): View
    {
        $signalements = Signalement::with(['residence', 'residence.quartier'])
            ->where('user_id', auth()->id())
            ->latest()
            ->get();
        $notifications = auth()->user()->notifications()->latest()->get();
        $unreadNotificationsCount = auth()->user()->unreadNotifications()->count();
        $residences = Residence::with('quartier')->orderBy('nom')->get();

        return view('front.signalements', compact('signalements', 'notifications', 'unreadNotificationsCount', 'residences'));
    }

    public function store(StoreSignalementRequest $request): RedirectResponse
    {
        $user = $request->user();
        $today = now()->toDateString();

        $data = $request->validated();
        $data['user_id'] = $user->id;
        $data['date_signalement'] = $today;

        if (Signalement::where('user_id', $user->id)
            ->where('residence_id', $data['residence_id'])
            ->where('categorie', $data['categorie'])
            ->whereDate('date_signalement', $today)
            ->exists()) {
            return back()->withInput()->withErrors([
                'signalement' => 'Vous avez déjà envoyé un signalement de cette catégorie aujourd\'hui pour cette résidence.',
            ]);
        }

        if ($data['categorie'] !== 'autre') {
            $data['categorie_autre'] = null;
        }
        $data['photo_path'] = $request->hasFile('photo')
            ? $request->file('photo')->store('signalements', 'public')
            : null;
        unset($data['photo']);

        $signalement = Signalement::create($data);
        $user->notify(new SignalementNotification($signalement, 'created'));

        return to_route('signalements.index')->with('success', 'Votre signalement a été envoyé.');
    }

    public function edit(Signalement $signalement): View
    {
        abort_unless($signalement->user_id === auth()->id(), 403);

        return view('front.signalements-edit', compact('signalement'));
    }

    public function update(StoreSignalementRequest $request, Signalement $signalement): RedirectResponse
    {
        abort_unless($signalement->user_id === auth()->id(), 403);

        $data = $request->validated();
        $data['categorie_autre'] = $data['categorie'] === 'autre' ? ($data['categorie_autre'] ?? null) : null;

        if ($request->hasFile('photo')) {
            if ($signalement->photo_path) {
                Storage::disk('public')->delete($signalement->photo_path);
            }
            $data['photo_path'] = $request->file('photo')->store('signalements', 'public');
        }

        unset($data['photo']);
        $signalement->update($data);

        return to_route('signalements.index')->with('success', 'Votre signalement a été modifié.');
    }

    public function destroy(Signalement $signalement): RedirectResponse
    {
        abort_unless($signalement->user_id === auth()->id(), 403);

        if ($signalement->photo_path) {
            Storage::disk('public')->delete($signalement->photo_path);
        }
        $signalement->delete();

        return to_route('signalements.index')->with('success', 'Votre signalement a été supprimé.');
    }

    public function downloadPdf(Signalement $signalement)
    {
        abort_unless($signalement->user_id === auth()->id(), 403);

        $signalement->load(['habitant', 'residence']);

        return Pdf::loadView('front.signalement-pdf', compact('signalement'))
            ->download('signalement-'.$signalement->id.'.pdf');
    }
}