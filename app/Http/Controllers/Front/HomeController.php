<?php

namespace App\Http\Controllers\Front;

use App\Http\Controllers\Controller;
use App\Models\Quartier;
use App\Models\Residence;
use Illuminate\Http\Request;

class HomeController extends Controller
{
    public function index()
    {
        $quartiers = Quartier::withCount('residences')->orderBy('nom')->get();
        $pointsFraicheur = Residence::pointFraicheur()
            ->with('quartier')
            ->orderBy('nom')
            ->get();

        // Landing page = welcome.blade.php (simple, visuelle, peu de texte).
        return view('welcome', compact('quartiers', 'pointsFraicheur'));
    }

    public function quartier(int $id)
    {
        $quartier = Quartier::with(['residences' => fn ($q) => $q->orderBy('nom')])
            ->findOrFail($id);

        return view('front.quartier', compact('quartier'));
    }
}
