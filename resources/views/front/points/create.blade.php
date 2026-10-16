<x-public-layout title="Proposer un point de fraîcheur">
<nav aria-label="Fil d'Ariane" class="flex items-center gap-2 font-body-sm text-body-sm text-on-surface-variant">
<a href="{{ route('refuges.index') }}" class="inline-flex items-center gap-1 text-primary hover:underline"><span class="material-symbols-outlined text-[16px]">arrow_back</span>Points de fraîcheur</a>
<span aria-hidden="true">/</span><span class="text-on-surface font-medium">Proposer un point</span>
</nav>
<header class="rounded-2xl bg-surface-container-low p-space-md shadow-sm flex items-start gap-3">
<div class="p-3 rounded-xl bg-sky-100 text-sky-800 shrink-0"><span class="material-symbols-outlined text-[28px]">add_location_alt</span></div>
<div>
<h1 class="font-headline-md text-headline-md text-on-surface font-bold">Proposer un point de fraîcheur</h1>
<p class="font-body-md text-body-md text-on-surface-variant mt-1">Vous connaissez un parc ombragé, une salle climatisée ou une fontaine ? Partagez-le : un administrateur le vérifiera avant publication.</p>
</div>
</header>
<div class="rounded-2xl bg-surface-container-low/80 shadow-md p-space-md md:p-space-lg">
<form method="POST" action="{{ route('points.store') }}" enctype="multipart/form-data" novalidate>
@include('points-fraicheur._form', ['retour' => route('refuges.index')])
</form>
</div>
</x-public-layout>
