<?php

namespace App\Http\Controllers\Front;

use App\Enums\LieuType;
use App\Enums\ProfilVulnerabilite;
use App\Http\Controllers\Controller;
use App\Http\Requests\StoreLieuRequest;
use App\Models\Lieu;
use App\Services\VigilanceAiService;
use App\Services\WeatherService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

/**
 * « Mes lieux » — CRUD des lieux personnels de l'habitant.
 *
 * Chaque lieu est un point géolocalisé (Domicile, Travail…) que le foyer gère
 * lui-même. Toutes les actions sont strictement scopées à l'utilisateur
 * connecté : un lieu d'un autre foyer renvoie 403. Le quartier n'apparaît
 * jamais ici — il est déduit automatiquement par le modèle `Lieu`.
 */
class LieuController extends Controller
{
    public function __construct(
        private WeatherService $meteo,
        private VigilanceAiService $ia,
    ) {}

    public function index(Request $request): View
    {
        $user = $request->user();

        $lieux = $user->lieux()
            ->orderByDesc('est_principal')
            ->orderBy('nom')
            ->get();

        // Repère visuel : le principal, sinon le premier déclaré.
        $principal = $lieux->firstWhere('est_principal', true) ?? $lieux->first();

        // Météo locale + conseil IA pour chaque lieu (cache court : la page
        // affiche plusieurs lieux et le conseil suit le profil du foyer).
        $profils = $user->profilsVulnerabilite();
        $apercus = $lieux->mapWithKeys(fn (Lieu $lieu): array => [
            $lieu->id => $this->apercu($lieu, $profils),
        ])->all();

        $lieuxJson = $lieux->map(fn (Lieu $lieu): array => [
            'id' => $lieu->id,
            'nom' => $lieu->nom,
            'type' => $lieu->type->value,
            'typeLabel' => $lieu->type->label(),
            'icone' => $lieu->type->icone(),
            'adresse' => $lieu->adresse,
            'lat' => (float) $lieu->latitude,
            'lng' => (float) $lieu->longitude,
            'temp' => isset($apercus[$lieu->id]['temperature'])
                ? round((float) $apercus[$lieu->id]['temperature'], 1)
                : null,
            'principal' => (bool) $lieu->est_principal,
            'updateUrl' => route('lieux.update', $lieu),
            'deleteUrl' => route('lieux.destroy', $lieu),
            'principalUrl' => route('lieux.principal', $lieu),
        ])->values();

        $types = LieuType::cases();

        $config = [
            'lieux' => $lieuxJson,
            'form' => [
                'nom' => old('nom', ''),
                'type' => old('type', LieuType::Domicile->value),
                'adresse' => old('adresse', ''),
                'latitude' => old('latitude', ''),
                'longitude' => old('longitude', ''),
            ],
            'urls' => [
                'store' => route('lieux.store'),
            ],
            'defaut' => [36.8065, 10.1815],
        ];

        return view('front.lieux.index', compact('lieux', 'lieuxJson', 'principal', 'types', 'config', 'apercus'));
    }

    /**
     * Aperçu météo + conseil IA d'un lieu : niveau canicule déterministe
     * (seuils) et message rédigé par le LLM, replié sur un conseil de secours
     * si l'API est indisponible. `null` si le lieu n'a pas de point exploitable.
     *
     * @param  array<int, ProfilVulnerabilite>  $profils
     * @return array<string, mixed>|null
     */
    private function apercu(Lieu $lieu, array $profils): ?array
    {
        if (! $lieu->hasCoordinates()) {
            return null;
        }

        $meteo = $this->meteo->actuelCache((float) $lieu->latitude, (float) $lieu->longitude);

        if ($meteo === null) {
            return null;
        }

        $historique = $lieu->quartier_id !== null
            ? $this->ia->historiquePourQuartier($lieu->quartier_id)
            : [];

        $analyse = $this->ia->analyse($meteo->temperature, $meteo->humidite, $historique);
        $niveau = $analyse['niveau'];

        $profilKey = implode('-', array_map(fn ($profil): string => $profil->value, $profils)) ?: 'standard';
        $temperatureArrondie = (int) round($meteo->temperature);

        // Cache 30 min : au plus un appel LLM par lieu/niveau/profil/fenêtre.
        $conseil = Cache::remember(
            "lieu:{$lieu->id}:conseil:{$niveau->value}:{$temperatureArrondie}:profil:{$profilKey}",
            now()->addMinutes(30),
            fn (): string => $this->ia->messagePourContexte([
                'titre' => 'Conseil local',
                'niveau' => $niveau->value,
                'niveau_label' => $niveau->label(),
                'quartiers' => [$lieu->nom],
                'temperature_actuelle' => $meteo->temperature,
                'temperature_ressentie' => $meteo->ressentie,
                'humidite' => $meteo->humidite,
                'raison' => $analyse['raison'],
            ], $profils),
        );

        return [
            'temperature' => $meteo->temperature,
            'ressentie' => $meteo->ressentie,
            'humidite' => $meteo->humidite,
            'vent' => $meteo->vent,
            'condition' => $meteo->condition,
            'ville' => $meteo->ville,
            'niveau' => $niveau->value,
            'niveauLabel' => $niveau->label(),
            'badgeClasses' => $niveau->badgeClasses(),
            'couleur' => $niveau->couleurHex(),
            'icone' => $niveau->icone(),
            'conseil' => $conseil,
        ];
    }

    public function store(StoreLieuRequest $request): RedirectResponse
    {
        $user = $request->user();

        $user->lieux()->create($request->validated() + [
            // Le tout premier lieu devient d'office le principal.
            'est_principal' => $user->lieux()->count() === 0,
        ]);

        return to_route('lieux.index')->with('success', 'Lieu ajouté à votre foyer.');
    }

    public function update(StoreLieuRequest $request, Lieu $lieu): RedirectResponse
    {
        $this->autoriser($request, $lieu);

        // Le quartier est ré-évalué automatiquement si le point a bougé.
        $lieu->update($request->validated());

        return to_route('lieux.index')->with('success', 'Lieu mis à jour.');
    }

    public function destroy(Request $request, Lieu $lieu): RedirectResponse
    {
        $this->autoriser($request, $lieu);

        $user = $request->user();

        // Toujours conserver au moins un lieu : il alimente le tableau de bord
        // et les alertes géolocalisées du foyer.
        if ($user->lieux()->count() <= 1) {
            return back()->with('error', 'Vous devez conserver au moins un lieu.');
        }

        DB::transaction(function () use ($user, $lieu): void {
            $etaitPrincipal = $lieu->est_principal;
            $lieu->delete();

            if ($etaitPrincipal) {
                $user->lieux()->orderBy('id')->first()?->update(['est_principal' => true]);
            }
        });

        return to_route('lieux.index')->with('success', 'Lieu supprimé.');
    }

    public function principal(Request $request, Lieu $lieu): RedirectResponse
    {
        $this->autoriser($request, $lieu);

        $user = $request->user();

        // Un seul lieu principal à la fois.
        DB::transaction(function () use ($user, $lieu): void {
            $user->lieux()->update(['est_principal' => false]);
            $lieu->update(['est_principal' => true]);
        });

        return to_route('lieux.index')->with('success', 'Lieu principal mis à jour.');
    }

    /**
     * Un habitant ne peut agir que sur ses propres lieux.
     */
    private function autoriser(Request $request, Lieu $lieu): void
    {
        abort_unless((int) $lieu->user_id === (int) $request->user()->id, 403);
    }
}
