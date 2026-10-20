{{-- Santé prédite d'un point (modèle IA du module 3 + plancher des règles). --}}
@php
    $niveau = $sante['niveau'];
    $probabilite = $sante['p14'] !== null ? (int) round($sante['p14'] * 100) : null;
@endphp
<div class="rounded-2xl bg-surface-container-low shadow-sm border border-outline-variant/20 p-space-md">
<h2 class="font-title-md text-title-md text-on-surface font-semibold flex items-center gap-2"><span class="material-symbols-outlined text-primary">monitor_heart</span>Santé prédite (IA)</h2>
<p class="mt-1 font-body-sm text-body-sm text-on-surface-variant">Risque que ce point reçoive au moins {{ \App\Services\SantePointService::SEUIL_NEGATIFS }} avis négatifs dans les {{ \App\Services\SantePointService::HORIZON_JOURS }} prochains jours.</p>
<div class="mt-3 flex items-center gap-4">
<div class="relative h-20 w-20 shrink-0 rounded-full" style="background: conic-gradient({{ $niveau->couleurHex() }} {{ $sante['score'] }}%, rgb(127 127 127 / 0.18) 0)" role="img" aria-label="Score de risque {{ $sante['score'] }} sur 100">
<div class="absolute inset-2 rounded-full bg-surface-container-low flex flex-col items-center justify-center leading-none">
<span class="font-title-lg text-title-lg font-bold text-on-surface">{{ $sante['score'] }}</span>
<span class="font-label-sm text-label-sm text-on-surface-variant">/100</span>
</div>
</div>
<div class="flex flex-col gap-1">
<span class="self-start px-2.5 py-1 rounded-full font-label-sm text-label-sm font-semibold inline-flex items-center gap-1 {{ $niveau->badgeClasses() }}"><span class="material-symbols-outlined text-[14px]">{{ $niveau->icone() }}</span>{{ $niveau->label() }}</span>
@if ($probabilite !== null)
<p class="font-body-sm text-body-sm text-on-surface">Probabilité estimée : <strong>{{ $probabilite }} %</strong></p>
@endif
@if ($sante['desaccord'] && $sante['score_regles'] > ($sante['score_modele'] ?? 0))
<p class="font-body-sm text-body-sm text-on-surface-variant">Niveau relevé par les règles de sécurité.</p>
@endif
</div>
</div>

@if ($sante['facteurs'] !== [])
<p class="mt-3 font-label-md text-label-md text-on-surface font-semibold">Pourquoi ?</p>
<ul class="mt-1 flex flex-col gap-1">
@foreach ($sante['facteurs'] as $facteur)
<li class="font-body-sm text-body-sm text-on-surface-variant flex items-start gap-1.5"><span class="material-symbols-outlined text-[16px] text-primary mt-0.5">arrow_right</span>{{ $facteur }}</li>
@endforeach
</ul>
@endif

@if ($sante['actions'] !== [])
<p class="mt-3 font-label-md text-label-md text-on-surface font-semibold">Actions conseillées</p>
<ul class="mt-1 flex flex-col gap-1">
@foreach ($sante['actions'] as $action)
<li class="font-body-sm text-body-sm text-on-surface flex items-start gap-1.5"><span class="material-symbols-outlined text-[16px] text-amber-600 mt-0.5">task_alt</span>{{ $action }}</li>
@endforeach
</ul>
@endif

<p class="mt-3 pt-2 border-t border-outline-variant/20 font-label-sm text-label-sm text-on-surface-variant">
@if ($sante['source'] === 'modele')
Modèle {{ $sante['model_version'] ? 'v'.$sante['model_version'] : '' }} · plancher des règles de sécurité appliqué
@else
Règles de sécurité (service IA indisponible)
@endif
</p>
</div>
