<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Support\Totp;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * Gestion par l'utilisateur connecté de sa double authentification TOTP :
 * activation, confirmation (premier code), désactivation, codes de récupération.
 */
class TwoFactorAuthenticationController extends Controller
{
    /**
     * Active la 2FA : génère un secret et des codes de récupération. La 2FA n'est
     * réellement active qu'après confirmation d'un premier code TOTP valide.
     */
    public function enable(Request $request): RedirectResponse
    {
        $user = $request->user();

        $user->forceFill([
            'two_factor_secret' => Totp::generateSecret(),
            'two_factor_recovery_codes' => $this->genererCodesRecuperation(),
            'two_factor_confirmed_at' => null,
        ])->save();

        return back()->with('status', 'two-factor-enabled');
    }

    /**
     * Confirme l'activation en validant un premier code issu de l'application
     * d'authentification.
     */
    public function confirm(Request $request): RedirectResponse
    {
        $request->validate([
            'code' => ['required', 'string'],
        ]);

        $user = $request->user();

        if (! $user->two_factor_secret) {
            throw ValidationException::withMessages([
                'code' => 'Activez d\'abord la double authentification.',
            ]);
        }

        if (! Totp::verify($user->two_factor_secret, $request->string('code')->toString())) {
            throw ValidationException::withMessages([
                'code' => 'Ce code est invalide. Vérifiez l\'heure de votre appareil et réessayez.',
            ]);
        }

        $user->forceFill(['two_factor_confirmed_at' => now()])->save();

        return back()->with('status', 'two-factor-confirmed');
    }

    /**
     * Désactive la 2FA et efface le secret ainsi que les codes de récupération.
     */
    public function disable(Request $request): RedirectResponse
    {
        $request->user()->forceFill([
            'two_factor_secret' => null,
            'two_factor_recovery_codes' => null,
            'two_factor_confirmed_at' => null,
        ])->save();

        return back()->with('status', 'two-factor-disabled');
    }

    /**
     * Renvoie les codes de récupération courants (affichage JSON).
     */
    public function recoveryCodes(Request $request): JsonResponse
    {
        return response()->json([
            'recovery_codes' => $request->user()->recoveryCodes(),
        ]);
    }

    /**
     * Régénère un jeu complet de codes de récupération.
     */
    public function regenerateRecoveryCodes(Request $request): RedirectResponse
    {
        $request->user()->forceFill([
            'two_factor_recovery_codes' => $this->genererCodesRecuperation(),
        ])->save();

        return back()->with('status', 'two-factor-recovery-codes-regenerated');
    }

    /**
     * Huit codes à usage unique au format `XXXXX-XXXXX`.
     *
     * @return array<int, string>
     */
    private function genererCodesRecuperation(): array
    {
        return collect(range(1, 8))
            ->map(fn (): string => Str::upper(Str::random(5).'-'.Str::random(5)))
            ->all();
    }
}