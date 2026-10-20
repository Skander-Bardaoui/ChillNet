<?php

namespace App\Providers;

use App\Models\Alerte;
use App\Models\PointFraicheur;
use Illuminate\Support\Facades\View;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        $alerteActive = fn (): ?Alerte => Alerte::query()
            ->actives()
            ->where('validee', true)
            ->get()
            ->sortByDesc(fn (Alerte $alerte): int => $alerte->niveau?->gravite() ?? 0)
            ->first();

        View::composer('layouts.stitch-nav', fn ($view) => $view->with('alerteLogin', $alerteActive()));

        View::composer('auth.login', fn ($view) => $view
            ->with('alerteLogin', $alerteActive())
            ->with('pointsFraicheurLogin', PointFraicheur::query()->valides()->orderBy('nom')->get()));
    }
}
