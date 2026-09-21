<?php

namespace App\Http\Controllers\Front;

use App\Http\Controllers\Controller;
use App\Models\QuartierRecord;
use App\Models\ResidenceRecord;
use Illuminate\Http\Request;

class HomeController extends Controller
{
    public function index()
    {
        $quartiers = QuartierRecord::withCount('residences')->orderBy('nom')->get();
        $pointsFraicheur = ResidenceRecord::with('quartier')
            ->where('point_fraicheur', true)
            ->orderBy('nom')
            ->get();

        return view('front.home', compact('quartiers', 'pointsFraicheur'));
    }

    public function quartier(int $id)
    {
        $quartier = QuartierRecord::with(['residences' => fn ($q) => $q->orderBy('nom')])
            ->findOrFail($id);

        return view('front.quartier', compact('quartier'));
    }
}
