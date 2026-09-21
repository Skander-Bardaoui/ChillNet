<?php

namespace App\Http\Middleware;

use App\Enums\Role;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureUserHasRole
{
    public function handle(Request $request, Closure $next, string ...$roles): Response
    {
        $user = $request->user();

        if (! $user || ! in_array($user->role, array_map(fn (string $r) => Role::from($r), $roles), true)) {
            abort(403, "Vous n'avez pas accès à cette section.");
        }

        return $next($request);
    }
}
