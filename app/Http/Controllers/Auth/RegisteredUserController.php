<?php

namespace App\Http\Controllers\Auth;

use App\Enums\Role;
use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\RegisterRequest;
use App\Models\Quartier;
use App\Models\Residence;
use App\Models\User;
use Illuminate\Auth\Events\Registered;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Hash;
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
            'residence_id' => $this->resolveResidence($request)?->id,
        ]);

        event(new Registered($user));

        $request->session()->regenerate();

        $cookies = $this->issueAuthCookies($user);

        $response = redirect(route('dashboard', absolute: false));

        foreach ($cookies as $cookie) {
            $response->withCookie($cookie);
        }

        return $response;
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
