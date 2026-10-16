{{-- Carte météo « générale » affichée à droite du formulaire d'alerte.
     `$meteo` = App\Services\MeteoActuelle ou null (relevé initial, avant saisie).
     La carte est RÉACTIVE : le bouton « Météo + niveau » émet l'événement
     window « alerte-ia », qui met à jour la météo affichée (point/quartiers
     analysés) et le bloc Analyse IA (niveau, confiance, probabilités, facteurs). --}}
<div x-data="{
        ia: null,
        delta: null,
        derniereTemp: null,
        meteo: @js($meteo ? [
            'temperature' => round($meteo->temperature, 1),
            'condition' => $meteo->condition,
            'ville' => $meteo->ville,
            'ressentie' => $meteo->ressentie,
            'humidite' => $meteo->humidite,
            'vent' => $meteo->vent,
            'source' => $meteo->source,
            'general' => true,
            'lieu' => $meteoLieu ?? null,
        ] : null),
        num1(v) {
            return (v === null || v === undefined || v === '') ? '—' : Number(v).toLocaleString('fr-FR', { minimumFractionDigits: 1, maximumFractionDigits: 1 });
        },
        libelleClasse(cls) { return { normal: 'Aucune alerte', jaune: 'Vigilance jaune', orange: 'Vigilance orange', rouge: 'Vigilance rouge' }[cls] || cls; },
        couleurClasse(cls) { return { normal: 'bg-outline', jaune: 'bg-amber-500', orange: 'bg-orange-500', rouge: 'bg-red-600' }[cls] || 'bg-outline'; },
        couleurTexteClasse(cls) { return { jaune: 'text-amber-700', orange: 'text-orange-700', rouge: 'text-red-700' }[cls] || 'text-on-surface'; },
        appliquerMeteo(d) {
            if (! d || d.temperature === undefined) { return; }
            const t = Number(d.temperature);
            this.delta = (this.derniereTemp === null || this.derniereTemp === undefined) ? null : (t - this.derniereTemp);
            this.derniereTemp = t;
            this.meteo = {
                temperature: d.temperature,
                condition: d.condition,
                ville: d.ville,
                ressentie: d.ressentie,
                humidite: d.humidite,
                vent: d.vent,
                source: d.source,
                general: false,
                lieu: null,
            };
        }
    }"
     @alerte-ia.window="ia = $event.detail; appliquerMeteo($event.detail)"
     class="rounded-xl bg-surface-container-low/80 backdrop-blur-xl shadow-xl p-space-lg flex flex-col gap-4 lg:sticky lg:top-4">
<div class="flex items-center gap-2">
<span class="material-symbols-outlined text-primary text-[24px]">partly_cloudy_day</span>
<h2 class="font-title-md text-title-md text-on-surface font-semibold">Météo actuelle</h2>
</div>

<template x-if="meteo">
<div class="flex flex-col gap-4">
<div class="flex items-end gap-3">
<span class="font-headline-lg text-headline-lg text-on-surface leading-none" x-text="num1(meteo.temperature) + '°C'"></span>
<template x-if="meteo.condition"><span class="font-body-md text-body-md text-on-surface-variant pb-1" x-text="meteo.condition"></span></template>
<template x-if="delta !== null && delta !== 0">
<span class="pb-1 rounded-full px-2 py-0.5 font-label-sm text-label-sm font-semibold" :class="delta > 0 ? 'bg-red-100 text-red-700' : 'bg-sky-100 text-sky-700'" title="Écart vs le point précédent" x-text="(delta > 0 ? '▲ +' : '▼ ') + num1(delta) + ' °C'"></span>
</template>
</div>

<template x-if="meteo.ville">
<p class="font-body-sm text-body-sm text-on-surface-variant flex items-center gap-1"><span class="material-symbols-outlined text-[16px]">place</span><span x-text="meteo.ville"></span></p>
</template>

<div class="flex flex-col gap-2 rounded-lg bg-surface-container px-3 py-3">
<template x-if="meteo.ressentie !== null && meteo.ressentie !== undefined">
<div class="flex items-center justify-between font-body-sm text-body-sm">
<span class="text-on-surface-variant">Ressentie</span>
<span class="text-on-surface font-medium" x-text="num1(meteo.ressentie) + '°C'"></span>
</div>
</template>
<template x-if="meteo.humidite !== null && meteo.humidite !== undefined">
<div class="flex items-center justify-between font-body-sm text-body-sm">
<span class="text-on-surface-variant">Humidité</span>
<span class="text-on-surface font-medium" x-text="meteo.humidite + ' %'"></span>
</div>
</template>
<template x-if="meteo.vent !== null && meteo.vent !== undefined">
<div class="flex items-center justify-between font-body-sm text-body-sm">
<span class="text-on-surface-variant">Vent</span>
<span class="text-on-surface font-medium" x-text="Math.round(meteo.vent) + ' km/h'"></span>
</div>
</template>
</div>

<span class="self-start rounded-full bg-surface-container px-3 py-1 font-label-sm text-label-sm text-on-surface-variant" x-text="meteo.source === 'manuel' ? 'Saisie manuelle' : 'WeatherAPI'"></span>

<p class="font-body-sm text-body-sm text-on-surface-variant" x-text="meteo.general
    ? ('Relevé général' + (meteo.lieu ? ' · ' + meteo.lieu : '') + '. Sélectionnez un quartier ou posez un point sur la carte à gauche pour affiner la mesure.')
    : 'Relevé du point / des quartiers analysés.'"></p>
</div>
</template>

<template x-if="!meteo">
<div class="flex items-start gap-2 rounded-lg bg-surface-container px-3 py-3">
<span class="material-symbols-outlined text-on-surface-variant text-[20px] shrink-0">cloud_off</span>
<p class="font-body-sm text-body-sm text-on-surface-variant">Météo indisponible pour le moment (service injoignable ou clé absente). Vous pouvez saisir la température manuellement, ou utiliser « Météo + niveau ».</p>
</div>
</template>

{{-- Analyse IA (remplie par le bouton « Météo + niveau » du formulaire). --}}
<template x-if="ia && ia.ok">
<div class="flex flex-col gap-2 rounded-lg border border-primary-container/30 bg-primary-container/5 px-3 py-3">
<div class="flex items-center justify-between">
<span class="font-label-sm text-label-sm text-on-surface-variant uppercase tracking-wider">Analyse IA</span>
<span class="font-label-sm text-label-sm font-semibold" :class="ia.desaccord ? 'text-amber-700' : 'text-primary'" x-text="ia.desaccord ? 'Modèle ≠ règles' : (ia.modele_disponible ? 'Modèle + règles' : 'Règles seules')"></span>
</div>
<p class="font-body-sm text-body-sm text-on-surface" x-text="ia.raison"></p>
<div class="flex items-center justify-between font-body-sm text-body-sm">
<span class="text-on-surface-variant">Modèle (brut)</span>
<span class="text-on-surface font-medium" x-text="(ia.niveau_modele ? libelleClasse(ia.niveau_modele) : 'Aucune alerte') + ' · ' + Math.round((ia.confiance || 0) * 100) + ' %'"></span>
</div>
<div class="flex items-center justify-between font-body-sm text-body-sm">
<span class="text-on-surface-variant">Règles</span>
<span class="text-on-surface font-medium" x-text="libelleClasse(ia.niveau_regles || ia.niveau)"></span>
</div>
<div class="flex items-center justify-between font-body-sm text-body-sm">
<span class="text-on-surface-variant">Niveau retenu</span>
<span class="font-semibold" :class="couleurTexteClasse(ia.niveau)" x-text="libelleClasse(ia.niveau)"></span>
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
