<?php

namespace App\Http\Controllers\Auth;

use App\Enums\Role;
use App\Http\Controllers\Controller;
use App\Models\QuartierRecord;
use App\Models\User;
use Illuminate\Auth\Events\Registered;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class RegisteredUserController extends Controller
{
    use IssuesJwtCookies;

    /**
     * Display the registration view.
     */
    public function create(): View
    {
        $quartiers = QuartierRecord::with(['residences' => fn ($q) => $q->orderBy('nom')])
            ->orderBy('nom')
            ->get();

        return view('auth.register', compact('quartiers'));
    }

    /**
     * Handle an incoming registration request.
     *
     * @throws ValidationException
     */
    public function store(Request $request): RedirectResponse
    {
        $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'lowercase', 'email', 'max:255', 'unique:'.User::class],
            'password' => ['required', 'confirmed', Rules\Password::defaults()],
            'residence_id' => ['nullable', 'integer', 'exists:residences,id'],
        ]);

        $user = User::create([
            'name' => $request->name,
            'email' => $request->email,
            'password' => Hash::make($request->password),
            'role' => Role::Habitant,
            'residence_id' => $request->input('residence_id'),
        ]);

        event(new Registered($user));

        $this->issueAuthCookies($user);

        return redirect(route('home', absolute: false));
    }
}
