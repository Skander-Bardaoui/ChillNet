<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\FaceLoginRequest;
use App\Models\User;
use App\Services\Face\FacePlusPlusClient;
use Illuminate\Contracts\Encryption\DecryptException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\View\View;

/**
 * Connexion par reconnaissance faciale (Face++).
 *
 * Le visage est comparé à l'image de référence enrôlée depuis le profil.
 * Toute erreur renvoie le même message générique : compte inconnu, visage
 * absent, confiance insuffisante ou API indisponible sont indiscernables.
 * Les images ne sont jamais journalisées.
 */
class FaceLoginController extends Controller
{
    use IssuesJwtCookies;

    public function __construct(private readonly FacePlusPlusClient $facePlusPlus) {}

    /**
     * Affiche la page de connexion par visage.
     */
    public function create(): View
    {
        return view('auth.face-login');
    }

    /**
     * Vérifie le visage puis ouvre la session (ou passe par la 2FA).
     */
    public function store(FaceLoginRequest $request): RedirectResponse
    {
        $request->ensureIsNotRateLimited();

        $user = User::query()->where('email', (string) $request->string('email'))->first();

        // Retour immédiat pour les comptes inconnus / non enrôlés : aucun appel
        // Face++ inutile et pas d'énumération par le message d'erreur.
        if ($user === null || ! $user->hasFaceEnrollment()) {
            return $this->refuse($request, 'compte_inconnu_ou_non_enrole', $user?->getKey());
        }

        try {
            $reference = $user->face_reference_image;
        } catch (DecryptException) {
            // Image de référence chiffrée avec une ancienne APP_KEY : le mot de
            // passe reste utilisable, l'utilisateur devra ré-enrôler son visage.
            return $this->refuse($request, 'reference_indechiffrable', $user->getKey());
        }

        if (! is_string($reference) || trim($reference) === '') {
            return $this->refuse($request, 'reference_absente', $user->getKey());
        }

        $probe = (string) $request->string('image');

        $faces = $this->facePlusPlus->detect($probe);

        if ($faces === null) {
            return $this->refuse($request, 'service_detect_indisponible', $user->getKey());
        }

        if (count($faces) !== 1) {
            return $this->refuse($request, 'visages_detectes:'.count($faces), $user->getKey());
        }

        $comparison = $this->facePlusPlus->compare($probe, $reference);

        if ($comparison === null) {
            return $this->refuse($request, 'service_compare_indisponible', $user->getKey());
        }

        $threshold = $this->effectiveThreshold($comparison['thresholds']);

        if ($comparison['confidence'] < $threshold) {
            return $this->refuse(
                $request,
                'confiance_insuffisante',
                $user->getKey(),
                $comparison['confidence'],
                $threshold,
            );
        }

        $request->clear();

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

        $cookies = $this->issueAuthCookies($user, $request->boolean('remember'));

        $response = redirect()->intended(route('dashboard', absolute: false));

        foreach ($cookies as $cookie) {
            $response->withCookie($cookie);
        }

        return $response;
    }

    /**
     * Seuil exigé : au moins le plancher configuré et le seuil FAR de Face++.
     *
     * @param  array<string, mixed>  $thresholds
     */
    private function effectiveThreshold(array $thresholds): float
    {
        $floor = (float) config('services.faceplusplus.threshold', 80.0);
        $far = (string) config('services.faceplusplus.far', '1e-5');

        $value = $thresholds[$far] ?? null;

        if ($value === null) {
            foreach ($thresholds as $key => $candidate) {
                if (is_numeric($key) && is_numeric($candidate) && abs((float) $key - (float) $far) < 1e-12) {
                    $value = $candidate;
                    break;
                }
            }
        }

        return max($floor, is_numeric($value) ? (float) $value : 0.0);
    }

    /**
     * Échec uniforme : même message pour l'utilisateur, raison précise dans
     * les journaux (sans image ni identifiant Face++).
     */
    private function refuse(
        FaceLoginRequest $request,
        string $reason,
        ?int $userId = null,
        ?float $confidence = null,
        ?float $threshold = null,
    ): RedirectResponse {
        $request->hit();

        Log::warning('Connexion par visage refusée', array_filter([
            'reason' => $reason,
            'user_id' => $userId,
            'confidence' => $confidence,
            'threshold' => $threshold,
        ], fn (mixed $value): bool => ! is_null($value)));

        return back()
            ->withErrors(['email' => trans('auth.face_failed')])
            ->onlyInput('email');
    }
}
