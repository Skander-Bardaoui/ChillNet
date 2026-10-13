<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Brouillons d'alerte canicule générés chaque matin par le moteur IA local.
// Les alertes créées sont toujours des BROUILLONS (validee = false) :
// aucun envoi automatique aux habitants.
Schedule::command('ai:predict-daily')
    ->dailyAt(sprintf('%02d:00', (int) config('ai.auto_draft.hour', 6)))
    ->onOneServer()
    ->withoutOverlapping();
