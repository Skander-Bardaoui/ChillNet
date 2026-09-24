<x-app-layout>
<x-slot name="header">
<div class="flex flex-col md:flex-row md:items-center justify-between gap-3">
<div>
<p class="font-label-sm text-label-sm text-primary uppercase tracking-wider font-semibold">Tableau de bord résident — ChillNet</p>
<h1 class="font-headline-lg text-headline-lg text-on-surface">Bonjour {{ auth()->user()->name }}</h1>
<p class="font-body-sm text-body-sm text-on-surface-variant mt-1">
@if(auth()->user()->residence)
{{ auth()->user()->residence->nom }}@if(auth()->user()->residence->quartier) — Quartier {{ auth()->user()->residence->quartier->nom }}@endif
· <a href="{{ route('profile.edit') }}" class="text-primary hover:underline">Modifier mon foyer</a>
@else
<a href="{{ route('profile.edit') }}" class="text-primary hover:underline">Renseignez votre résidence pour personnaliser la vigilance →</a>
@endif
</p>
</div>
@if(auth()->user()->isAdmin() || auth()->user()->isGestionnaire())
<a href="{{ route('back.dashboard') }}" class="shrink-0 inline-flex items-center gap-2 px-4 py-2.5 rounded-lg bg-surface-variant text-on-surface font-label-md text-label-md hover:bg-surface-bright transition-colors">
<span class="material-symbols-outlined text-[18px]">admin_panel_settings</span>
<span>Espace gestionnaire</span>
</a>
@endif
</div>
</x-slot>

{{-- Accès modules (vitrines par module) --}}
@php
    $modulesAcces = [
        ['href' => route('alertes.index'), 'icon' => 'thermostat', 'label' => 'Alertes'],
        ['href' => route('coupures.index'), 'icon' => 'electric_bolt', 'label' => 'Coupures'],
        ['href' => route('equipements.index'), 'icon' => 'medical_services', 'label' => 'Équipements'],
        ['href' => route('signalements.index'), 'icon' => 'report', 'label' => 'Signalements'],
        ['href' => route('conseils'), 'icon' => 'health_and_safety', 'label' => 'Conseils'],
    ];
@endphp
<nav aria-label="Mes modules" class="grid grid-cols-2 sm:grid-cols-3 xl:grid-cols-5 gap-space-sm">
@foreach ($modulesAcces as $mod)
<a href="{{ $mod['href'] }}" class="flex items-center gap-2 rounded-xl bg-surface-container/70 px-3 py-2.5 shadow hover:border hover:border-primary-container/40 transition-colors">
<span class="material-symbols-outlined text-primary text-[20px]">{{ $mod['icon'] }}</span>
<span class="font-label-md text-label-md text-on-surface">{{ $mod['label'] }}</span>
</a>
@endforeach
</nav>

{{-- Fond lumineux d'ambiance --}}
<div class="relative w-full">
<div class="absolute -top-32 left-1/4 w-96 h-96 bg-primary-container/10 rounded-full blur-[120px] pointer-events-none"></div>
<div class="absolute top-12 right-12 w-80 h-80 bg-tertiary-container/10 rounded-full blur-[140px] pointer-events-none"></div>

{{-- Bandeau protocole vigilance --}}
<section class="relative overflow-hidden rounded-xl bg-surface-container-high/90 backdrop-blur-xl shadow-xl">
<div class="absolute inset-y-0 left-0 w-2 bg-gradient-to-b from-tertiary-container via-tertiary-fixed-dim to-error"></div>
<div class="p-space-md md:p-space-lg flex flex-col lg:flex-row items-start lg:items-center justify-between gap-space-md">
<div class="flex items-start gap-space-md">
<div class="relative flex items-center justify-center w-12 h-12 rounded-xl bg-tertiary-container/15 text-tertiary-fixed shrink-0">
<span class="material-symbols-outlined text-[26px]">warning</span>
<span class="absolute -top-1 -right-1 flex h-3 w-3">
<span class="animate-ping absolute inline-flex h-full w-full rounded-full bg-tertiary-container opacity-75"></span>
<span class="relative inline-flex rounded-full h-3 w-3 bg-tertiary-fixed"></span>
</span>
</div>
<div class="flex flex-col gap-0.5">
<div class="flex items-center gap-2 flex-wrap">
<span class="px-2 py-0.5 rounded-full bg-tertiary-container/20 text-tertiary-fixed font-label-sm text-label-sm tracking-wider uppercase font-semibold">Protocole Vigilance Rouge Canicule</span>
<span class="font-body-sm text-body-sm text-on-surface-variant flex items-center gap-1">
<span class="material-symbols-outlined text-[14px] text-primary">schedule</span> Actualisé il y a 4 min
</span>
</div>
<p class="font-title-md text-title-md text-on-surface">Vigilance Canicule Extrême en cours — Pic attendu à 16h30 (<span class="text-tertiary-fixed font-bold">41°C</span>). Risque de surcharge réseau entre 17h00 et 19h30.</p>
<p class="font-body-sm text-body-sm text-on-surface-variant">Arrêté préfectoral n°2025-07 : Accès gratuit aux piscines et climatisation renforcée des refuges municipaux.</p>
</div>
</div>
<div class="flex items-center gap-space-sm w-full lg:w-auto shrink-0 pt-2 lg:pt-0">
<a href="tel:190" class="flex-1 lg:flex-initial px-space-md py-2.5 rounded-lg bg-surface-container text-primary font-label-md text-label-md hover:bg-surface-variant transition-colors flex items-center justify-center gap-2">
<span class="material-symbols-outlined text-[18px]">radio</span>
<span>Point de Situation — 15</span>
</a>
<a href="#quick-actions" class="flex-1 lg:flex-initial px-space-md py-2.5 rounded-lg bg-primary-container text-on-primary-container font-label-md text-label-md hover:opacity-95 shadow-md flex items-center justify-center gap-2 font-semibold">
<span class="material-symbols-outlined text-[18px]">shield</span>
<span>Activer Protocole Foyer</span>
</a>
</div>
</div>
</section>

{{-- 4 cartes télémétrie --}}
<section class="grid grid-cols-1 sm:grid-cols-2 xl:grid-cols-4 gap-space-md mt-space-lg">
<div class="relative overflow-hidden rounded-xl bg-surface-container/70 backdrop-blur-md p-space-md flex flex-col justify-between shadow-md">
<div class="flex items-center justify-between text-on-surface-variant">
<span class="font-label-md text-label-md uppercase tracking-wider text-on-surface-variant">Température Locale</span>
<span class="flex items-center gap-1 text-tertiary-container font-label-sm text-label-sm font-semibold">
<span class="material-symbols-outlined text-[16px]">arrow_upward</span> +1.8°C / 1h
</span>
</div>
<div class="my-space-sm flex items-baseline gap-space-xs">
<span class="font-display-lg text-display-lg text-on-surface tracking-tighter">38.2</span>
<span class="font-title-md text-title-md text-on-surface-variant font-medium">°C</span>
<span class="ml-auto px-2 py-0.5 rounded-md bg-surface-variant text-tertiary-fixed font-label-sm text-label-sm">Ressenti 42°C</span>
</div>
<div class="pt-2 flex flex-col gap-1.5">
<div class="flex justify-between items-center text-on-surface-variant font-body-sm text-body-sm">
<span>08:00 (29°C)</span>
<span class="text-tertiary-fixed font-medium">Pic 16:30 (41°C)</span>
<span>23:00 (33°C)</span>
</div>
<svg class="w-full h-8 text-tertiary-container" fill="none" viewBox="0 0 100 24">
<path d="M0 20 Q 25 18, 45 10 T 70 3 T 100 12" fill="none" stroke="currentColor" stroke-linecap="round" stroke-width="2.5"></path>
<circle class="fill-error animate-pulse" cx="70" cy="3" r="2.5"></circle>
</svg>
</div>
</div>

<div class="relative overflow-hidden rounded-xl bg-surface-container/70 backdrop-blur-md p-space-md flex flex-col justify-between shadow-md">
<div class="flex items-center justify-between text-on-surface-variant">
<span class="font-label-md text-label-md uppercase tracking-wider text-on-surface-variant">Tension Réseau</span>
<span class="px-2 py-0.5 rounded-md bg-tertiary-container/15 text-tertiary-fixed font-label-sm text-label-sm font-semibold">Éco-Vigilance</span>
</div>
<div class="my-space-sm flex items-baseline gap-space-xs">
<span class="font-display-lg text-display-lg text-on-surface tracking-tighter">88</span>
<span class="font-title-md text-title-md text-on-surface-variant font-medium">%</span>
<span class="ml-auto font-body-sm text-body-sm text-on-surface-variant">Capacité Max : 9.4 GW</span>
</div>
<div class="pt-2 flex flex-col gap-1">
<div class="w-full bg-surface-variant h-2.5 rounded-full overflow-hidden flex">
<div class="bg-primary-container h-full w-[65%]"></div>
<div class="bg-tertiary-fixed-dim h-full w-[23%] animate-pulse"></div>
<div class="bg-surface-variant h-full w-[12%]"></div>
</div>
<div class="flex justify-between items-center text-on-surface-variant font-body-sm text-body-sm pt-0.5">
<span>Seuil Nominal &lt;75%</span>
<span class="text-tertiary-container font-semibold">Seuil Critique 92%</span>
</div>
</div>
</div>

<div class="relative overflow-hidden rounded-xl bg-surface-container/70 backdrop-blur-md p-space-md flex flex-col justify-between shadow-md">
<div class="flex items-center justify-between text-on-surface-variant">
<span class="font-label-md text-label-md uppercase tracking-wider text-on-surface-variant">Risque Délestage</span>
<span class="flex items-center gap-1.5 text-on-surface-variant font-label-sm text-label-sm">
<span class="w-2 h-2 rounded-full bg-tertiary-container animate-ping"></span> Modéré
</span>
</div>
<div class="my-space-sm flex flex-col">
<div class="flex items-baseline gap-2">
<span class="font-headline-lg text-headline-lg text-tertiary-fixed font-semibold tracking-tight">18h00 — 20h00</span>
</div>
<span class="font-body-sm text-body-sm text-on-surface-variant mt-0.5">Secteur : Bâtonnier &amp; Faubourg Nord</span>
</div>
<div class="pt-2 flex items-center justify-between bg-surface-container-high px-space-sm py-1.5 rounded-lg text-on-surface-variant font-body-sm text-body-sm">
<span class="flex items-center gap-1 text-primary">
<span class="material-symbols-outlined text-[16px]">battery_charging_full</span> Batterie quartier
</span>
<span class="font-semibold text-on-surface">94% chargée</span>
</div>
</div>

<div class="relative overflow-hidden rounded-xl bg-surface-container/70 backdrop-blur-md p-space-md flex flex-col justify-between shadow-md">
<div class="flex items-center justify-between text-on-surface-variant">
<span class="font-label-md text-label-md uppercase tracking-wider text-on-surface-variant">Refuges Climat</span>
<span class="px-2 py-0.5 rounded-md bg-primary-container/20 text-primary-fixed-dim font-label-sm text-label-sm font-semibold">&lt;10 min à pied</span>
</div>
<div class="my-space-sm flex items-baseline gap-space-xs">
<span class="font-display-lg text-display-lg text-primary tracking-tighter">6</span>
<span class="font-title-md text-title-md text-on-surface font-medium">Ouverts</span>
<span class="ml-auto text-on-surface-variant font-body-sm text-body-sm">Places libres : 180+</span>
</div>
<div class="pt-2 flex items-center justify-between text-on-surface-variant font-body-sm text-body-sm">
<span class="truncate">Le plus proche : <strong class="text-on-surface">Médiathèque Nord (250m)</strong></span>
<a class="text-primary hover:underline shrink-0 ml-1" href="{{ route('refuges.index') }}">Voir</a>
</div>
</div>
</section>

{{-- Split principal --}}
<div class="grid grid-cols-1 lg:grid-cols-12 gap-space-lg items-start mt-space-lg">
<div class="lg:col-span-8 flex flex-col gap-space-lg">

{{-- Chronologie 24h --}}
<div class="rounded-xl bg-surface-container/80 backdrop-blur-xl p-space-md md:p-space-lg shadow-xl flex flex-col gap-space-md">
<div class="flex flex-col sm:flex-row sm:items-center justify-between gap-space-sm">
<div class="flex flex-col">
<span class="font-label-sm text-label-sm text-primary uppercase tracking-wider font-semibold">Planification Anticipée</span>
<h2 class="font-headline-sm text-headline-sm text-on-surface">Chronologie 24h : Températures &amp; Stress Électrique</h2>
</div>
<div class="flex items-center gap-3 text-body-sm font-body-sm flex-wrap">
<span class="flex items-center gap-1.5 text-on-surface-variant"><span class="w-3 h-3 rounded-full bg-primary-container"></span> Frais &amp; Sécurisé</span>
<span class="flex items-center gap-1.5 text-on-surface-variant"><span class="w-3 h-3 rounded-full bg-tertiary-container"></span> Tension Réseau</span>
<span class="flex items-center gap-1.5 text-on-surface-variant"><span class="w-3 h-3 rounded-full bg-error"></span> Pic Canicule</span>
</div>
</div>
<div class="w-full bg-surface-container-low rounded-xl p-space-md flex flex-col gap-space-md">
<div class="grid grid-cols-6 sm:grid-cols-8 gap-2 overflow-x-auto pb-1">
<div class="flex flex-col items-center p-2 rounded-lg bg-surface-container-high/60 text-center gap-1">
<span class="font-label-sm text-label-sm text-on-surface-variant">10:00</span>
<span class="material-symbols-outlined text-[18px] text-tertiary-container">wb_sunny</span>
<span class="font-title-md text-title-md text-on-surface font-semibold">32°</span>
<span class="px-1.5 py-0.5 rounded text-[10px] bg-surface-variant text-on-surface-variant">62% ch.</span>
</div>
<div class="flex flex-col items-center p-2 rounded-lg bg-surface-container-high/60 text-center gap-1">
<span class="font-label-sm text-label-sm text-on-surface-variant">12:00</span>
<span class="material-symbols-outlined text-[18px] text-tertiary-fixed-dim">thermostat</span>
<span class="font-title-md text-title-md text-on-surface font-semibold">35°</span>
<span class="px-1.5 py-0.5 rounded text-[10px] bg-surface-variant text-on-surface-variant">74% ch.</span>
</div>
<div class="flex flex-col items-center p-2 rounded-lg bg-surface-container-high/60 text-center gap-1">
<span class="font-label-sm text-label-sm text-on-surface-variant">14:00</span>
<span class="material-symbols-outlined text-[18px] text-tertiary-fixed">sunny</span>
<span class="font-title-md text-title-md text-tertiary-fixed font-semibold">39°</span>
<span class="px-1.5 py-0.5 rounded text-[10px] bg-surface-variant text-tertiary-fixed">81% ch.</span>
</div>
<div class="flex flex-col items-center p-2 rounded-lg bg-tertiary-container/15 text-center gap-1 shadow-[0_0_12px_rgba(255,199,105,0.2)]">
<span class="font-label-sm text-label-sm text-tertiary-fixed font-semibold">16:30</span>
<span class="material-symbols-outlined text-[18px] text-error">local_fire_department</span>
<span class="font-title-md text-title-md text-error font-bold">41°</span>
<span class="px-1.5 py-0.5 rounded text-[10px] bg-error-container text-on-error-container font-semibold">PIC</span>
</div>
<div class="flex flex-col items-center p-2 rounded-lg bg-error-container/20 text-center gap-1">
<span class="font-label-sm text-label-sm text-error font-semibold">18:00</span>
<span class="material-symbols-outlined text-[18px] text-tertiary-fixed">offline_bolt</span>
<span class="font-title-md text-title-md text-on-surface font-semibold">39.5°</span>
<span class="px-1.5 py-0.5 rounded text-[10px] bg-error-container text-on-error-container font-semibold">91% MAX</span>
</div>
<div class="flex flex-col items-center p-2 rounded-lg bg-surface-container-high/60 text-center gap-1">
<span class="font-label-sm text-label-sm text-on-surface-variant">20:00</span>
<span class="material-symbols-outlined text-[18px] text-tertiary-container">partly_cloudy_day</span>
<span class="font-title-md text-title-md text-on-surface font-semibold">37°</span>
<span class="px-1.5 py-0.5 rounded text-[10px] bg-surface-variant text-tertiary-fixed">84% ch.</span>
</div>
<div class="flex flex-col items-center p-2 rounded-lg bg-primary-container/15 text-center gap-1">
<span class="font-label-sm text-label-sm text-primary font-semibold">22:00</span>
<span class="material-symbols-outlined text-[18px] text-primary">bedtime</span>
<span class="font-title-md text-title-md text-on-surface font-semibold">33°</span>
<span class="px-1.5 py-0.5 rounded text-[10px] bg-primary/20 text-primary font-semibold">ÉCO</span>
</div>
<div class="flex flex-col items-center p-2 rounded-lg bg-surface-container-high/60 text-center gap-1">
<span class="font-label-sm text-label-sm text-on-surface-variant">02:00</span>
<span class="material-symbols-outlined text-[18px] text-primary">nightlight</span>
<span class="font-title-md text-title-md text-on-surface font-semibold">29°</span>
<span class="px-1.5 py-0.5 rounded text-[10px] bg-surface-variant text-on-surface-variant">45% ch.</span>
</div>
</div>
<div class="flex flex-col gap-2 pt-2">
<div class="flex items-center gap-space-sm p-space-sm rounded-lg bg-primary-container/10 text-on-surface font-body-sm text-body-sm">
<span class="material-symbols-outlined text-primary text-[20px] shrink-0">bolt</span>
<span class="flex-1"><strong class="text-primary font-semibold">Fenêtre Optimale Recommandée :</strong> Chargez vos téléphones, batteries de secours et accumulateurs de froid entre <span class="text-primary underline font-medium">10h00 et 13h00</span> ou après <span class="text-primary underline font-medium">22h00</span>.</span>
</div>
<div class="flex items-center gap-space-sm p-space-sm rounded-lg bg-error-container/20 text-on-surface font-body-sm text-body-sm">
<span class="material-symbols-outlined text-error text-[20px] shrink-0">power_off</span>
<span class="flex-1"><strong class="text-error font-semibold">Plage Critique d'Alerte :</strong> Évitez impérativement tout équipement énergivore (fours, lave-linge, recharge VE) entre <span class="text-error font-bold">17h00 et 20h00</span> pour préserver le transformateur de quartier.</span>
</div>
</div>
</div>
<div class="flex flex-col gap-2 pt-space-xs">
<div class="flex items-center justify-between">
<span class="font-title-md text-title-md text-on-surface">Zones d'Ombre &amp; Refuges Actifs (Périmètre 800m)</span>
<span class="font-label-sm text-label-sm text-primary flex items-center gap-1">
<span class="material-symbols-outlined text-[16px]">navigation</span> Géolocalisation active
</span>
</div>
<div class="w-full h-56 rounded-xl bg-surface-container-lowest relative overflow-hidden flex items-end p-space-md shadow-inner">
<div class="relative z-10 w-full flex flex-wrap items-center justify-between gap-2 p-space-sm rounded-lg bg-surface-container-lowest/85 backdrop-blur-md">
<div class="flex items-center gap-space-sm">
<span class="w-3 h-3 rounded-full bg-primary-container shadow-[0_0_8px_#1B77BA]"></span>
<span class="font-body-sm text-body-sm text-on-surface font-medium">Parc des Remparts : Canopée active (-4°C mesuré)</span>
</div>
<div class="flex items-center gap-2">
<span class="px-2 py-0.5 rounded bg-surface-variant font-label-sm text-label-sm text-on-surface-variant">3 Bornes Eau Potable</span>
<a href="{{ route('refuges.index') }}" class="px-2.5 py-1 rounded bg-primary-container text-on-primary-container font-label-sm text-label-sm font-semibold hover:opacity-90 transition-opacity">Itinéraire Ombragé</a>
</div>
</div>
</div>
<div class="flex flex-wrap items-center gap-2">
<a href="{{ route('refuges.index') }}" class="font-body-sm text-body-sm text-primary hover:underline">Voir la carte des refuges →</a>
<a href="{{ route('conseils') }}" class="font-body-sm text-body-sm text-primary hover:underline">Consulter les conseils canicule →</a>
</div>
</div>
</div>

{{-- Fil direct citoyen --}}
<div class="rounded-xl bg-surface-container/70 backdrop-blur-md p-space-md md:p-space-lg shadow-md flex flex-col gap-space-md">
<div class="flex items-center justify-between">
<div class="flex items-center gap-2">
<span class="material-symbols-outlined text-primary text-[22px]">feed</span>
<h3 class="font-headline-sm text-headline-sm text-on-surface">Fil Direct Citoyen &amp; Municipal</h3>
</div>
<span class="px-2 py-0.5 rounded-full bg-surface-variant font-label-sm text-label-sm text-on-surface-variant">4 nouveaux messages</span>
</div>
<div class="flex flex-col gap-space-sm divide-y-0">
<div class="flex items-start gap-space-md p-space-sm rounded-lg bg-surface-container-high/40 hover:bg-surface-container-high/80 transition-colors">
<div class="w-9 h-9 rounded-lg bg-primary-container/20 text-primary flex items-center justify-center shrink-0">
<span class="material-symbols-outlined text-[20px]">account_balance</span>
</div>
<div class="flex flex-col flex-1 gap-0.5">
<div class="flex items-center justify-between">
<span class="font-title-md text-title-md text-on-surface font-medium">Cellule de Crise Municipale</span>
<span class="font-label-sm text-label-sm text-on-surface-variant">Il y a 12 min</span>
</div>
<p class="font-body-md text-body-md text-on-surface-variant">La médiathèque municipale climatisée reste exceptionnellement ouverte ce soir jusqu'à <strong class="text-on-surface">22h00</strong>. Distribution d'eau fraîche et prises électriques accessibles.</p>
</div>
</div>
<div class="flex items-start gap-space-md p-space-sm rounded-lg bg-surface-container-high/40 hover:bg-surface-container-high/80 transition-colors">
<div class="w-9 h-9 rounded-lg bg-tertiary-container/20 text-tertiary-fixed flex items-center justify-center shrink-0">
<span class="material-symbols-outlined text-[20px]">volunteer_activism</span>
</div>
<div class="flex flex-col flex-1 gap-0.5">
<div class="flex items-center justify-between">
<span class="font-title-md text-title-md text-on-surface font-medium">Claire D. — Résidence Les Micocouliers</span>
<span class="font-label-sm text-label-sm text-on-surface-variant">Il y a 28 min</span>
</div>
<p class="font-body-md text-body-md text-on-surface-variant">Pièce fraîche en sous-sol disponible pour les personnes âgées du quartier ou isolées sans ventilateur (étage R-1 avec brumisateur). N'hésitez pas à toquer au n°14.</p>
<div class="flex items-center gap-2 pt-1">
<a href="{{ route('conseils') }}" class="px-2 py-0.5 rounded bg-surface-variant text-primary font-label-sm text-label-sm hover:underline">Entraide Quartier</a>
<span class="text-on-surface-variant font-body-sm text-body-sm">3 voisins ont remercié</span>
</div>
</div>
</div>
<div class="flex items-start gap-space-md p-space-sm rounded-lg bg-surface-container-high/40 hover:bg-surface-container-high/80 transition-colors">
<div class="w-9 h-9 rounded-lg bg-secondary-container/30 text-secondary flex items-center justify-center shrink-0">
<span class="material-symbols-outlined text-[20px]">electric_bolt</span>
</div>
<div class="flex flex-col flex-1 gap-0.5">
<div class="flex items-center justify-between">
<span class="font-title-md text-title-md text-on-surface font-medium">Régie Énergie Territoire</span>
<span class="font-label-sm text-label-sm text-on-surface-variant">Il y a 54 min</span>
</div>
<p class="font-body-md text-body-md text-on-surface-variant">Transformateur Rue Haute stabilisé. La baisse collective de consommation opérée par 420 foyers a permis d'éviter la bascule en délestage tournant. Merci pour vos gestes éco-responsables !</p>
</div>
</div>
</div>
</div>
</div>

{{-- Colonne droite : dispositif + réflexes --}}
<div class="lg:col-span-4 flex flex-col gap-space-lg" id="quick-actions">
<div class="rounded-xl bg-gradient-to-b from-surface-container-high to-surface-container p-space-md md:p-space-lg shadow-xl relative overflow-hidden">
<div class="absolute -right-12 -top-12 w-40 h-40 bg-primary-container/20 rounded-full blur-3xl pointer-events-none"></div>
<div class="flex flex-col gap-space-sm">
<span class="font-label-sm text-label-sm text-primary uppercase tracking-wider font-semibold">Dispositif d'Urgence Foyer</span>
<h3 class="font-headline-sm text-headline-sm text-on-surface">Mode Vigilance Énergie</h3>
<p class="font-body-sm text-body-sm text-on-surface-variant">En un clic, basculez vos objets connectés, éteignez les veilles et modulez votre rafraîchissement pour alléger le réseau local de <strong class="text-primary font-medium">-35%</strong>.</p>
<div class="mt-space-sm p-space-sm rounded-xl bg-surface-container-lowest/80 flex items-center justify-between gap-space-sm">
<div class="flex items-center gap-space-sm">
<div class="w-10 h-10 rounded-lg bg-primary-container/20 text-primary flex items-center justify-center" id="status-bulb">
<span class="material-symbols-outlined text-[24px]">energy_savings_leaf</span>
</div>
<div class="flex flex-col">
<span class="font-title-md text-title-md text-on-surface font-semibold" id="status-title">Mode Actif</span>
<span class="font-body-sm text-body-sm text-primary" id="status-desc">Consommation réduite de 35%</span>
</div>
</div>
<button aria-checked="true" class="relative inline-flex h-7 w-12 shrink-0 cursor-pointer rounded-full bg-primary-container p-0.5 transition-colors duration-200 ease-in-out focus:outline-none" id="toggle-energy-mode" role="switch">
<span class="pointer-events-none inline-block h-6 w-6 transform translate-x-5 rounded-full bg-on-primary-container shadow ring-0 transition duration-200 ease-in-out" id="toggle-knob"></span>
</button>
</div>
<div class="flex items-center justify-between text-body-sm font-body-sm text-on-surface-variant pt-1 px-1">
<span>Économie estimée : <strong>~1.4 kWh/jour</strong></span>
<span class="text-tertiary-fixed font-medium">Impact réseau : Fort</span>
</div>
<a href="{{ route('conseils') }}" class="font-body-sm text-body-sm text-primary hover:underline">Voir le protocole complet →</a>
</div>
</div>

<div class="rounded-xl bg-surface-container/70 backdrop-blur-md p-space-md md:p-space-lg shadow-md flex flex-col gap-space-md">
<div class="flex items-center justify-between">
<h3 class="font-title-md text-title-md text-on-surface">Actions Réflexes du Foyer</h3>
<span class="font-label-sm text-label-sm px-2 py-0.5 rounded-full bg-surface-container-high text-primary font-semibold" id="checklist-counter">2 / 4 faits</span>
</div>
<p class="font-body-sm text-body-sm text-on-surface-variant">Gestes essentiels validés par la sécurité civile pour maintenir votre logement tempéré sans surcharger la ligne.</p>
<div class="flex flex-col gap-space-sm" id="action-list">
<label class="group flex items-start gap-space-sm p-space-sm rounded-lg bg-surface-container-high/60 hover:bg-surface-container-high cursor-pointer transition-colors">
<input checked class="mt-1 h-5 w-5 rounded bg-surface-container-lowest text-primary-container focus:ring-0 accent-primary-container cursor-pointer" type="checkbox" />
<div class="flex flex-col flex-1">
<span class="font-body-md text-body-md text-on-surface font-medium group-hover:text-primary transition-colors">Fermer volets et stores dès 10h00</span>
<span class="font-body-sm text-body-sm text-on-surface-variant">Bloque jusqu'à 70% de la chaleur radiative directe.</span>
</div>
<span class="material-symbols-outlined text-primary text-[20px] shrink-0">check_circle</span>
</label>
<label class="group flex items-start gap-space-sm p-space-sm rounded-lg bg-surface-container-high/60 hover:bg-surface-container-high cursor-pointer transition-colors">
<input checked class="mt-1 h-5 w-5 rounded bg-surface-container-lowest text-primary-container focus:ring-0 accent-primary-container cursor-pointer" type="checkbox" />
<div class="flex flex-col flex-1">
<span class="font-body-md text-body-md text-on-surface font-medium group-hover:text-primary transition-colors">Remplir réserves d'eau fraîche &amp; glaces</span>
<span class="font-body-sm text-body-sm text-on-surface-variant">2L par personne stockés au frais avant le pic.</span>
</div>
<span class="material-symbols-outlined text-primary text-[20px] shrink-0">check_circle</span>
</label>
<label class="group flex items-start gap-space-sm p-space-sm rounded-lg bg-surface-container-high/60 hover:bg-surface-container-high cursor-pointer transition-colors">
<input class="mt-1 h-5 w-5 rounded bg-surface-container-lowest text-primary-container focus:ring-0 accent-primary-container cursor-pointer" type="checkbox" />
<div class="flex flex-col flex-1">
<span class="font-body-md text-body-md text-on-surface font-medium group-hover:text-primary transition-colors">Décaler le lave-linge &amp; four après 22h00</span>
<span class="font-body-sm text-body-sm text-on-surface-variant">Soulage le créneau haute tension de 18h-20h.</span>
</div>
<span class="material-symbols-outlined text-outline-variant text-[20px] shrink-0">radio_button_unchecked</span>
</label>
<label class="group flex items-start gap-space-sm p-space-sm rounded-lg bg-surface-container-high/60 hover:bg-surface-container-high cursor-pointer transition-colors">
<input class="mt-1 h-5 w-5 rounded bg-surface-container-lowest text-primary-container focus:ring-0 accent-primary-container cursor-pointer" type="checkbox" />
<div class="flex flex-col flex-1">
<span class="font-body-md text-body-md text-on-surface font-medium group-hover:text-primary transition-colors">Prendre des nouvelles d'un voisin vulnérable</span>
<span class="font-body-sm text-body-sm text-on-surface-variant">S'assurer de l'hydratation et du ventilateur en marche.</span>
</div>
<span class="material-symbols-outlined text-outline-variant text-[20px] shrink-0">radio_button_unchecked</span>
</label>
</div>
<div class="mt-space-xs p-space-sm rounded-lg bg-surface-container-lowest flex items-center justify-between">
<div class="flex items-center gap-2">
<span class="material-symbols-outlined text-error text-[20px]">phone_in_talk</span>
<div class="flex flex-col">
<span class="font-label-sm text-label-sm text-on-surface font-semibold">Numéro Vert Canicule Ville</span>
<span class="font-body-sm text-body-sm text-on-surface-variant">0 800 06 66 66 (Appel gratuit)</span>
</div>
</div>
<a class="px-2.5 py-1 rounded bg-surface-variant text-on-surface font-label-sm text-label-sm hover:bg-surface-bright transition-colors font-medium" href="tel:0800066666">Appeler</a>
</div>
</div>

<div class="relative overflow-hidden rounded-xl bg-surface-container-low shadow-md group">
<div class="w-full h-44 bg-cover bg-center transition-transform duration-500 group-hover:scale-105" style="background-image: url('https://lh3.googleusercontent.com/aida-public/AB6AXuANSt3QqGftuyYqv_H51fODIGAhdEDsHStizB2vqO7_dKIqP4mbmRv99DkXI326US4MtY-BJAyr3bOvXO8X3N0q_bMMPJMsjYbsZxwiAjBn8PcJlk6lgnUdUPa-ZvTMyMGrwc41hAJ6xK9yKsStRDXGi9bc-iGjT1ompWaVOCgCw7mr1qjzVQpCq-Yv7hZOIPEkaJUsMYAehUwRQ4zXWP7lEtf8jOihr5R4JWkL-R0F0VBSmv5bLqC4zA')">
<div class="w-full h-full bg-gradient-to-t from-surface-container-lowest via-surface-container-lowest/50 to-transparent p-space-md flex flex-col justify-end">
<div class="flex items-center gap-1.5 text-primary font-label-sm text-label-sm font-semibold uppercase">
<span class="material-symbols-outlined text-[16px]">park</span> Oasis Fraîcheur Active
</div>
<h4 class="font-title-md text-title-md text-on-surface font-semibold">Parc des Remparts &amp; Canopée</h4>
<p class="font-body-sm text-body-sm text-on-surface-variant">Brumisateurs en service continu • Wifi &amp; recharge solaire</p>
<a href="{{ route('refuges.index') }}" class="mt-1 font-body-sm text-body-sm text-primary hover:underline">Rejoindre ce refuge →</a>
</div>
</div>
</div>
</div>
</div>
</div>

<script>
(function initDashboard() {
  const toggleBtn = document.getElementById('toggle-energy-mode');
  const knob = document.getElementById('toggle-knob');
  const bulb = document.getElementById('status-bulb');
  const title = document.getElementById('status-title');
  const desc = document.getElementById('status-desc');
  let isActive = true;
  if (toggleBtn) {
    toggleBtn.addEventListener('click', () => {
      isActive = !isActive;
      toggleBtn.setAttribute('aria-checked', isActive ? 'true' : 'false');
      if (isActive) {
        toggleBtn.className = 'relative inline-flex h-7 w-12 shrink-0 cursor-pointer rounded-full bg-primary-container p-0.5 transition-colors duration-200 ease-in-out focus:outline-none';
        knob.className = 'pointer-events-none inline-block h-6 w-6 transform translate-x-5 rounded-full bg-on-primary-container shadow ring-0 transition duration-200 ease-in-out';
        bulb.className = 'w-10 h-10 rounded-lg bg-primary-container/20 text-primary flex items-center justify-center';
        title.textContent = 'Mode Actif';
        desc.textContent = 'Consommation réduite de 35%';
        desc.className = 'font-body-sm text-body-sm text-primary';
      } else {
        toggleBtn.className = 'relative inline-flex h-7 w-12 shrink-0 cursor-pointer rounded-full bg-surface-variant p-0.5 transition-colors duration-200 ease-in-out focus:outline-none';
        knob.className = 'pointer-events-none inline-block h-6 w-6 transform translate-x-0 rounded-full bg-on-surface-variant shadow ring-0 transition duration-200 ease-in-out';
        bulb.className = 'w-10 h-10 rounded-lg bg-surface-variant text-on-surface-variant flex items-center justify-center';
        title.textContent = 'Mode Standard';
        desc.textContent = 'Aucune restriction automatique';
        desc.className = 'font-body-sm text-body-sm text-on-surface-variant';
      }
    });
  }
  const actionList = document.getElementById('action-list');
  const counterEl = document.getElementById('checklist-counter');
  if (actionList && counterEl) {
    const checkboxes = actionList.querySelectorAll('input[type="checkbox"]');
    function updateChecklist() {
      let checkedCount = 0;
      checkboxes.forEach(cb => {
        const icon = cb.parentElement.querySelector('.material-symbols-outlined');
        if (cb.checked) {
          checkedCount++;
          if (icon) { icon.textContent = 'check_circle'; icon.className = 'material-symbols-outlined text-primary text-[20px] shrink-0'; }
        } else {
          if (icon) { icon.textContent = 'radio_button_unchecked'; icon.className = 'material-symbols-outlined text-outline-variant text-[20px] shrink-0'; }
        }
      });
      counterEl.textContent = `${checkedCount} / ${checkboxes.length} faits`;
    }
    checkboxes.forEach(cb => { cb.addEventListener('change', updateChecklist); });
  }
})();
</script>
</x-app-layout>
