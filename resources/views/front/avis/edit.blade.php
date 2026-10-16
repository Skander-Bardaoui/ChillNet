<x-public-layout title="Modifier mon avis">
<nav aria-label="Fil d'Ariane" class="flex items-center gap-2 font-body-sm text-body-sm text-on-surface-variant">
<a href="{{ route('points.show', $avis->pointFraicheur) }}" class="inline-flex items-center gap-1 text-primary hover:underline"><span class="material-symbols-outlined text-[16px]">arrow_back</span>{{ $avis->pointFraicheur->nom }}</a>
<span aria-hidden="true">/</span><span class="text-on-surface font-medium">Modifier mon avis</span>
</nav>
<section class="rounded-2xl bg-surface-container-low p-space-md md:p-space-lg shadow-md max-w-3xl w-full">
<h1 class="font-headline-sm text-headline-sm text-on-surface mb-space-sm">Modifier mon avis sur « {{ $avis->pointFraicheur->nom }} »</h1>
<form method="POST" action="{{ route('avis.update', $avis) }}" novalidate>
@method('PUT')
@include('points-fraicheur._avis-form')
</form>
</section>
</x-public-layout>
