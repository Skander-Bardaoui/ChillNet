<x-public-layout title="Alertes canicule">
<a href="{{ route('home') }}" class="inline-flex items-center gap-1 font-body-sm text-body-sm text-primary hover:underline"><span class="material-symbols-outlined text-[16px]">arrow_back</span>Retour à l'accueil</a>

{{-- Bandeau vigilance rouge (statique, démo) --}}
<section class="relative overflow-hidden rounded-xl bg-surface-container-low p-space-md md:p-space-lg shadow-xl">
<div class="absolute inset-y-0 left-0 w-2 bg-gradient-to-b from-error via-error to-error-container"></div>
<div class="flex flex-col lg:flex-row items-start lg:items-center justify-between gap-space-md pl-space-sm">
<div class="flex items-start gap-space-md">
<div class="relative flex items-center justify-center w-12 h-12 rounded-xl bg-error/15 text-error shrink-0">
<span class="material-symbols-outlined text-[26px]">warning</span>
<span class="absolute -top-1 -right-1 flex h-3 w-3"><span class="animate-ping absolute inline-flex h-full w-full rounded-full bg-error opacity-75"></span><span class="relative inline-flex rounded-full h-3 w-3 bg-error"></span></span>
</div>
<div class="flex flex-col gap-1">
<div class="flex items-center gap-2 flex-wrap">
<span class="px-2 py-0.5 rounded-full bg-error/15 text-error font-label-sm text-label-sm tracking-wider uppercase font-semibold">Vigilance rouge canicule</span>
<span class="font-body-sm text-body-sm text-on-surface-variant flex items-center gap-1"><span class="material-symbols-outlined text-[14px] text-primary">schedule</span>Actualisé il y a 4 min (démo)</span>
</div>
<p class="font-title-md text-title-md text-on-surface">Pic attendu à 16h30 (41°C). Restez au frais, hydratez-vous, prenez des nouvelles de vos voisins.</p>
<p class="font-body-sm text-body-sm text-on-surface-variant">Arrêté préfectoral n°2025-07 (démo) : accès gratuit aux piscines et climatisation renforcée des refuges municipaux.</p>
</div>
</div>
<div class="flex items-center gap-space-sm w-full lg:w-auto shrink-0">
<a href="tel:0800066666" class="flex-1 lg:flex-initial px-space-md py-2.5 rounded-lg bg-surface-container-high text-on-surface font-label-md text-label-md hover:bg-surface-variant transition-colors flex items-center justify-center gap-2"><span class="material-symbols-outlined text-[18px] text-primary">call</span><span>0800 06 66 66</span></a>
<a href="tel:15" class="flex-1 lg:flex-initial px-space-md py-2.5 rounded-lg bg-error text-on-error font-label-md text-label-md font-semibold hover:opacity-95 shadow-md flex items-center justify-center gap-2"><span class="material-symbols-outlined text-[18px]">emergency</span><span>Urgence : 15</span></a>
</div>
</div>
</section>

{{-- 3 cartes d'alertes statiques --}}
<section class="grid grid-cols-1 md:grid-cols-3 gap-space-md">
<article class="rounded-xl bg-surface-container-low p-space-md shadow-md flex flex-col gap-space-sm">
<div class="flex items-center justify-between">
<span class="px-2 py-0.5 rounded-full bg-tertiary-container/20 text-tertiary font-label-sm text-label-sm uppercase tracking-wider font-semibold flex items-center gap-1"><span class="material-symbols-outlined text-[14px]">thermostat</span>Vigilance jaune</span>
<span class="font-label-sm text-label-sm text-on-surface-variant">Seuil 35°C</span>
</div>
<h2 class="font-headline-sm text-headline-sm text-on-surface">Centre Historique — chaleur diurne</h2>
<p class="font-body-sm text-body-sm text-on-surface-variant">Du 24/09/2026 à 12h00 au 24/09/2026 à 19h00. Buvez de l'eau régulièrement, fermez volets et rideaux.</p>
<div class="flex items-center gap-2 font-body-sm text-body-sm text-on-surface-variant"><span class="material-symbols-outlined text-[16px] text-primary">location_on</span><span>Quartier Centre Historique</span></div>
<div class="flex items-center gap-2 pt-1"><a href="#conseils-jaune" class="text-primary font-label-md text-label-md hover:underline">Voir les réflexes</a><a href="tel:0800066666" class="ml-auto inline-flex items-center gap-1 font-label-md text-label-md text-on-surface-variant hover:text-primary"><span class="material-symbols-outlined text-[16px]">call</span>0800 06 66 66</a></div>
</article>
<article class="rounded-xl bg-surface-container-low p-space-md shadow-md flex flex-col gap-space-sm">
<div class="flex items-center justify-between">
<span class="px-2 py-0.5 rounded-full bg-tertiary-container/25 text-tertiary font-label-sm text-label-sm uppercase tracking-wider font-semibold flex items-center gap-1"><span class="material-symbols-outlined text-[14px]">wb_sunny</span>Vigilance orange</span>
<span class="font-label-sm text-label-sm text-on-surface-variant">Seuil 38°C</span>
</div>
<h2 class="font-headline-sm text-headline-sm text-on-surface">Oasis Nord — pic prolongé</h2>
<p class="font-body-sm text-body-sm text-on-surface-variant">Du 24/09/2026 à 11h00 au 25/09/2026 à 21h00. Salle climatisée ouverte jusqu'à 22h00, refuge à 350 m.</p>
<div class="flex items-center gap-2 font-body-sm text-body-sm text-on-surface-variant"><span class="material-symbols-outlined text-[16px] text-primary">location_on</span><span>Quartier Oasis Nord</span></div>
<div class="flex items-center gap-2 pt-1"><a href="#conseils-orange" class="text-primary font-label-md text-label-md hover:underline">Voir les réflexes</a><a href="tel:15" class="ml-auto inline-flex items-center gap-1 font-label-md text-label-md text-on-surface-variant hover:text-primary"><span class="material-symbols-outlined text-[16px]">call</span>Urgence 15</a></div>
</article>
<article class="rounded-xl bg-surface-container-low p-space-md shadow-md flex flex-col gap-space-sm border border-error/30">
<div class="flex items-center justify-between">
<span class="px-2 py-0.5 rounded-full bg-error/15 text-error font-label-sm text-label-sm uppercase tracking-wider font-semibold flex items-center gap-1"><span class="material-symbols-outlined text-[14px]">warning</span>Vigilance rouge</span>
<span class="font-label-sm text-label-sm text-on-surface-variant">Seuil 41°C</span>
</div>
<h2 class="font-headline-sm text-headline-sm text-on-surface">Berges Sud — canicule extrême</h2>
<p class="font-body-sm text-body-sm text-on-surface-variant">Du 24/09/2026 à 10h00 au 26/09/2026 à 20h00. Protocole foyer activé : restez au frais, signalez les voisins isolés.</p>
<div class="flex items-center gap-2 font-body-sm text-body-sm text-on-surface-variant"><span class="material-symbols-outlined text-[16px] text-primary">location_on</span><span>Quartier Berges Sud</span></div>
<div class="flex items-center gap-2 pt-1"><a href="#conseils-rouge" class="text-primary font-label-md text-label-md hover:underline">Voir les réflexes</a><a href="tel:15" class="ml-auto inline-flex items-center gap-1 font-label-md text-label-md text-error hover:underline"><span class="material-symbols-outlined text-[16px]">emergency</span>Appeler le 15</a></div>
</article>
</section>

{{-- Rappel numéros --}}
<section class="rounded-xl bg-surface-container-low p-space-md shadow-sm flex flex-wrap items-center gap-space-md">
<span class="material-symbols-outlined text-primary text-[22px]">info</span>
<p class="font-body-sm text-body-sm text-on-surface-variant">En cas de malaise : appelez le <a href="tel:15" class="text-primary font-semibold hover:underline">15</a> ou le <a href="tel:112" class="text-primary font-semibold hover:underline">112</a>. Plateforme canicule : <a href="tel:0800066666" class="text-primary font-semibold hover:underline">0800 06 66 66</a>.</p>
<a href="{{ route('home') }}" class="ml-auto inline-flex items-center gap-1 font-label-md text-label-md text-primary hover:underline"><span class="material-symbols-outlined text-[16px]">arrow_back</span>Retour à l'accueil</a>
</section>
</x-public-layout>
