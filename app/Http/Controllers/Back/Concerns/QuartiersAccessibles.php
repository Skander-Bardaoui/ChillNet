<?php

namespace App\Http\Controllers\Back\Concerns;

use App\Models\Quartier;
use Illuminate\Database\Eloquent\Collection;

trait QuartiersAccessibles
{
    /**
     * Quartiers proposés dans les formulaires : le gestionnaire ne voit que le
     * sien, l'admin les voit tous.
     *
     * @return Collection<int, Quartier>
     */
    protected function quartiersAccessibles(): Collection
    {
        $user = request()->user();

        if ($user && $user->isGestionnaire()) {
            return Quartier::whereKey($user->residence?->quartier_id)->orderBy('nom')->get();
        }

        return Quartier::orderBy('nom')->get();
    }
}
