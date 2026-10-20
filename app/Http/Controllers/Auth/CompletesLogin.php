<?php

namespace App\Http\Controllers\Auth;

use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

/**
 * Fin de connexion commune aux deux parcours (mot de passe et visage).
 *
 * Avec la 2FA activée, la session réelle n'est pas ouverte : l'utilisateur est
 * renvoyé vers le défi du second facteur. Sinon, l'utilisateur est posé sur le
 * guard `web` et la paire de cookies JWT est attachée à la redirection.
 */
trait CompletesLogin
{
    protected function completeLogin(Request $request, User $user): RedirectResponse
    {
        if ($user->hasEnabledTwoFactorAuthentication()) {
            $request->session()->put([
                'login.id' => $user->getKey(),
                'login.remember' => $request->boolean('remember'),
            ]);

            $request->session()->regenerate();

            return redirect()->route('two-factor.challenge');
        }

        Auth::guard('web')->setUser($user);
        $request->session()->regenerate();

        $response = redirect()->intended(route('dashboard', absolute: false));

        foreach ($this->issueAuthCookies($user, $request->boolean('remember')) as $cookie) {
            $response->withCookie($cookie);
        }

        return $response;
    }
}
