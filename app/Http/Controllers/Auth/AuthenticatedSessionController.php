<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\LoginRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

class AuthenticatedSessionController extends Controller
{
    use CompletesLogin;
    use IssuesJwtCookies;

    /**
     * Display the login view.
     */
    public function create(): View
    {
        return view('auth.login');
    }

    /**
     * Handle an incoming authentication request.
     */
    public function store(LoginRequest $request): RedirectResponse
    {
        return $this->completeLogin($request, $request->authenticate());
    }

    /**
     * Destroy an authenticated session.
     */
    public function destroy(Request $request): RedirectResponse
    {
        // Révoque les deux jetons (access + refresh) portés par les cookies.
        $cookies = $this->clearAuthCookies();

        // Réinitialise l'utilisateur en mémoire sur le guard JWT.
        Auth::guard('web')->logout();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        $response = redirect('/');

        foreach ($cookies as $cookie) {
            $response->withCookie($cookie);
        }

        return $response;
    }
}
