{{-- Prévision IA (14 j) d'un point pour les habitants : santé prédite à partir
     des avis récents, des équipements du point et de la chaleur. --}}
@php
    $niveau = $sante['niveau'];
    $probabilite = $sante['p14'] !== null ? (int) round($sante['p14'] * 100) : null;
@endphp
<div class="rounded-2xl bg-surface-container-low p-space-md shadow-md border-l-4" style="border-left-color: {{ $niveau->couleurHex() }}">
<div class="flex items-center gap-space-sm">
<div class="p-2 rounded-lg bg-primary/10 text-primary"><span class="material-symbols-outlined text-[20px]">insights</span></div>
<h2 class="font-title-lg text-title-lg text-on-surface">Prévision IA · {{ \App\Services\SantePointService::HORIZON_JOURS }} jours</h2>
</div>

<div class="mt-3 flex flex-wrap items-center gap-2">
<span class="px-2.5 py-1 rounded-full font-label-md text-label-md font-semibold inline-flex items-center gap-1 {{ $niveau->badgeClasses() }}"><span class="material-symbols-outlined text-[16px]">{{ $niveau->icone() }}</span>{{ $niveau->libellePublic() }}</span>
@if ($probabilite !== null && $niveau->estARisque())
<span class="font-body-sm text-body-sm text-on-surface-variant">Risque d'avis négatifs : <strong class="text-on-surface">{{ $probabilite }} %</strong></span>
@endif
</div>
<p class="mt-2 font-body-md text-body-md text-on-surface">{{ $niveau->messagePublic() }}</p>

@if ($niveau->estARisque() && $sante['facteurs'] !== [])
<p class="mt-3 font-label-md text-label-md text-on-surface font-semibold">Signaux observés</p>
<ul class="mt-1 flex flex-col gap-1">
@foreach (array_slice($sante['facteurs'], 0, 3) as $facteur)
<li class="font-body-sm text-body-sm text-on-surface-variant flex items-start gap-1.5"><span class="material-symbols-outlined text-[16px] text-primary mt-0.5">arrow_right</span>{{ $facteur }}</li>
@endforeach
</ul>
@endif

@if ($alternative)
@php $alt = $alternative['point']; @endphp
<a href="{{ route('points.show', $alt) }}" class="mt-3 rounded-xl bg-green-50 border border-green-200 p-3 flex items-center gap-3 hover:bg-green-100">
<span class="material-symbols-outlined text-green-700 shrink-0">{{ $alt->type->icone() }}</span>
<span class="flex-1 min-w-0">
<span class="block font-label-sm text-label-sm text-green-800 uppercase">Alternative en bon état</span>
<span class="block font-title-sm text-title-sm text-on-surface font-semibold truncate">{{ $alt->nom }}</span>
<span class="block font-body-sm text-body-sm text-on-surface-variant">{{ $alternative['minutes_marche'] }} min à pied · {{ mb_strtolower($alternative['affluence']['libelle']) }}</span>
</span>
<span class="material-symbols-outlined text-green-700 shrink-0">arrow_forward</span>
</a>
@endif

<p class="mt-3 pt-2 border-t border-outline-variant/20 font-label-sm text-label-sm text-on-surface-variant">Calculée à partir des avis récents, des équipements du point et de la chaleur{{ $sante['source'] === 'modele' ? '' : ' (estimation simplifiée)' }}.</p>
</div>
