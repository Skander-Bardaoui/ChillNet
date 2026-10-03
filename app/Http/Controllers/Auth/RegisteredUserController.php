<?php

namespace App\Http\Controllers\Auth;

use App\Enums\LieuType;
use App\Enums\Role;
use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\RegisterRequest;
use App\Models\Quartier;
use App\Models\Residence;
use App\Models\User;
use Illuminate\Auth\Events\Registered;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

class RegisteredUserController extends Controller
{
    use IssuesJwtCookies;

    /**
     * Display the registration view.
     */
    public function create(): View
    {
        $quartiers = Quartier::with(['residences' => fn ($query) => $query->orderBy('nom')])
            ->orderBy('nom')
            ->get();

        // Sert à proposer d'emblée le bon mode : s'il n'existe aucune résidence
        // référencée, on invite le foyer à déclarer la sienne.
        $residencesDisponibles = $quartiers->sum(fn (Quartier $quartier): int => $quartier->residences->count());

        return view('auth.register', compact('quartiers', 'residencesDisponibles'));
    }

    /**
     * Handle an incoming registration request.
     */
    public function store(RegisterRequest $request): RedirectResponse
    {
        $role = Role::from($request->validated('role'));

        $user = User::create([
            'name' => $request->validated('name'),
            'email' => $request->validated('email'),
            'password' => Hash::make($request->validated('password')),
            'role' => $role,
            // Seul le gestionnaire est rattaché à une résidence ; l'habitant
            // décrit son foyer par un lieu géolocalisé (voir ci-dessous).
            'residence_id' => $role === Role::Gestionnaire
                ? $this->resolveResidence($request)?->id
                : null,
            'profil_vulnerabilites' => $request->validated('profil_vulnerabilites') ?? [],
        ]);

        if ($role === Role::Habitant) {
            $this->enregistrerLieu($user, $request);
        }

        event(new Registered($user));

        Auth::guard('web')->setUser($user);
        $request->session()->regenerate();

        $cookies = $this->issueAuthCookies($user);

        $response = redirect(route('dashboard', absolute: false));

        foreach ($cookies as $cookie) {
            $response->withCookie($cookie);
        }

        return $response;
    }

    /**
     * Crée le premier lieu de l'habitant à partir du point posé sur la carte.
     * Le quartier est déduit automatiquement (le plus proche) par le modèle.
     */
    protected function enregistrerLieu(User $user, RegisterRequest $request): void
    {
        $user->lieux()->create([
            'nom' => trim((string) ($request->validated('lieu_nom') ?: 'Domicile')),
            'type' => LieuType::Domicile,
            'adresse' => $request->validated('lieu_adresse'),
            'latitude' => $request->validated('latitude'),
            'longitude' => $request->validated('longitude'),
            'est_principal' => true,
        ]);
    }

    /**
     * Détermine la résidence du nouveau foyer selon le mode choisi.
     */
    protected function resolveResidence(RegisterRequest $request): ?Residence
    {
        return match ($request->validated('residence_mode')) {
            RegisterRequest::MODE_EXISTANTE => Residence::find($request->validated('residence_id')),
            RegisterRequest::MODE_NOUVELLE => $this->declareResidence($request),
            default => null,
        };
    }

    /**
     * Déclare la résidence du foyer (et son quartier si celui-ci est nouveau).
     *
     * `firstOrCreate` rend l'opération idempotente : si un autre foyer a déjà
     * déclaré la même résidence, on la réutilise au lieu de créer un doublon.
     */
    protected function declareResidence(RegisterRequest $request): Residence
    {
        $quartier = $request->validated('quartier_id')
            ? Quartier::findOrFail($request->validated('quartier_id'))
            : Quartier::firstOrCreate(
                [
                    'nom' => trim((string) $request->validated('nouveau_quartier_nom')),
                    'ville' => trim((string) $request->validated('nouveau_quartier_ville')),
                ],
                ['code_postal' => $request->validated('nouveau_quartier_code_postal')],
            );

        return Residence::firstOrCreate(
            [
                'nom' => trim((string) $request->validated('nouvelle_residence_nom')),
                'quartier_id' => $quartier->id,
            ],
            ['adresse' => $request->validated('nouvelle_residence_adresse')],
        );
    }
}
