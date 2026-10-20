<?php

namespace App\Http\Controllers\Front;

use App\Enums\StatutCoupure;
use App\Http\Controllers\Controller;
use App\Models\Alerte;
use App\Models\Coupure;
use App\Models\PointFraicheur;
use App\Models\Quartier;
use App\Models\Residence;
use Illuminate\Database\Eloquent\Builder;

class HomeController extends Controller
{
    public function index()
    {
        $quartiers = Quartier::withCount('residences')->orderBy('nom')->get();
        $pointsFraicheur = Residence::pointFraicheur()
            ->with('quartier')
            ->orderBy('nom')
            ->get();

        // Landing page = welcome.blade.php (simple, visuelle, peu de texte).
        return view('welcome', [
            'quartiers' => $quartiers,
            'pointsFraicheur' => $pointsFraicheur,
            'alerteActive' => $this->alertePrioritaire(Alerte::query()->actives()),
        ]);
    }

    public function quartier(int $id)
    {
        $quartier = Quartier::with(['residences' => fn ($q) => $q->orderBy('nom')])
            ->findOrFail($id);

        $points = PointFraicheur::query()
            ->valides()
            ->where('quartier_id', $quartier->id)
            ->withCount('avis')
            ->orderBy('nom')
            ->get();

        $coupures = Coupure::query()
            ->where('quartier_id', $quartier->id)
            ->whereIn('statut', [StatutCoupure::EnCours->value, StatutCoupure::Prevue->value])
            ->orderBy('debut')
            ->get();

        $alerte = $this->alertePrioritaire(
            Alerte::query()->actives()->whereHas('quartiers', fn ($q) => $q->where('quartiers.id', $quartier->id))
        );

        $marqueurs = $points
            ->toBase()
            ->map(fn (PointFraicheur $point): array => [
                'lat' => (float) $point->latitude,
                'lng' => (float) $point->longitude,
                'nom' => $point->nom,
                'type' => $point->type->label(),
                'couleur' => $point->type->couleurHex(),
                'lien' => route('points.show', $point),
            ])
            ->merge($quartier->residences
                ->toBase()
                ->filter(fn (Residence $r): bool => $r->latitude !== null && $r->longitude !== null)
                ->map(fn (Residence $r): array => [
                    'lat' => (float) $r->latitude,
                    'lng' => (float) $r->longitude,
                    'nom' => $r->nom,
                    'type' => $r->salle_climatisee ? 'Résidence · salle climatisée' : 'Résidence',
                    'couleur' => '#1b77ba',
                    'lien' => '#residences',
                ]))
            ->values()
            ->all();

        return view('front.quartier', [
            'quartier' => $quartier,
            'points' => $points,
            'coupures' => $coupures,
            'alerte' => $alerte,
            'marqueurs' => $marqueurs,
            'origine' => $quartier->hasCoordinates()
                ? ['lat' => $quartier->latitude, 'lng' => $quartier->longitude]
                : null,
        ]);
    }

    /**
     * Alerte validée la plus grave parmi une requête d'alertes actives.
     */
    private function alertePrioritaire(Builder $query): ?Alerte
    {
        return $query->where('validee', true)->get()
            ->sortByDesc(fn (Alerte $alerte): int => match ($alerte->niveau?->value) {
                'rouge' => 3,
                'orange' => 2,
                'jaune' => 1,
                default => 0,
            })
            ->first();
    }
}
