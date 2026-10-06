<?php

use App\Http\Controllers\Back\AlerteController as BackAlerteController;
use App\Http\Controllers\Back\ConseilController as BackConseilController;
use App\Http\Controllers\Back\CoupureController as BackCoupureController;
use App\Http\Controllers\Back\DashboardController as BackDashboardController;
use App\Http\Controllers\Back\EquipementTypeController as BackEquipementTypeController;
use App\Http\Controllers\Back\QuartierController;
use App\Http\Controllers\Back\ResidenceController;
use App\Http\Controllers\Back\SignalementController as BackSignalementController;
use App\Http\Controllers\Front\AlerteController as FrontAlerteController;
use App\Http\Controllers\Front\ConseilController as FrontConseilController;
use App\Http\Controllers\Front\CoupureController as FrontCoupureController;
use App\Http\Controllers\Front\DashboardController;
use App\Http\Controllers\Front\EquipementController as FrontEquipementController;
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

/*
|--------------------------------------------------------------------------
| Module 2 (Imen) : coupures de courant — RÉEL (base de données).
|--------------------------------------------------------------------------
*/
Route::get('/coupures', [FrontCoupureController::class, 'index'])->name('coupures.index');
Route::get('/points-fraicheur/{id}', fn (string $id) => view('front.point-show'))->name('points.show');
Route::get('/refuges', function () {
    $points = Residence::pointFraicheur()->with('quartier')->orderBy('nom')->get();

    return view('front.refuges', compact('points'));
})->name('refuges.index');

/*
|--------------------------------------------------------------------------
| Espace habitant (connecté) — rôle habitant uniquement.
| Les managers (admin / gestionnaire) ont leur espace sur /admin.
|--------------------------------------------------------------------------
*/
Route::middleware(['auth', 'role:habitant'])->group(function () {
    // Module 1 — alertes canicule validées du quartier de l'habitant.
    Route::get('/alertes', [FrontAlerteController::class, 'index'])->name('alertes.index');

    // Module 2 — signalement coupure + confirmation.
    Route::get('/coupures/signaler', [FrontCoupureController::class, 'create'])->name('coupures.create');
    Route::post('/coupures', [FrontCoupureController::class, 'store'])->name('coupures.store');
    Route::post('/coupures/{coupure}/confirmer', [FrontCoupureController::class, 'confirm'])->name('coupures.confirm');

    // Module 4 — équipements sensibles déclarés par l'habitant.
    Route::get('/equipements', [FrontEquipementController::class, 'index'])->name('equipements.index');
    Route::post('/equipements', [FrontEquipementController::class, 'store'])->name('equipements.store');
    Route::get('/equipements/{equipement}/modifier', [FrontEquipementController::class, 'edit'])->name('equipements.edit');
    Route::patch('/equipements/{equipement}', [FrontEquipementController::class, 'update'])->name('equipements.update');
    Route::delete('/equipements/{equipement}', [FrontEquipementController::class, 'destroy'])->name('equipements.destroy');

    // Module 4 — conseils personnalisés (IA + DB).
    Route::get('/conseils', [FrontConseilController::class, 'index'])->name('conseils');

    // Module 5 — signalements communautaires.
    Route::get('/signalements', [FrontSignalementController::class, 'index'])->name('signalements.index');
    Route::post('/signalements', [FrontSignalementController::class, 'store'])->name('front.signalements.store');
    Route::get('/signalements/{signalement}/modifier', [FrontSignalementController::class, 'edit'])->name('front.signalements.edit');
    Route::patch('/signalements/{signalement}', [FrontSignalementController::class, 'update'])->name('front.signalements.update');
    Route::delete('/signalements/{signalement}', [FrontSignalementController::class, 'destroy'])->name('front.signalements.destroy');
    Route::get('/signalements/{signalement}/pdf', [FrontSignalementController::class, 'downloadPdf'])->name('front.signalements.pdf');

    // Mes lieux — points géolocalisés du foyer (Domicile, Travail…).
    Route::get('/mes-lieux', [FrontLieuController::class, 'index'])->name('lieux.index');
    Route::post('/mes-lieux', [FrontLieuController::class, 'store'])->name('lieux.store');
    Route::patch('/mes-lieux/{lieu}/principal', [FrontLieuController::class, 'principal'])->name('lieux.principal');
    Route::patch('/mes-lieux/{lieu}', [FrontLieuController::class, 'update'])->name('lieux.update');
    Route::delete('/mes-lieux/{lieu}', [FrontLieuController::class, 'destroy'])->name('lieux.destroy');
});

/*
|--------------------------------------------------------------------------
| Diagnostic auth
|--------------------------------------------------------------------------
*/
Route::get('/auth/status', function (Request $request) {
    return response()->json([
        'authenticated' => auth()->check(),
        'user'          => auth()->check() ? auth()->user()->only(['id', 'name', 'email']) : null,
        'cookies'       => [
            'access_token'  => $request->hasCookie(AuthCookie::ACCESS),
            'refresh_token' => $request->hasCookie(AuthCookie::REFRESH),
        ],
        'hint' => 'Les cookies httpOnly se vérifient dans DevTools > Application > Cookies.',
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
| Back office — gestionnaire / admin  (préfixe /admin)
|--------------------------------------------------------------------------
*/
Route::prefix('admin')->name('back.')->middleware(['auth', 'role:admin,gestionnaire'])->group(function () {
    Route::get('/', [BackDashboardController::class, 'index'])->name('dashboard');

    // Quartiers & résidences (admin uniquement pour les quartiers).
    Route::resource('quartiers', QuartierController::class)
        ->except(['show'])
        ->middleware('role:admin');
    Route::resource('residences', ResidenceController::class)->except(['show']);

    // Module 2 - coupures (gestionnaire = sa zone, admin = tout).
    Route::resource('coupures', BackCoupureController::class);

    // Module 1 — alertes canicule (CRUD + validation + IA).
    Route::post('/alertes/prefill', [BackAlerteController::class, 'prefill'])->name('alertes.prefill');
    Route::patch('/alertes/{alerte}/valider', [BackAlerteController::class, 'valider'])->name('alertes.valider');
    Route::resource('alertes', BackAlerteController::class);

    // Module 4 — conseils (admin + gestionnaire).
    Route::resource('conseils', BackConseilController::class)->except(['show']);

    // Module 4 — types d'équipements (admin uniquement).
    Route::resource('equipement-types', BackEquipementTypeController::class)
        ->except(['show'])
        ->middleware('role:admin');

    // Module 5 — signalements.
    Route::get('/signalements', [BackSignalementController::class, 'index'])->name('signalements.index');
    Route::patch('/signalements/{signalement}', [BackSignalementController::class, 'update'])->name('signalements.update');

    // Points de fraîcheur — vitrine statique.
    Route::get('/points-fraicheur', fn () => view('back.points.index'))->name('points.index');
});

require __DIR__.'/auth.php';
