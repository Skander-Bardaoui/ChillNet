<?php

use App\Http\Controllers\Back\QuartierController;
use App\Http\Controllers\Back\ResidenceController;
use App\Http\Controllers\Front\HomeController;
use App\Http\Controllers\ProfileController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Front office — public + habitant
|--------------------------------------------------------------------------
*/
Route::get('/', [HomeController::class, 'index'])->name('home');
Route::get('/quartiers/{id}', [HomeController::class, 'quartier'])->name('quartiers.show');

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
});

require __DIR__.'/auth.php';
