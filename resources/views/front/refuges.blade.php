<x-public-layout title="Refuges">
<a href="{{ route('home') }}" class="inline-flex items-center gap-1 font-body-sm text-body-sm text-primary hover:underline"><span class="material-symbols-outlined text-[16px]">arrow_back</span>Retour à l'accueil</a>

<section class="rounded-xl bg-surface-container-low/95 backdrop-blur-xl p-space-md shadow-xl flex flex-col gap-space-sm">
<div>
<span class="font-label-sm text-label-sm uppercase tracking-widest text-primary font-bold">Points de fraîcheur</span>
<h1 class="font-headline-lg text-headline-lg text-on-surface">Refuges près de chez vous</h1>
<p class="font-body-md text-body-md text-on-surface-variant mt-1">{{ $points->count() }} lieu(x) accessible(s) · salles climatisées, parcs ombragés et fontaines.</p>
</div>
<div class="rounded-lg bg-surface-container p-space-sm flex flex-wrap items-center justify-between gap-space-sm">
<div class="flex items-center gap-space-sm"><span class="material-symbols-outlined text-primary">directions_walk</span><p class="font-body-sm text-body-sm text-on-surface-variant">Trajet piéton ombragé · <strong class="text-primary font-medium">refuges à moins de 500 m</strong></p></div>
<span class="font-headline-sm text-headline-sm text-primary">{{ $points->where('salle_climatisee', true)->count() }} climatisé(s)</span>
</div>
</section>

@if ($points->isEmpty())
<div class="rounded-xl bg-surface-container-low p-space-md text-on-surface-variant">Aucun refuge référencé pour le moment.</div>
@else
<div class="grid grid-cols-1 sm:grid-cols-2 xl:grid-cols-3 gap-space-md">
@foreach ($points as $point)
<article class="flex flex-col gap-2 rounded-xl bg-surface-container/70 p-space-md shadow-md backdrop-blur-md">
<div class="flex items-center justify-between text-on-surface-variant">
<span class="font-label-md text-label-md uppercase tracking-wider">{{ $point->quartier->nom ?? '—' }}</span>
<span class="material-symbols-outlined text-primary">ac_unit</span>
</div>
<h2 class="font-title-md text-title-md text-on-surface">{{ $point->nom }}</h2>
<p class="font-body-sm text-body-sm text-on-surface-variant"><span class="material-symbols-outlined align-middle text-[14px]">place</span> {{ $point->adresse }}</p>
<p class="font-body-sm text-body-sm text-on-surface-variant">{{ $point->nombre_logements }} logement(s) desservi(s)</p>
<div class="mt-1 flex flex-wrap gap-2">
@if ($point->salle_climatisee)<span class="rounded-md bg-surface-variant px-2 py-0.5 font-label-sm text-label-sm text-primary">Salle climatisée</span>@endif
<span class="rounded-md bg-primary-container/15 px-2 py-0.5 font-label-sm text-label-sm text-primary">Point de fraîcheur</span>
</div>
<a href="{{ route('points.show', $point->id) }}" class="mt-1 inline-flex items-center gap-1 font-label-sm text-label-sm text-primary hover:underline">Détails et avis <span class="material-symbols-outlined text-[14px]">arrow_forward</span></a>
</article>
@endforeach
</div>
@endif
</x-public-layout>
