<?php

use App\Http\Controllers\Back\AlerteController as BackAlerteController;
use App\Http\Controllers\Back\CoupureController as BackCoupureController;
use App\Http\Controllers\Back\DashboardController as BackDashboardController;
use App\Http\Controllers\Back\QuartierController;
use App\Http\Controllers\Back\ResidenceController;
use App\Http\Controllers\Front\AlerteController as FrontAlerteController;
use App\Http\Controllers\Front\CoupureController as FrontCoupureController;
use App\Http\Controllers\Back\SignalementController as BackSignalementController;
use App\Http\Controllers\Front\DashboardController;
use App\Http\Controllers\Front\HomeController;
use App\Http\Controllers\Front\LieuController as FrontLieuController;
use App\Http\Controllers\Front\SignalementController as FrontSignalementController;
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
    // Module 1 : les alertes canicule VALIDÉES et actives du quartier de l'habitant.
    Route::get('/alertes', [FrontAlerteController::class, 'index'])->name('alertes.index');
    Route::get('/signalements', [FrontSignalementController::class, 'index'])->name('signalements.index');
    Route::post('/signalements', [FrontSignalementController::class, 'store'])->name('front.signalements.store');
    Route::get('/signalements/{signalement}/modifier', [FrontSignalementController::class, 'edit'])->name('front.signalements.edit');
    Route::patch('/signalements/{signalement}', [FrontSignalementController::class, 'update'])->name('front.signalements.update');
    Route::delete('/signalements/{signalement}', [FrontSignalementController::class, 'destroy'])->name('front.signalements.destroy');
    Route::get('/signalements/{signalement}/pdf', [FrontSignalementController::class, 'downloadPdf'])->name('front.signalements.pdf');
    Route::get('/equipements', fn () => view('front.equipements'))->name('equipements.index');

    // Module 2 : l'habitant signale une coupure en cours.
    Route::get('/coupures/signaler', [FrontCoupureController::class, 'create'])->name('coupures.create');
    Route::post('/coupures', [FrontCoupureController::class, 'store'])->name('coupures.store');
    // Module 2 : « je confirme » — crédibilise un signalement constaté aussi.
    Route::post('/coupures/{coupure}/confirmer', [FrontCoupureController::class, 'confirm'])->name('coupures.confirm');

    // Mes lieux : les endroits géolocalisés du foyer (Domicile, Travail…).
    Route::get('/mes-lieux', [FrontLieuController::class, 'index'])->name('lieux.index');
    Route::post('/mes-lieux', [FrontLieuController::class, 'store'])->name('lieux.store');
    Route::patch('/mes-lieux/{lieu}/principal', [FrontLieuController::class, 'principal'])->name('lieux.principal');
    Route::patch('/mes-lieux/{lieu}', [FrontLieuController::class, 'update'])->name('lieux.update');
    Route::delete('/mes-lieux/{lieu}', [FrontLieuController::class, 'destroy'])->name('lieux.destroy');
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

Route::get('/dashboard', [DashboardController::class, 'index'])
    ->middleware(['auth', 'verified'])->name('dashboard');

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
    Route::get('/', [BackDashboardController::class, 'index'])->name('dashboard');

    Route::resource('quartiers', QuartierController::class)
        ->except(['show'])
        ->middleware('role:admin');
    Route::resource('residences', ResidenceController::class)->except(['show']);

    // Module 2 : back office coupures (gestionnaire = sa zone, admin = tout).
    Route::resource('coupures', BackCoupureController::class)->except(['show']);

    // Module 1 : back office alertes canicule (CRUD + validation + IA).
    Route::post('/alertes/prefill', [BackAlerteController::class, 'prefill'])->name('alertes.prefill');
    Route::patch('/alertes/{alerte}/valider', [BackAlerteController::class, 'valider'])->name('alertes.valider');
    Route::resource('alertes', BackAlerteController::class)->except(['show']);

    /*
    |----------------------------------------------------------------------
    | Back office vitrine par module (TEMPLATE UNIQUEMENT).
    |----------------------------------------------------------------------
    */
    foreach (['conseils'] as $module) {
        Route::get("/{$module}", fn () => view("back.{$module}.index"))->name("{$module}.index");
        Route::get("/{$module}/creer", fn () => view("back.{$module}.create"))->name("{$module}.create");
        Route::get("/{$module}/{id}/modifier", fn (string $id) => view("back.{$module}.edit"))->name("{$module}.edit");
    }
    Route::get('/points-fraicheur', fn () => view('back.points.index'))->name('points.index');
    Route::get('/signalements', [BackSignalementController::class, 'index'])->name('signalements.index');
    Route::patch('/signalements/{signalement}', [BackSignalementController::class, 'update'])->name('signalements.update');
});

require __DIR__.'/auth.php';
