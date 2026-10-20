<x-public-layout :title="'Quartier '.$quartier->nom">
<nav aria-label="Fil d'Ariane" class="flex items-center gap-2 font-body-sm text-body-sm text-on-surface-variant">
<a href="{{ route('home') }}" class="inline-flex items-center gap-1 text-primary hover:underline"><span class="material-symbols-outlined text-[16px]">arrow_back</span>Accueil</a>
<span aria-hidden="true">/</span>
<span class="text-on-surface font-medium truncate">Quartier {{ $quartier->nom }}</span>
</nav>

@php
    $nbResidences = $quartier->residences->count();
    $nbLogements = (int) $quartier->residences->sum('nombre_logements');
    $nbClim = $quartier->residences->where('salle_climatisee', true)->count();
    $nbPoints = $points->count();
@endphp

<section class="rounded-2xl bg-surface-container-low shadow-lg p-space-md md:p-space-lg flex flex-col gap-space-sm">
<div class="flex flex-col md:flex-row md:items-start md:justify-between gap-space-sm">
<div class="flex flex-col gap-1">
<p class="font-label-sm text-label-sm text-primary uppercase tracking-wider font-semibold">Quartier</p>
<h1 class="font-headline-lg text-headline-lg text-on-surface font-bold">{{ $quartier->nom }}</h1>
<p class="font-body-md text-body-md text-on-surface-variant flex items-center gap-1">
<span class="material-symbols-outlined text-[18px]">place</span>
{{ $quartier->ville }}@if ($quartier->code_postal) · {{ $quartier->code_postal }}@endif
</p>
@if ($quartier->description)
<p class="font-body-md text-body-md text-on-surface">{{ $quartier->description }}</p>
@endif
</div>
<a href="{{ route('refuges.index') }}" class="shrink-0 inline-flex items-center gap-2 px-space-md py-2.5 rounded-lg bg-primary-container text-on-primary-container font-label-md text-label-md font-semibold hover:opacity-95">
<span class="material-symbols-outlined text-[18px]">map</span>Points de fraîcheur
</a>
</div>
<div class="flex flex-wrap gap-2 mt-1">
<span class="px-3 py-1 rounded-lg bg-surface-container text-on-surface font-label-sm text-label-sm">{{ $nbResidences }} résidence(s)</span>
<span class="px-3 py-1 rounded-lg bg-surface-container text-on-surface font-label-sm text-label-sm">{{ $nbPoints }} point(s) de fraîcheur</span>
<span class="px-3 py-1 rounded-lg bg-surface-container text-on-surface font-label-sm text-label-sm">{{ $nbLogements }} logement(s)</span>
<span class="px-3 py-1 rounded-lg bg-surface-container text-on-surface font-label-sm text-label-sm">{{ $nbClim }} salle(s) climatisée(s)</span>
</div>
</section>

@if ($alerte)
@php $niveau = $alerte->niveau; $couleur = $niveau?->couleurHex() ?? '#b45309'; @endphp
<section class="relative overflow-hidden rounded-xl bg-surface-container-low shadow-md border border-outline-variant/20">
<div class="absolute inset-y-0 left-0 w-2" style="background: {{ $couleur }};"></div>
<div class="p-space-md pl-space-lg flex flex-col md:flex-row md:items-center gap-space-md justify-between">
<div class="flex items-start gap-space-sm">
<span class="material-symbols-outlined text-[26px]" style="color: {{ $couleur }};">{{ $niveau?->icone() ?? 'warning' }}</span>
<div>
<span class="px-2 py-0.5 rounded-full font-label-sm text-label-sm uppercase tracking-wider font-semibold {{ $niveau?->badgeClasses() }}">Vigilance {{ $niveau?->label() }}</span>
<p class="font-title-md text-title-md text-on-surface mt-1">{{ $alerte->titre }}</p>
@if ($alerte->message)
<p class="font-body-sm text-body-sm text-on-surface-variant">{{ $alerte->message }}</p>
@endif
<p class="font-body-sm text-body-sm text-on-surface-variant mt-1">Du {{ $alerte->debut?->format('d/m à H:i') }} au {{ $alerte->fin?->format('d/m à H:i') }}</p>
</div>
</div>
@auth
<a href="{{ route('alertes.index') }}" class="shrink-0 text-primary font-label-md text-label-md hover:underline">Voir les consignes →</a>
@endauth
</div>
</section>
@endif

@if ($coupures->isNotEmpty())
<section class="rounded-xl bg-surface-container-low shadow-md border border-outline-variant/20 p-space-md md:p-space-lg">
<h2 class="font-headline-sm text-headline-sm text-on-surface flex items-center gap-2 mb-3"><span class="material-symbols-outlined text-orange-600 text-[20px]">bolt</span>Coupures en cours et programmées</h2>
<div class="flex flex-col divide-y divide-outline-variant/10">
@foreach ($coupures as $coupure)
<div class="flex flex-col sm:flex-row sm:items-center gap-2 py-3">
<div class="flex-1 min-w-0">
<p class="font-title-sm text-title-sm text-on-surface">{{ $coupure->type?->label() }} — {{ $coupure->lieu ?? $quartier->nom }}</p>
<p class="font-body-sm text-body-sm text-on-surface-variant">{{ $coupure->debut?->format('d/m H:i') }} → {{ $coupure->fin?->format('d/m H:i') ?? 'rétablissement non communiqué' }} · {{ $coupure->confirmations }} confirmation(s)</p>
</div>
<span class="px-2.5 py-0.5 rounded-full font-label-sm text-label-sm font-semibold whitespace-nowrap {{ $coupure->statut?->value === 'en_cours' ? 'bg-red-100 text-red-800' : 'bg-orange-100 text-orange-800' }}">{{ $coupure->statut?->label() }}</span>
</div>
@endforeach
</div>
<a href="{{ route('coupures.index') }}" class="inline-block mt-2 font-label-md text-label-md text-primary hover:underline">Toutes les coupures →</a>
</section>
@endif

<div class="grid grid-cols-1 lg:grid-cols-3 gap-space-md items-start">
<section class="lg:col-span-2 rounded-xl bg-surface-container-low shadow-md border border-outline-variant/20 p-space-md md:p-space-lg">
<h2 class="font-headline-sm text-headline-sm text-on-surface flex items-center gap-2 mb-3"><span class="material-symbols-outlined text-primary text-[20px]">map</span>Carte du quartier</h2>
@if ($marqueurs !== [])
<div id="carte-quartier" class="w-full h-80 rounded-xl overflow-hidden z-0 border border-outline-variant/30"></div>
<p class="mt-2 font-body-sm text-body-sm text-on-surface-variant">Points de fraîcheur validés et résidences géolocalisées du quartier.</p>
@else
<div class="rounded-lg bg-surface-container p-space-md font-body-sm text-body-sm text-on-surface-variant">Aucun point géolocalisé dans ce quartier pour le moment.</div>
@endif
</section>

<aside class="flex flex-col gap-space-sm">
<div class="rounded-xl bg-surface-container-low shadow-md border border-outline-variant/20 p-space-md">
<h2 class="font-title-md text-title-md text-on-surface font-semibold mb-2">Points de fraîcheur</h2>
@forelse ($points as $point)
<a href="{{ route('points.show', $point) }}" class="flex items-start gap-2 py-2 border-b border-outline-variant/10 last:border-0 hover:text-primary transition-colors">
<span class="material-symbols-outlined text-[20px] text-primary shrink-0">{{ $point->type->icone() }}</span>
<div class="min-w-0">
<p class="font-body-md text-body-md text-on-surface font-medium truncate">{{ $point->nom }}</p>
<p class="font-body-sm text-body-sm text-on-surface-variant truncate">{{ $point->type->label() }} · {{ $point->horaires() }}</p>
<p class="font-body-sm text-body-sm text-on-surface-variant">{{ $point->avis_count }} avis · {{ $point->noteMoyenne() !== null ? number_format($point->noteMoyenne(), 1, ',', '').'/5' : 'pas encore noté' }}</p>
</div>
</a>
@empty
<p class="font-body-sm text-body-sm text-on-surface-variant">Aucun point de fraîcheur validé dans ce quartier.</p>
@endforelse
</div>
</aside>
</div>

<section id="residences" class="rounded-xl bg-surface-container-low shadow-md border border-outline-variant/20 p-space-md md:p-space-lg">
<h2 class="font-headline-sm text-headline-sm text-on-surface flex items-center gap-2 mb-3"><span class="material-symbols-outlined text-primary text-[20px]">apartment</span>Résidences du quartier</h2>
<div class="grid grid-cols-1 md:grid-cols-2 gap-space-sm">
@forelse ($quartier->residences as $residence)
<div class="rounded-lg bg-surface-container p-space-sm flex flex-col gap-1">
<p class="font-title-sm text-title-sm text-on-surface font-semibold">{{ $residence->nom }}</p>
<p class="font-body-sm text-body-sm text-on-surface-variant">{{ $residence->adresse ?? 'Adresse non renseignée' }}</p>
<div class="flex flex-wrap gap-1.5 mt-1">
<span class="px-2 py-0.5 rounded bg-surface-variant text-on-surface-variant font-label-sm text-label-sm">{{ $residence->nombre_logements ?? '—' }} logements</span>
@if ($residence->salle_climatisee)<span class="px-2 py-0.5 rounded bg-primary-container/15 text-primary font-label-sm text-label-sm">Salle climatisée</span>@endif
@if ($residence->point_fraicheur)<span class="px-2 py-0.5 rounded bg-primary-container/15 text-primary font-label-sm text-label-sm">Point de fraîcheur</span>@endif
</div>
</div>
@empty
<p class="font-body-sm text-body-sm text-on-surface-variant">Aucune résidence référencée dans ce quartier.</p>
@endforelse
</div>
</section>

<section class="rounded-xl bg-surface-container-low p-space-md shadow-sm flex flex-wrap items-center gap-space-md">
<span class="material-symbols-outlined text-primary text-[22px]">info</span>
<p class="font-body-sm text-body-sm text-on-surface-variant">En cas de malaise : appelez le <a href="tel:190" class="text-primary font-semibold hover:underline">190</a> (SAMU) ou le <a href="tel:198" class="text-primary font-semibold hover:underline">198</a> (Protection civile).</p>
</section>

@if ($marqueurs !== [])
<link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" />
@include('points-fraicheur._carte-script', [
    'idCarte' => 'carte-quartier',
    'marqueurs' => $marqueurs,
    'origine' => $origine,
    'rayonKm' => null,
])
@endif
</x-public-layout>
