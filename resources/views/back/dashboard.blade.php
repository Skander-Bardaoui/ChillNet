<x-back-layout :title="'Tableau de bord'">
@if(auth()->user()->isAdmin())
{{-- ADMIN : vision globale du réseau --}}
<section class="grid grid-cols-1 sm:grid-cols-2 xl:grid-cols-4 gap-space-md">
<div class="rounded-xl bg-surface-container/70 backdrop-blur-md p-space-md shadow-md"><div class="flex items-center justify-between text-on-surface-variant"><span class="font-label-md text-label-md uppercase tracking-wider">Température</span><span class="material-symbols-outlined text-tertiary-container">thermostat</span></div><p class="font-display-lg text-display-lg text-on-surface mt-1">38.2°C</p><p class="font-body-sm text-body-sm text-on-surface-variant">Pic 16h30 · 41°C</p></div>
<div class="rounded-xl bg-surface-container/70 backdrop-blur-md p-space-md shadow-md"><div class="flex items-center justify-between text-on-surface-variant"><span class="font-label-md text-label-md uppercase tracking-wider">Réseau</span><span class="material-symbols-outlined text-primary">bolt</span></div><p class="font-display-lg text-display-lg text-on-surface mt-1">88%</p><p class="font-body-sm text-body-sm text-tertiary-fixed">Éco-vigilance 14h–18h</p></div>
<div class="rounded-xl bg-surface-container/70 backdrop-blur-md p-space-md shadow-md"><div class="flex items-center justify-between text-on-surface-variant"><span class="font-label-md text-label-md uppercase tracking-wider">Quartiers</span><span class="material-symbols-outlined text-primary">location_city</span></div><p class="font-display-lg text-display-lg text-on-surface mt-1">{{ \App\Models\Quartier::count() }}</p><a href="{{ route('back.quartiers.index') }}" class="font-body-sm text-body-sm text-primary hover:underline">Gérer →</a></div>
<div class="rounded-xl bg-surface-container/70 backdrop-blur-md p-space-md shadow-md"><div class="flex items-center justify-between text-on-surface-variant"><span class="font-label-md text-label-md uppercase tracking-wider">Résidences</span><span class="material-symbols-outlined text-primary">home</span></div><p class="font-display-lg text-display-lg text-on-surface mt-1">{{ \App\Models\Residence::count() }}</p><a href="{{ route('back.residences.index') }}" class="font-body-sm text-body-sm text-primary hover:underline">Gérer →</a></div>
</section>
<p class="font-body-sm text-body-sm text-on-surface-variant">Espace <strong class="text-on-surface">administrateur</strong> : référentiel global — quartiers + toutes les résidences (création, modification, suppression).</p>
@else
{{-- GESTIONNAIRE : périmètre = sa résidence uniquement --}}
@php($maResidence = auth()->user()->residence)
<section class="grid grid-cols-1 lg:grid-cols-3 gap-space-md">
<div class="lg:col-span-2 rounded-xl bg-surface-container/70 backdrop-blur-md p-space-md shadow-md">
<div class="flex items-center justify-between text-on-surface-variant"><span class="font-label-md text-label-md uppercase tracking-wider">Ma résidence</span><span class="material-symbols-outlined text-primary">apartment</span></div>
@if($maResidence)
<p class="font-headline-sm text-headline-sm text-on-surface mt-1">{{ $maResidence->nom }}</p>
<p class="font-body-sm text-body-sm text-on-surface-variant">{{ $maResidence->adresse ?? 'Adresse non renseignée' }}@if($maResidence->quartier) — Quartier {{ $maResidence->quartier->nom }} ({{ $maResidence->quartier->ville }})@endif</p>
<div class="flex flex-wrap gap-2 mt-3">
<span class="px-2 py-0.5 rounded-md bg-surface-variant text-on-surface font-label-sm text-label-sm">{{ $maResidence->nombre_logements ?? '—' }} logements</span>
@if($maResidence->salle_climatisee)<span class="px-2 py-0.5 rounded-md bg-surface-variant text-primary font-label-sm text-label-sm">Salle climatisée</span>@endif
@if($maResidence->point_fraicheur)<span class="px-2 py-0.5 rounded-md bg-primary-container/15 text-primary font-label-sm text-label-sm">Point de fraîcheur</span>@endif
</div>
<a href="{{ route('back.residences.edit', $maResidence->id) }}" class="inline-flex items-center gap-2 mt-4 px-4 py-2.5 rounded-lg bg-primary-container text-on-primary-container font-label-md text-label-md font-semibold hover:opacity-95"><span class="material-symbols-outlined text-[18px]">edit</span>Modifier ma résidence</a>
@else
<p class="font-title-md text-title-md text-on-surface mt-1">Aucune résidence rattachée</p>
<p class="font-body-sm text-body-sm text-on-surface-variant">Contactez un administrateur pour être rattaché à votre résidence.</p>
@endif
</div>
<div class="rounded-xl bg-surface-container/70 backdrop-blur-md p-space-md shadow-md"><div class="flex items-center justify-between text-on-surface-variant"><span class="font-label-md text-label-md uppercase tracking-wider">Mon périmètre</span><span class="material-symbols-outlined text-primary">shield</span></div><p class="font-body-md text-body-md text-on-surface mt-2">1 résidence · 0 quartier</p><p class="font-body-sm text-body-sm text-on-surface-variant mt-1">Vous gérez uniquement votre résidence (fiche + équipements). Les quartiers et les autres résidences sont administrés par un admin.</p><a href="{{ route('back.residences.index') }}" class="font-body-sm text-body-sm text-primary hover:underline">Voir ma fiche →</a></div>
</section>
@endif
</x-back-layout>
