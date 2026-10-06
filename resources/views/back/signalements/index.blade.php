<x-back-layout :title="'Signalements communautaires'">
@php
    $catMeta = [
        'fuite' => ['label' => 'Fuite', 'icon' => 'water_drop', 'classes' => 'bg-sky-100 text-sky-800'],
        'panne_locale' => ['label' => 'Panne locale', 'icon' => 'build', 'classes' => 'bg-amber-100 text-amber-800'],
        'personne_vulnerable' => ['label' => 'Personne vulnérable', 'icon' => 'elderly', 'classes' => 'bg-purple-100 text-purple-800'],
        'autre' => ['label' => 'Autre', 'icon' => 'report', 'classes' => 'bg-surface-container-high text-on-surface-variant'],
    ];
    $urgMeta = [
        'vitale' => ['label' => 'Vitale', 'icon' => 'emergency', 'classes' => 'bg-red-100 text-red-800'],
        'prioritaire' => ['label' => 'Prioritaire', 'icon' => 'priority_high', 'classes' => 'bg-orange-100 text-orange-800'],
        'normale' => ['label' => 'Normale', 'icon' => 'low_priority', 'classes' => 'bg-surface-container-high text-on-surface-variant'],
    ];
    $statutMeta = [
        'nouveau' => ['label' => 'Nouveau', 'classes' => 'bg-sky-100 text-sky-800'],
        'en_traitement' => ['label' => 'En traitement', 'classes' => 'bg-amber-100 text-amber-800'],
        'resolu' => ['label' => 'Résolu', 'classes' => 'bg-green-100 text-green-800'],
    ];
    $filtresActifs = collect($filters)->filter()->isNotEmpty();
@endphp

{{-- En-tête du module --}}
<div class="rounded-2xl bg-surface-container-low shadow-sm border border-outline-variant/20 p-space-md flex flex-col lg:flex-row lg:items-center gap-4">
<div class="flex items-start gap-3 flex-1">
<div class="p-3 rounded-xl bg-primary-container/15 text-primary shrink-0"><span class="material-symbols-outlined text-[28px]">report</span></div>
<div>
<p class="font-label-sm text-label-sm uppercase tracking-widest text-on-surface-variant font-semibold">Module signalements · Toutes les résidences</p>
<p class="font-body-md text-body-md text-on-surface-variant mt-1">Suivez les remontées des habitants (fuites, pannes, personnes vulnérables), qualifiez l'<strong>urgence</strong> et faites évoluer le <strong>statut</strong> jusqu'à la résolution.</p>
</div>
</div>
<a href="{{ route('back.signalements.create') }}" class="shrink-0 px-space-md py-2.5 rounded-xl bg-primary-container text-on-primary-container font-label-md text-label-md font-semibold hover:opacity-95 inline-flex items-center justify-center gap-2 shadow"><span class="material-symbols-outlined text-[18px]">add</span>Nouveau signalement</a>
</div>

{{-- Chiffres clés --}}
<div class="grid grid-cols-2 lg:grid-cols-5 gap-3">
<div class="rounded-xl bg-surface-container-low border border-outline-variant/20 p-4 flex items-center gap-3">
<span class="material-symbols-outlined text-primary text-[28px]">inbox</span>
<div><p class="font-headline-sm text-headline-sm text-on-surface font-bold">{{ $stats->total ?? 0 }}</p><p class="font-body-sm text-body-sm text-on-surface-variant">Total</p></div>
</div>
<div class="rounded-xl bg-sky-50 border border-sky-200 p-4 flex items-center gap-3">
<span class="material-symbols-outlined text-sky-700 text-[28px]">fiber_new</span>
<div><p class="font-headline-sm text-headline-sm text-sky-900 font-bold">{{ $stats->nouveaux ?? 0 }}</p><p class="font-body-sm text-body-sm text-sky-800">Nouveaux</p></div>
</div>
<div class="rounded-xl bg-amber-50 border border-amber-200 p-4 flex items-center gap-3">
<span class="material-symbols-outlined text-amber-700 text-[28px]">pending_actions</span>
<div><p class="font-headline-sm text-headline-sm text-amber-900 font-bold">{{ $stats->en_traitement ?? 0 }}</p><p class="font-body-sm text-body-sm text-amber-800">En traitement</p></div>
</div>
<div class="rounded-xl bg-green-50 border border-green-200 p-4 flex items-center gap-3">
<span class="material-symbols-outlined text-green-700 text-[28px]">check_circle</span>
<div><p class="font-headline-sm text-headline-sm text-green-900 font-bold">{{ $stats->resolus ?? 0 }}</p><p class="font-body-sm text-body-sm text-green-800">Résolus</p></div>
</div>
<div class="rounded-xl bg-red-50 border border-red-200 p-4 flex items-center gap-3">
<span class="material-symbols-outlined text-red-700 text-[28px]">emergency</span>
<div><p class="font-headline-sm text-headline-sm text-red-900 font-bold">{{ $stats->urgences_vitales ?? 0 }}</p><p class="font-body-sm text-body-sm text-red-800">Urgences vitales</p></div>
</div>
</div>

{{-- Barre d'outils : filtres --}}
<form method="GET" action="{{ route('back.signalements.index') }}" class="flex flex-col lg:flex-row lg:items-center gap-3 rounded-2xl bg-surface-container-low shadow-sm border border-outline-variant/20 p-space-md">
<p class="font-body-sm text-body-sm text-on-surface-variant">{{ $signalements->count() }} signalement(s) affiché(s)</p>
<div class="lg:ml-auto flex flex-wrap items-center gap-2">
<select name="categorie" aria-label="Filtrer par catégorie" class="rounded-xl bg-surface-container-low border border-outline-variant/40 px-3 py-2 text-on-surface focus:border-primary-container focus:outline-none">
<option value="">Toutes les catégories</option>
<option value="fuite" @selected(($filters['categorie'] ?? '') === 'fuite')>Fuite</option>
<option value="panne_locale" @selected(($filters['categorie'] ?? '') === 'panne_locale')>Panne locale</option>
<option value="personne_vulnerable" @selected(($filters['categorie'] ?? '') === 'personne_vulnerable')>Personne vulnérable</option>
<option value="autre" @selected(($filters['categorie'] ?? '') === 'autre')>Autre</option>
</select>
<select name="urgence" aria-label="Filtrer par urgence" class="rounded-xl bg-surface-container-low border border-outline-variant/40 px-3 py-2 text-on-surface focus:border-primary-container focus:outline-none">
<option value="">Toutes les urgences</option>
<option value="vitale" @selected(($filters['urgence'] ?? '') === 'vitale')>Vitale</option>
<option value="prioritaire" @selected(($filters['urgence'] ?? '') === 'prioritaire')>Prioritaire</option>
<option value="normale" @selected(($filters['urgence'] ?? '') === 'normale')>Normale</option>
</select>
<select name="statut" aria-label="Filtrer par statut" class="rounded-xl bg-surface-container-low border border-outline-variant/40 px-3 py-2 text-on-surface focus:border-primary-container focus:outline-none">
<option value="">Tous les statuts</option>
<option value="nouveau" @selected(($filters['statut'] ?? '') === 'nouveau')>Nouveau</option>
<option value="en_traitement" @selected(($filters['statut'] ?? '') === 'en_traitement')>En traitement</option>
<option value="resolu" @selected(($filters['statut'] ?? '') === 'resolu')>Résolu</option>
</select>
<button class="px-space-md py-2 rounded-xl bg-primary-container text-on-primary-container font-label-md text-label-md font-semibold hover:opacity-95">Filtrer</button>
@if ($filtresActifs)
<a href="{{ route('back.signalements.index') }}" class="px-3 py-2 rounded-xl bg-surface-container text-on-surface font-label-md text-label-md hover:bg-surface-bright inline-flex items-center gap-1"><span class="material-symbols-outlined text-[18px]">restart_alt</span>Réinitialiser</a>
@endif
</div>
</form>

{{-- Tableau --}}
<div class="rounded-2xl bg-surface-container-low shadow-md border border-outline-variant/20 overflow-hidden">
<div class="overflow-x-auto">
<table class="min-w-full">
<thead><tr class="border-b border-outline-variant/20 bg-surface-container-lowest/60">
<th scope="col" class="px-6 py-3 text-left font-label-sm text-label-sm uppercase text-on-surface-variant">Catégorie</th>
<th scope="col" class="px-6 py-3 text-left font-label-sm text-label-sm uppercase text-on-surface-variant">Urgence</th>
<th scope="col" class="px-6 py-3 text-left font-label-sm text-label-sm uppercase text-on-surface-variant">Habitant / résidence</th>
<th scope="col" class="px-6 py-3 text-left font-label-sm text-label-sm uppercase text-on-surface-variant">Description</th>
<th scope="col" class="px-6 py-3 text-left font-label-sm text-label-sm uppercase text-on-surface-variant">Statut</th>
<th scope="col" class="px-6 py-3 text-left font-label-sm text-label-sm uppercase text-on-surface-variant">Date</th>
<th scope="col" class="px-6 py-3"><span class="sr-only">Actions</span></th>
</tr></thead>
<tbody>
@forelse ($signalements as $signalement)
@php
    $cat = $catMeta[$signalement->categorie] ?? $catMeta['autre'];
    $urg = $urgMeta[$signalement->urgence] ?? $urgMeta['normale'];
    $st = $statutMeta[$signalement->statut] ?? $statutMeta['nouveau'];
@endphp
<tr class="border-b border-outline-variant/10 align-top hover:bg-surface-container/60 last:border-0">
<td class="px-6 py-4">
<span class="inline-flex items-center gap-1 px-2.5 py-0.5 rounded-full {{ $cat['classes'] }} font-label-sm text-label-sm font-semibold whitespace-nowrap"><span class="material-symbols-outlined text-[14px]">{{ $cat['icon'] }}</span>{{ $signalement->categorie === 'autre' ? ($signalement->categorie_autre ?: $cat['label']) : $cat['label'] }}</span>
</td>
<td class="px-6 py-4">
<div class="flex flex-col items-start gap-2">
<span class="inline-flex items-center gap-1 px-2.5 py-0.5 rounded-full {{ $urg['classes'] }} font-label-sm text-label-sm font-semibold whitespace-nowrap"><span class="material-symbols-outlined text-[14px]">{{ $urg['icon'] }}</span>{{ $urg['label'] }}</span>
<form method="POST" action="{{ route('back.signalements.treatment', $signalement) }}" class="flex items-center gap-1.5">
@csrf
@method('PATCH')
<input type="hidden" name="statut" value="{{ $signalement->statut }}">
<select name="urgence" aria-label="Urgence du signalement" class="rounded-lg bg-surface-container border border-outline-variant/40 px-2 py-1 text-body-sm font-body-sm text-on-surface focus:border-primary-container focus:outline-none">
<option value="vitale" @selected($signalement->urgence === 'vitale')>Vitale</option>
<option value="prioritaire" @selected($signalement->urgence === 'prioritaire')>Prioritaire</option>
<option value="normale" @selected($signalement->urgence === 'normale')>Normale</option>
</select>
<button type="submit" title="Enregistrer l'urgence" aria-label="Enregistrer l'urgence" class="inline-flex items-center justify-center h-8 w-8 rounded-lg bg-primary-container text-on-primary-container hover:opacity-95"><span class="material-symbols-outlined text-[18px]">check</span></button>
</form>
</div>
</td>
<td class="px-6 py-4">
<p class="font-title-sm text-title-sm text-on-surface font-medium">{{ $signalement->habitant->name }}</p>
<p class="font-body-sm text-body-sm text-on-surface-variant">{{ $signalement->residence->nom }}</p>
</td>
<td class="max-w-sm px-6 py-4">
<p class="font-body-sm text-body-sm text-on-surface-variant">{{ $signalement->description }}</p>
@if ($signalement->photo_path)
<a href="{{ asset('storage/'.$signalement->photo_path) }}" target="_blank" rel="noopener" class="mt-1 inline-flex items-center gap-1 font-label-sm text-label-sm font-semibold text-primary hover:underline"><span class="material-symbols-outlined text-[16px]">image</span>Voir la photo</a>
@endif
</td>
<td class="px-6 py-4">
<div class="flex flex-col items-start gap-2">
<span class="inline-flex items-center gap-1 px-2.5 py-0.5 rounded-full {{ $st['classes'] }} font-label-sm text-label-sm font-semibold whitespace-nowrap">{{ $st['label'] }}</span>
<form method="POST" action="{{ route('back.signalements.treatment', $signalement) }}" class="flex items-center gap-1.5">
@csrf
@method('PATCH')
<input type="hidden" name="urgence" value="{{ $signalement->urgence }}">
<select name="statut" aria-label="Statut du signalement" class="rounded-lg bg-surface-container border border-outline-variant/40 px-2 py-1 text-body-sm font-body-sm text-on-surface focus:border-primary-container focus:outline-none">
<option value="nouveau" @selected($signalement->statut === 'nouveau')>Nouveau</option>
<option value="en_traitement" @selected($signalement->statut === 'en_traitement')>En traitement</option>
<option value="resolu" @selected($signalement->statut === 'resolu')>Résolu</option>
</select>
<button type="submit" title="Enregistrer le statut" aria-label="Enregistrer le statut" class="inline-flex items-center justify-center h-8 w-8 rounded-lg bg-primary-container text-on-primary-container hover:opacity-95"><span class="material-symbols-outlined text-[18px]">check</span></button>
</form>
</div>
</td>
<td class="whitespace-nowrap px-6 py-4 font-body-sm text-body-sm text-on-surface-variant">{{ $signalement->date_signalement->format('d/m/Y') }}</td>
<td class="px-6 py-4 text-right whitespace-nowrap space-x-3">
<a href="{{ route('back.signalements.edit', $signalement) }}" title="Modifier" aria-label="Modifier" class="inline-flex items-center justify-center h-9 w-9 rounded-lg text-primary hover:bg-primary-container/20"><span class="material-symbols-outlined text-[18px]">edit</span></a>
<form method="POST" action="{{ route('back.signalements.destroy', $signalement) }}" class="inline" onsubmit="return confirm('Supprimer définitivement ce signalement ?');">@csrf @method('DELETE')<button type="submit" title="Supprimer" aria-label="Supprimer" class="inline-flex items-center justify-center h-9 w-9 rounded-lg text-error hover:bg-error-container/40"><span class="material-symbols-outlined text-[18px]">delete</span></button></form>
</td>
</tr>
@empty
<tr><td colspan="7" class="px-6 py-10 text-center">
<span class="material-symbols-outlined text-[40px] text-on-surface-variant">inbox</span>
<p class="mt-1 font-title-md text-title-md text-on-surface font-medium">Aucun signalement pour le moment</p>
<p class="font-body-sm text-body-sm text-on-surface-variant mt-1">Les signalements créés par les habitants apparaîtront ici.</p>
</td></tr>
@endforelse
</tbody>
</table>
</div>
</div>
</x-back-layout>
