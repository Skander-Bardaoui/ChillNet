<x-public-layout title="Modifier ma proposition">
<nav aria-label="Fil d'Ariane" class="flex items-center gap-2 font-body-sm text-body-sm text-on-surface-variant">
<a href="{{ route('points.mine') }}" class="inline-flex items-center gap-1 text-primary hover:underline"><span class="material-symbols-outlined text-[16px]">arrow_back</span>Mes propositions</a>
<span aria-hidden="true">/</span><span class="text-on-surface font-medium">{{ $point->nom }}</span>
</nav>
@if ($point->motif_refus)
<div role="alert" class="rounded-xl bg-red-50 border border-red-200 text-red-900 px-space-md py-space-sm flex items-start gap-2"><span class="material-symbols-outlined">info</span><span><strong>Refusé :</strong> {{ $point->motif_refus }} — corrigez puis renvoyez votre proposition.</span></div>
@endif
<div class="rounded-2xl bg-surface-container-low/80 shadow-md p-space-md md:p-space-lg">
<form method="POST" action="{{ route('points.update', $point) }}" enctype="multipart/form-data" novalidate>
@method('PUT')
@include('points-fraicheur._form', ['retour' => route('points.mine')])
</form>
</div>
</x-public-layout>
