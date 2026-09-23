<?php

namespace App\Http\Middleware;

use App\Enums\Role;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Restreint une route à un ou plusieurs rôles : `->middleware('role:admin,gestionnaire')`.
 *
 * Un rôle inconnu déclaré dans la route est ignoré (et non plus fatal) :
 * si aucun rôle exploitable n'est fourni, la route est refusée en 403.
 */
class EnsureUserHasRole
{
    public function handle(Request $request, Closure $next, string ...$roles): Response
    {
        $required = array_filter(array_map(
            static fn (string $role): ?Role => Role::tryFrom($role),
            $roles,
        ));

        $user = $request->user();

        if (! $user || ! $user->role || ! in_array($user->role, $required, true)) {
            abort(403, "Vous n'avez pas accès à cette section.");
        }

        return $next($request);
    }
}
