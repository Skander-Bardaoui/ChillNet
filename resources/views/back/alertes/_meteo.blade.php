{{-- Carte météo « générale » affichée à droite du formulaire d'alerte, avant
     toute saisie. `$meteo` = App\Services\MeteoActuelle ou null.
     Elle reflète aussi la dernière analyse IA (événement window « alerte-ia »
     émis par le formulaire) : niveau, confiance, probabilités, facteurs. --}}
<div x-data="{ ia: null, libelleClasse(cls) { return { normal: 'Aucune alerte', jaune: 'Vigilance jaune', orange: 'Vigilance orange', rouge: 'Vigilance rouge' }[cls] || cls; }, couleurClasse(cls) { return { normal: 'bg-outline', jaune: 'bg-amber-500', orange: 'bg-orange-500', rouge: 'bg-red-600' }[cls] || 'bg-outline'; } }"
     @alerte-ia.window="ia = $event.detail"
     class="rounded-xl bg-surface-container-low/80 backdrop-blur-xl shadow-xl p-space-lg flex flex-col gap-4 lg:sticky lg:top-4">
<div class="flex items-center gap-2">
<span class="material-symbols-outlined text-primary text-[24px]">partly_cloudy_day</span>
<h2 class="font-title-md text-title-md text-on-surface font-semibold">Météo actuelle</h2>
</div>

@if ($meteo)
<div class="flex items-end gap-3">
<span class="font-headline-lg text-headline-lg text-on-surface leading-none">{{ number_format($meteo->temperature, 1, ',', '') }}°C</span>
@if ($meteo->condition)
<span class="font-body-md text-body-md text-on-surface-variant pb-1">{{ $meteo->condition }}</span>
@endif
</div>

@if ($meteo->ville)
<p class="font-body-sm text-body-sm text-on-surface-variant flex items-center gap-1"><span class="material-symbols-outlined text-[16px]">place</span>{{ $meteo->ville }}</p>
@endif

<div class="flex flex-col gap-2 rounded-lg bg-surface-container px-3 py-3">
@if ($meteo->ressentie !== null)
<div class="flex items-center justify-between font-body-sm text-body-sm">
<span class="text-on-surface-variant">Ressentie</span>
<span class="text-on-surface font-medium">{{ number_format($meteo->ressentie, 1, ',', '') }}°C</span>
</div>
@endif
@if ($meteo->humidite !== null)
<div class="flex items-center justify-between font-body-sm text-body-sm">
<span class="text-on-surface-variant">Humidité</span>
<span class="text-on-surface font-medium">{{ $meteo->humidite }} %</span>
</div>
@endif
@if ($meteo->vent !== null)
<div class="flex items-center justify-between font-body-sm text-body-sm">
<span class="text-on-surface-variant">Vent</span>
<span class="text-on-surface font-medium">{{ number_format($meteo->vent, 0, ',', '') }} km/h</span>
</div>
@endif
</div>

<span class="self-start rounded-full bg-surface-container px-3 py-1 font-label-sm text-label-sm text-on-surface-variant">{{ $meteo->source === 'manuel' ? 'Saisie manuelle' : 'WeatherAPI' }}</span>

<p class="font-body-sm text-body-sm text-on-surface-variant">Relevé général{{ isset($meteoLieu) && $meteoLieu ? ' · '.$meteoLieu : '' }}. Sélectionnez un quartier ou posez un point sur la carte à gauche pour affiner la mesure.</p>
@else
<div class="flex items-start gap-2 rounded-lg bg-surface-container px-3 py-3">
<span class="material-symbols-outlined text-on-surface-variant text-[20px] shrink-0">cloud_off</span>
<p class="font-body-sm text-body-sm text-on-surface-variant">Météo indisponible pour le moment (service injoignable ou clé absente). Vous pouvez saisir la température manuellement, ou utiliser « Météo + niveau ».</p>
</div>
@endif

{{-- Analyse IA (remplie par le bouton « Météo + niveau » du formulaire). --}}
<template x-if="ia && ia.ok">
<div class="flex flex-col gap-2 rounded-lg border border-primary-container/30 bg-primary-container/5 px-3 py-3">
<div class="flex items-center justify-between">
<span class="font-label-sm text-label-sm text-on-surface-variant uppercase tracking-wider">Analyse IA</span>
<span class="font-label-sm text-label-sm font-semibold" :class="ia.desaccord ? 'text-amber-700' : 'text-primary'" x-text="ia.desaccord ? 'Modèle ≠ règles' : (ia.modele_disponible ? 'Modèle + règles' : 'Règles seules')"></span>
</div>
<p class="font-body-sm text-body-sm text-on-surface" x-text="ia.raison"></p>
<div class="flex items-center justify-between font-body-sm text-body-sm">
<span class="text-on-surface-variant">Confiance du modèle</span>
<span class="text-on-surface font-medium" x-text="Math.round((ia.confiance || 0) * 100) + ' %'"></span>
</div>
<template x-if="ia.probabilites && Object.keys(ia.probabilites).length">
<div class="flex flex-col gap-1">
<template x-for="(p, cls) in ia.probabilites" :key="cls">
<div class="flex items-center gap-2">
<span class="w-28 font-label-sm text-label-sm text-on-surface-variant" x-text="libelleClasse(cls)"></span>
<div class="flex-1 h-2 rounded-full bg-surface-variant overflow-hidden"><div class="h-full" :class="couleurClasse(cls)" :style="'width:' + Math.round((p || 0) * 100) + '%'"></div></div>
<span class="w-10 text-right font-label-sm text-label-sm text-on-surface" x-text="Math.round((p || 0) * 100) + '%'"></span>
</div>
</template>
</div>
</template>
<template x-if="ia.facteurs && ia.facteurs.length">
<div>
<p class="font-label-sm text-label-sm text-on-surface-variant mb-1">Principaux facteurs</p>
<div class="flex flex-wrap gap-2">
<template x-for="f in ia.facteurs" :key="f.feature">
<span class="px-2 py-1 rounded-md bg-surface-container-high text-on-surface font-label-sm text-label-sm"><strong x-text="f.label"></strong> : <span x-text="f.valeur_affichee"></span></span>
</template>
</div>
</div>
</template>
</div>
</template>
</div>
