<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Support\Totp;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

/**
 * Étape « second facteur » du login : un utilisateur au mot de passe correct mais
 * dont la 2FA est active est mis en attente (session `login.id`) jusqu'à ce qu'il
 * fournisse un code TOTP ou un code de récupération valide.
 */
class TwoFactorChallengeController extends Controller
{
    use IssuesJwtCookies;

    /**
     * Affiche l'écran de saisie du second facteur.
     */
    public function create(Request $request): View|RedirectResponse
    {
        if (! $request->session()->has('login.id')) {
            return redirect()->route('login');
        }

        $utilisateur = User::find($request->session()->get('login.id'));

        return view('auth.two-factor-challenge', [
            'codesRestants' => $utilisateur ? count($utilisateur->recoveryCodes()) : 0,
        ]);
    }

    /**
     * Valide le second facteur et connecte réellement l'utilisateur.
     */
    public function store(Request $request): RedirectResponse
    {
        $utilisateur = $this->utilisateurEnAttente($request);

        $this->ensureIsNotRateLimited($request);

        $code = $request->string('code')->toString();
        $codeRecuperation = $request->string('recovery_code')->toString();

        $valide = $code !== ''
            ? Totp::verify((string) $utilisateur->two_factor_secret, $code)
            : $this->consommerCodeRecuperation($utilisateur, $codeRecuperation);

        if (! $valide) {
            RateLimiter::hit($this->throttleKey($request));

            throw ValidationException::withMessages([
                'code' => 'Code de vérification invalide.',
            ]);
        }

        RateLimiter::clear($this->throttleKey($request));

        $remember = (bool) $request->session()->pull('login.remember', false);
        $request->session()->forget('login.id');

        Auth::guard('web')->setUser($utilisateur);
        $request->session()->regenerate();

        $cookies = $this->issueAuthCookies($utilisateur, $remember);

        $response = redirect()->intended(route('dashboard', absolute: false));

        foreach ($cookies as $cookie) {
            $response->withCookie($cookie);
        }

        return $response;
    }

    /**
     * Récupère l'utilisateur mis en attente par AuthenticatedSessionController.
     */
    private function utilisateurEnAttente(Request $request): User
    {
        $id = $request->session()->get('login.id');

        if (! $id) {
            throw ValidationException::withMessages([
                'code' => 'Votre session a expiré. Veuillez vous reconnecter.',
            ]);
        }

        $utilisateur = User::find($id);

        if (! $utilisateur || ! $utilisateur->two_factor_secret) {
            $request->session()->forget('login.id');
            throw ValidationException::withMessages([
                'code' => 'Votre session a expiré. Veuillez vous reconnecter.',
            ]);
        }

        return $utilisateur;
    }

    /**
     * Vérifie et consomme (usage unique) un code de récupération.
     */
    private function consommerCodeRecuperation(User $utilisateur, string $code): bool
    {
        $code = strtoupper(trim($code));

        if ($code === '') {
            return false;
        }

        $codes = $utilisateur->recoveryCodes();
        $restants = [];

        foreach ($codes as $candidat) {
            if (hash_equals(strtoupper($candidat), $code)) {
                continue; // consommé : on ne le réinsère pas
            }

            $restants[] = $candidat;
        }

        if (count($restants) === count($codes)) {
            return false; // aucun code ne correspond
        }

        $utilisateur->forceFill(['two_factor_recovery_codes' => $restants])->save();

        return true;
    }

    private function ensureIsNotRateLimited(Request $request): void
    {
        if (! RateLimiter::tooManyAttempts($this->throttleKey($request), 5)) {
            return;
        }

        $seconds = RateLimiter::availableIn($this->throttleKey($request));

        throw ValidationException::withMessages([
            'code' => trans('auth.throttle', [
                'seconds' => $seconds,
                'minutes' => ceil($seconds / 60),
            ]),
        ]);
    }

    private function throttleKey(Request $request): string
    {
        return 'two-factor|'.$request->session()->get('login.id', $request->ip());
    }
}