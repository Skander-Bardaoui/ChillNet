<?php

use App\Http\Controllers\Back\CoupureController as BackCoupureController;
use App\Http\Controllers\Back\QuartierController;
use App\Http\Controllers\Back\ResidenceController;
use App\Http\Controllers\Front\CoupureController as FrontCoupureController;
use App\Http\Controllers\Front\HomeController;
use App\Http\Controllers\ProfileController;
use App\Models\Residence;
use App\Support\AuthCookie;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Front office — public + habitant
|--------------------------------------------------------------------------
*/
Route::get('/', [HomeController::class, 'index'])->name('home');
Route::get('/accueil', [HomeController::class, 'index'])->name('accueil');
Route::get('/quartiers/{id}', [HomeController::class, 'quartier'])->name('quartiers.show');
Route::get('/conseils', fn () => view('front.conseils'))->name('conseils');

/*
|--------------------------------------------------------------------------
| Pages vitrines par module (TEMPLATE UNIQUEMENT — aucun traitement backend :
| formulaires en action="#" et données statiques de démonstration).
|   Module 1 (Skander) : alertes canicule.
|   Module 2 (Imen)    : coupures de courant.
|   Module 3 (Dhia)    : points de fraîcheur + avis.
|   Module 4 (Ghazi)   : équipements sensibles (conseils : voir /conseils).
|   Module 5 (Rihab)   : signalements communautaires.
|--------------------------------------------------------------------------
*/
/*
|--------------------------------------------------------------------------
| Module 2 (Imen) : coupures de courant — RÉEL (base de données).
| Front public : carte + liste des coupures actives, filtre par zone.
| Signalement habitant : formulaire connecté (voir groupe habitant plus bas).
| Les autres modules restent en vitrine TEMPLATE UNIQUEMENT.
|--------------------------------------------------------------------------
*/
Route::get('/coupures', [FrontCoupureController::class, 'index'])->name('coupures.index');
Route::get('/points-fraicheur/{id}', fn (string $id) => view('front.point-show'))->name('points.show');
Route::get('/refuges', function () {
    $points = Residence::pointFraicheur()->with('quartier')->orderBy('nom')->get();

    return view('front.refuges', compact('points'));
})->name('refuges.index');

/*
 * Espace habitant (connecté) : réservé au rôle habitant.
 * Les managers (admin / gestionnaire) ont leur propre espace sur /admin :
 * ils sont redirigés depuis /dashboard et reçoivent 403 ici — pas logique
 * qu'ils consultent l'espace citoyen en tant que foyer.
 */
Route::middleware(['auth', 'role:habitant'])->group(function () {
    Route::get('/alertes', fn () => view('front.alertes'))->name('alertes.index');
    Route::get('/signalements', fn () => view('front.signalements'))->name('signalements.index');
    Route::get('/equipements', fn () => view('front.equipements'))->name('equipements.index');

    // Module 2 : l'habitant signale une coupure en cours.
    Route::get('/coupures/signaler', [FrontCoupureController::class, 'create'])->name('coupures.create');
    Route::post('/coupures', [FrontCoupureController::class, 'store'])->name('coupures.store');
    // Module 2 : « je confirme » — crédibilise un signalement constaté aussi.
    Route::post('/coupures/{coupure}/confirmer', [FrontCoupureController::class, 'confirm'])->name('coupures.confirm');
});

/*
|--------------------------------------------------------------------------
| Diagnostic auth — vérifiable dans Inspecteur > Réseau (les cookies étant
| httpOnly, ils ne sont jamais lisibles en JS : c'est la preuve à consulter
| avec Inspecteur > Application > Cookies).
|--------------------------------------------------------------------------
*/
Route::get('/auth/status', function (Request $request) {
    return response()->json([
        'authenticated' => auth()->check(),
        'user' => auth()->check() ? auth()->user()->only(['id', 'name', 'email']) : null,
        'cookies' => [
            'access_token' => $request->hasCookie(AuthCookie::ACCESS),
            'refresh_token' => $request->hasCookie(AuthCookie::REFRESH),
        ],
        'hint' => 'Les cookies httpOnly se vérifient dans DevTools > Application > Cookies, pas dans document.cookie ni localStorage.',
    ]);
})->name('auth.status');

Route::get('/dashboard', function () {
    $user = auth()->user();

    if ($user->isAdmin() || $user->isGestionnaire()) {
        return redirect()->route('back.dashboard');
    }

    return view('front.dashboard');
})->middleware(['auth', 'verified'])->name('dashboard');

Route::middleware('auth')->group(function () {
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');
});

/*
|--------------------------------------------------------------------------
| Back office — gestionnaire / admin
|--------------------------------------------------------------------------
*/
Route::prefix('admin')->name('back.')->middleware(['auth', 'role:admin,gestionnaire'])->group(function () {
    Route::get('/', function () {
        return view('back.dashboard');
    })->name('dashboard');

    Route::resource('quartiers', QuartierController::class)
        ->except(['show'])
        ->middleware('role:admin');
    Route::resource('residences', ResidenceController::class)->except(['show']);

    // Module 2 : back office coupures (gestionnaire = sa zone, admin = tout).
    Route::resource('coupures', BackCoupureController::class)->except(['show']);

    /*
    |----------------------------------------------------------------------
    | Back office vitrine par module (TEMPLATE UNIQUEMENT).
    |----------------------------------------------------------------------
    */
    foreach (['alertes', 'conseils'] as $module) {
        Route::get("/{$module}", fn () => view("back.{$module}.index"))->name("{$module}.index");
        Route::get("/{$module}/creer", fn () => view("back.{$module}.create"))->name("{$module}.create");
        Route::get("/{$module}/{id}/modifier", fn (string $id) => view("back.{$module}.edit"))->name("{$module}.edit");
    }
    Route::get('/points-fraicheur', fn () => view('back.points.index'))->name('points.index');
    Route::get('/signalements', fn () => view('back.signalements.index'))->name('signalements.index');
});

require __DIR__.'/auth.php';
