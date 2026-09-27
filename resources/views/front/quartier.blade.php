<x-public-layout :title="$quartier->nom">
<h1 class="sr-only">Quartier {{ $quartier->nom }} — points de fraîcheur et carte</h1>
<a href="{{ route('home') }}" class="inline-flex items-center gap-1 font-body-sm text-body-sm text-primary hover:underline"><span class="material-symbols-outlined text-[16px]">arrow_back</span>Retour à l'accueil</a>

{{-- Screen Stitch — carte des fraîcheurs / refuges, adaptée au quartier courant --}}
@php
    $nbResidences = $quartier->residences->count();
    $nbFraicheur = $quartier->residences->where('point_fraicheur', true)->count();
    $nbClim = $quartier->residences->where('salle_climatisee', true)->count();
    $nbLogements = (int) $quartier->residences->sum('nombre_logements');
    $vedette = $quartier->residences->firstWhere('point_fraicheur', true) ?? $quartier->residences->first();
@endphp

<!-- Sub-header HUD Bar: Live Metrics & Context -->
<section class="w-full bg-surface-container-low px-space-md py-space-sm shadow-sm rounded-xl">
<div class="flex flex-wrap items-center justify-between gap-space-md">
<div class="flex items-center gap-space-md">
<div class="flex items-center gap-space-xs text-primary">
<span class="material-symbols-outlined text-[20px]" style="font-variation-settings: 'FILL' 1;">thermostat</span>
<span class="font-headline-sm text-headline-sm">38.4°C</span>
<span class="font-label-sm text-label-sm text-on-surface-variant uppercase tracking-wider pl-1">Ressenti 42°C</span>
</div>
<div class="hidden sm:flex items-center gap-space-xs text-secondary pl-space-md">
<span class="material-symbols-outlined text-[18px]">wb_sunny</span>
<span class="font-body-sm text-body-sm text-on-surface">Indice UV 9 (Extrême)</span>
</div>
<div class="hidden lg:flex items-center gap-space-xs text-on-surface-variant pl-space-md">
<span class="material-symbols-outlined text-[18px] text-tertiary-container">bolt</span>
<span class="font-body-sm text-body-sm">Charge Réseau Secteur : <strong class="text-tertiary-fixed">84%</strong> (Stable)</span>
</div>
</div>
<div class="flex items-center gap-space-sm ml-auto">
<span class="font-label-sm text-label-sm uppercase tracking-wider text-on-surface-variant">Mode Carte:</span>
<button class="px-space-sm py-1 rounded bg-surface-container-high text-primary font-label-sm text-label-sm flex items-center gap-1 shadow-sm transition-colors hover:bg-surface-variant" id="btnThermalToggle" type="button" aria-pressed="true">
<span class="material-symbols-outlined text-[16px]">layers</span>
<span>Thermographie active</span>
</button>
<button class="p-1.5 rounded bg-surface-container-high text-on-surface-variant hover:text-primary transition-colors" id="btnGpsLocate" title="Me géolocaliser" aria-label="Me géolocaliser" type="button">
<span class="material-symbols-outlined text-[18px]">my_location</span>
</button>
</div>
</div>
</section>

<!-- Interactive Filters Navigation -->
<section class="w-full bg-surface-container-lowest px-space-md py-space-sm shadow-md rounded-xl">
<div class="flex items-center gap-space-xs overflow-x-auto no-scrollbar">
<button class="filter-btn active shrink-0 px-space-md py-1.5 rounded-full font-label-md text-label-md transition-all flex items-center gap-1.5 bg-primary-container text-on-primary-container shadow-sm" data-category="all" type="button">
<span class="material-symbols-outlined text-[16px]">explore</span>
<span>Tous les refuges ({{ $nbResidences }})</span>
</button>
<button class="filter-btn shrink-0 px-space-md py-1.5 rounded-full font-label-md text-label-md transition-all flex items-center gap-1.5 bg-surface-container text-on-surface-variant hover:text-on-surface hover:bg-surface-container-high" data-category="climatise" type="button">
<span class="material-symbols-outlined text-[16px] text-secondary">ac_unit</span>
<span>Salles climatisées ({{ $nbClim }})</span>
</button>
<button class="filter-btn shrink-0 px-space-md py-1.5 rounded-full font-label-md text-label-md transition-all flex items-center gap-1.5 bg-surface-container text-on-surface-variant hover:text-on-surface hover:bg-surface-container-high" data-category="vegetal" type="button">
<span class="material-symbols-outlined text-[16px] text-primary">park</span>
<span>Parcs &amp; Canopées ombragées</span>
</button>
<button class="filter-btn shrink-0 px-space-md py-1.5 rounded-full font-label-md text-label-md transition-all flex items-center gap-1.5 bg-surface-container text-on-surface-variant hover:text-on-surface hover:bg-surface-container-high" data-category="eau" type="button">
<span class="material-symbols-outlined text-[16px] text-primary-container">water_drop</span>
<span>Fontaines &amp; Brumiseurs</span>
</button>
<button class="filter-btn shrink-0 px-space-md py-1.5 rounded-full font-label-md text-label-md transition-all flex items-center gap-1.5 bg-surface-container text-on-surface-variant hover:text-on-surface hover:bg-surface-container-high" data-category="pmr" type="button">
<span class="material-symbols-outlined text-[16px] text-tertiary-fixed">accessible</span>
<span>Points de fraîcheur ({{ $nbFraicheur }})</span>
</button>
</div>
</section>

<!-- Main Map Command Matrix (Split Layout) -->
<section class="w-full flex-1">
<div class="grid grid-cols-1 lg:grid-cols-12 gap-space-md items-start">
<!-- Interactive Dark-HUD Vector Map Panel -->
<div class="lg:col-span-7 flex flex-col gap-space-md">
<div class="relative w-full h-[580px] lg:h-[720px] rounded-xl overflow-hidden bg-surface-container-lowest shadow-xl flex flex-col justify-between p-space-md">
<div class="absolute inset-0 w-full h-full bg-cover bg-center filter saturate-50 contrast-125 brightness-50" data-location="{{ $quartier->nom }}, {{ $quartier->ville }}" style="background-image: url('https://images.unsplash.com/photo-1502920917128-1aa500764cbd?auto=format&amp;fit=crop&amp;w=1600&amp;q=80');"></div>
<div class="absolute inset-0 bg-gradient-to-t from-surface-container-lowest via-surface-container-lowest/30 to-surface-container-lowest/40 pointer-events-none"></div>
<div class="absolute -top-12 -left-12 w-96 h-96 rounded-full bg-error/15 blur-3xl pointer-events-none"></div>
<div class="absolute top-1/2 left-1/3 w-80 h-80 rounded-full bg-primary-container/15 blur-3xl pointer-events-none"></div>
<div class="absolute bottom-10 right-10 w-72 h-72 rounded-full bg-secondary-container/20 blur-2xl pointer-events-none"></div>
<div class="relative z-10 flex items-center justify-between gap-space-sm">
<div class="flex items-center gap-space-xs bg-surface-container-lowest/90 backdrop-blur-md px-space-md py-1.5 rounded-lg shadow-sm">
<span class="h-2 w-2 rounded-full bg-primary animate-pulse"></span>
<span class="font-label-sm text-label-sm text-on-surface uppercase tracking-wide">{{ $quartier->nom }} — {{ $quartier->ville }} {{ $quartier->code_postal }} · Rayon 1.2 km</span>
</div>
<div class="flex items-center gap-1 bg-surface-container-lowest/90 backdrop-blur-md p-1 rounded-lg shadow-sm">
<button class="p-1 rounded text-on-surface-variant hover:text-on-surface hover:bg-surface-container-high transition-colors" title="Zoom avant" aria-label="Zoom avant" type="button">
<span class="material-symbols-outlined text-[18px]">add</span>
</button>
<button class="p-1 rounded text-on-surface-variant hover:text-on-surface hover:bg-surface-container-high transition-colors" title="Zoom arrière" aria-label="Zoom arrière" type="button">
<span class="material-symbols-outlined text-[18px]">remove</span>
</button>
<button class="p-1 rounded text-primary hover:bg-surface-container-high transition-colors" title="Boussole Nord" aria-label="Recentrer la carte vers le nord" type="button">
<span class="material-symbols-outlined text-[18px]">explore</span>
</button>
</div>
</div>
<div class="relative z-10 w-full h-full my-auto pointer-events-none">
<div class="absolute top-[52%] left-[44%] -translate-x-1/2 -translate-y-1/2 pointer-events-auto flex flex-col items-center">
<span class="relative flex h-6 w-6">
<span class="animate-ping absolute inline-flex h-full w-full rounded-full bg-primary-container opacity-70"></span>
<span class="relative inline-flex rounded-full h-6 w-6 bg-primary-container/40 items-center justify-center">
<span class="h-3 w-3 rounded-full bg-primary-container shadow-[0_0_12px_#1B77BA]"></span>
</span>
</span>
<span class="mt-1 bg-surface-container-lowest/90 backdrop-blur-sm text-primary font-label-sm text-label-sm px-1.5 py-0.5 rounded shadow">Vous êtes ici</span>
</div>
<button class="pin-marker absolute top-[36%] left-[58%] -translate-x-1/2 -translate-y-1/2 pointer-events-auto group focus:outline-none transition-transform hover:scale-110 active:scale-95" data-spot="vedette" type="button">
<div class="relative flex items-center justify-center p-2 rounded-full bg-secondary-container text-on-secondary shadow-[0_0_24px_rgba(5,102,217,0.7)]">
<span class="material-symbols-outlined text-[20px]">ac_unit</span>
<span class="absolute -top-1 -right-1 bg-primary text-on-primary-fixed font-label-sm text-[10px] font-bold px-1 rounded-full">23°</span>
</div>
<div class="absolute left-1/2 -translate-x-1/2 mt-1.5 opacity-90 group-hover:opacity-100 transition-opacity whitespace-nowrap bg-surface-container-lowest/95 backdrop-blur-md px-2 py-0.5 rounded shadow text-secondary font-label-sm text-label-sm">
{{ $vedette ? $vedette->nom.' (350m)' : 'Aucun refuge (—)' }}
</div>
</button>
<button class="pin-marker absolute top-[68%] left-[28%] -translate-x-1/2 -translate-y-1/2 pointer-events-auto group focus:outline-none transition-transform hover:scale-110 active:scale-95" data-spot="parc" type="button">
<div class="relative flex items-center justify-center p-2 rounded-full bg-primary text-on-primary shadow-[0_0_20px_rgba(27,119,186,0.4)]">
<span class="material-symbols-outlined text-[20px]">park</span>
</div>
<div class="absolute left-1/2 -translate-x-1/2 mt-1.5 opacity-0 group-hover:opacity-100 transition-opacity whitespace-nowrap bg-surface-container-lowest/95 backdrop-blur-md px-2 py-0.5 rounded shadow text-primary font-label-sm text-label-sm">
Parc du quartier (520m)
</div>
</button>
<button class="pin-marker absolute top-[28%] left-[24%] -translate-x-1/2 -translate-y-1/2 pointer-events-auto group focus:outline-none transition-transform hover:scale-110 active:scale-95" data-spot="fontaine" type="button">
<div class="flex items-center justify-center p-1.5 rounded-full bg-surface-container-highest text-primary-container shadow-[0_0_14px_rgba(27,119,186,0.3)]">
<span class="material-symbols-outlined text-[16px]">water_drop</span>
</div>
<div class="absolute left-1/2 -translate-x-1/2 mt-1 opacity-0 group-hover:opacity-100 transition-opacity whitespace-nowrap bg-surface-container-lowest/90 px-1.5 py-0.5 rounded text-on-surface-variant font-label-sm text-label-sm">
Fontaine active (180m)
</div>
</button>
<button class="pin-marker absolute top-[74%] left-[72%] -translate-x-1/2 -translate-y-1/2 pointer-events-auto group focus:outline-none transition-transform hover:scale-110 active:scale-95" data-spot="repit" type="button">
<div class="relative flex items-center justify-center p-2 rounded-full bg-tertiary-container text-on-tertiary-container shadow-[0_0_20px_rgba(255,199,105,0.4)]">
<span class="material-symbols-outlined text-[18px]">emergency</span>
</div>
<div class="absolute left-1/2 -translate-x-1/2 mt-1.5 opacity-90 group-hover:opacity-100 transition-opacity whitespace-nowrap bg-surface-container-lowest/95 backdrop-blur-md px-2 py-0.5 rounded shadow text-tertiary-fixed font-label-sm text-label-sm">
Espace Répit (salle climatisée)
</div>
</button>
<svg class="absolute inset-0 w-full h-full pointer-events-none" xmlns="http://www.w3.org/2000/svg">
<path class="text-primary-container" d="M 440 370 Q 480 340 520 310 T 580 260" fill="none" stroke="currentColor" stroke-dasharray="6 6" stroke-linecap="round" stroke-width="4">
<animate attributename="stroke-dashoffset" dur="1.2s" from="24" repeatcount="indefinite" to="0"></animate>
</path>
</svg>
</div>
<div class="relative z-10 w-full bg-surface-container-low/95 backdrop-blur-xl p-space-sm rounded-lg shadow-xl flex flex-wrap items-center justify-between gap-space-sm">
<div class="flex items-center gap-space-sm">
<div class="p-2 rounded bg-surface-container text-primary flex items-center justify-center">
<span class="material-symbols-outlined text-[20px]">directions_walk</span>
</div>
<div>
<p class="font-title-md text-title-md text-on-surface">Trajet piéton ombragé calculé</p>
<p class="font-body-sm text-body-sm text-on-surface-variant">Refuges à moins de 500 m • <strong class="text-primary font-medium">85% sous canopée végétale</strong></p>
@if ($quartier->description)<p class="font-body-sm text-body-sm text-on-surface-variant mt-1">{{ $quartier->description }}</p>@endif
</div>
</div>
<div class="flex items-center gap-space-md ml-auto">
<div class="text-right">
<span class="font-headline-sm text-headline-sm text-primary">6 min</span>
<span class="block font-label-sm text-label-sm text-on-surface-variant">350 mètres</span>
</div>
<a href="#residences" class="px-space-md py-2 rounded bg-primary-container text-on-primary-container font-label-md text-label-md font-semibold hover:bg-primary transition-colors shadow inline-flex items-center">
Lancer le guidage
</a>
</div>
</div>
</div>
<div class="grid grid-cols-2 sm:grid-cols-4 gap-space-sm">
<div class="p-space-sm rounded-lg bg-surface-container-low shadow-sm">
<span class="font-label-sm text-label-sm uppercase text-on-surface-variant block">Résidences</span>
<div class="flex items-baseline gap-1 mt-0.5">
<span class="font-headline-sm text-headline-sm text-on-surface">{{ $nbResidences }}</span>
<span class="font-label-sm text-label-sm text-on-surface-variant">suivies</span>
</div>
</div>
<div class="p-space-sm rounded-lg bg-surface-container-low shadow-sm">
<span class="font-label-sm text-label-sm uppercase text-on-surface-variant block">Points de fraîcheur</span>
<div class="flex items-baseline gap-1 mt-0.5">
<span class="font-headline-sm text-headline-sm text-primary">{{ $nbFraicheur }}</span>
<span class="font-label-sm text-label-sm text-primary-fixed-dim">ouverts</span>
</div>
</div>
<div class="p-space-sm rounded-lg bg-surface-container-low shadow-sm">
<span class="font-label-sm text-label-sm uppercase text-on-surface-variant block">Salles climatisées</span>
<div class="flex items-baseline gap-1 mt-0.5">
<span class="font-headline-sm text-headline-sm text-tertiary-fixed">{{ $nbClim }}</span>
<span class="font-label-sm text-label-sm text-tertiary-fixed-dim">équipées</span>
</div>
</div>
<div class="p-space-sm rounded-lg bg-surface-container-low shadow-sm">
<span class="font-label-sm text-label-sm uppercase text-on-surface-variant block">Logements</span>
<div class="flex items-baseline gap-1 mt-0.5">
<span class="font-headline-sm text-headline-sm text-secondary">{{ $nbLogements }}</span>
<span class="font-label-sm text-label-sm text-secondary-fixed">protégés</span>
</div>
</div>
</div>
</div>
<!-- Right Sidebar: Results, Priority Shelters & Active Spot Inspector -->
<div class="lg:col-span-5 flex flex-col gap-space-md" id="residences">
<div class="p-space-sm rounded-xl bg-surface-container-low shadow-sm flex items-center gap-space-sm">
<span class="material-symbols-outlined text-outline pl-1 text-[20px]">search</span>
<input class="bg-transparent font-body-md text-body-md text-on-surface placeholder:text-outline focus:outline-none w-full" placeholder="Rechercher une résidence du quartier..." type="text" />
<div class="shrink-0 flex items-center gap-1 bg-surface-container px-2 py-1 rounded text-on-surface-variant text-label-sm font-label-sm">
<span>&lt; 1.5 km</span>
</div>
</div>
@if ($vedette)
<div class="rounded-xl bg-surface-container p-space-md shadow-lg relative overflow-hidden flex flex-col gap-space-sm">
<div class="absolute -top-16 -right-16 w-44 h-44 rounded-full bg-secondary-container/20 blur-2xl pointer-events-none"></div>
<div class="flex items-start justify-between gap-space-sm relative z-10">
<div class="flex flex-col">
<div class="flex items-center gap-2 mb-1 flex-wrap">
@if ($vedette->salle_climatisee)
<span class="px-2 py-0.5 rounded-full bg-secondary-container/40 text-secondary font-label-sm text-label-sm uppercase tracking-wider flex items-center gap-1">
<span class="material-symbols-outlined text-[13px]">ac_unit</span>
Climatisation régulée
</span>
@endif
@if ($vedette->point_fraicheur)
<span class="px-2 py-0.5 rounded-full bg-primary-container/15 text-primary font-label-sm text-label-sm uppercase tracking-wider">Point de fraîcheur</span>
@endif
<span class="px-2 py-0.5 rounded-full bg-surface-container-highest text-primary font-label-sm text-label-sm">350 m • 6 min</span>
</div>
<h2 class="font-headline-sm text-headline-sm text-on-surface">{{ $vedette->nom }}</h2>
<p class="font-body-sm text-body-sm text-on-surface-variant">{{ $vedette->adresse }} — {{ $vedette->nombre_logements }} logement(s)</p>
</div>
<div class="flex flex-col items-end">
<div class="px-2.5 py-1 rounded-lg bg-surface-container-highest text-primary font-headline-sm text-headline-sm shadow-sm">23°C</div>
<span class="font-label-sm text-label-sm text-on-surface-variant mt-0.5">T° intérieure</span>
</div>
</div>
<div class="mt-space-xs p-space-sm rounded-lg bg-surface-container-lowest/80 flex flex-col gap-2 relative z-10">
<div class="flex items-center justify-between text-body-sm font-body-sm">
<span class="text-on-surface-variant flex items-center gap-1.5">
<span class="material-symbols-outlined text-[16px] text-primary">groups</span>
Capacité d'accueil disponible
</span>
<span class="font-title-md text-title-md text-primary font-semibold">72% libre</span>
</div>
<div class="w-full bg-surface-variant h-2 rounded-full overflow-hidden">
<div class="bg-primary-container h-full rounded-full w-[28%]"></div>
</div>
<div class="flex items-center justify-between text-label-sm font-label-sm text-on-surface-variant pt-1">
<span>Ouvert jusqu'à <strong>22h00</strong> (Nocturne Canicule)</span>
<span class="text-primary-fixed-dim">Affluence modérée</span>
</div>
</div>
<div class="flex flex-wrap gap-1.5 py-1 relative z-10">
<span class="px-2 py-1 rounded bg-surface-container-high text-on-surface font-label-sm text-label-sm flex items-center gap-1">
<span class="material-symbols-outlined text-[14px] text-primary">power</span>
Prises recharge tél. &amp; méd.
</span>
<span class="px-2 py-1 rounded bg-surface-container-high text-on-surface font-label-sm text-label-sm flex items-center gap-1">
<span class="material-symbols-outlined text-[14px] text-primary">water_bottle</span>
Fontaine filtrée fraîche
</span>
<span class="px-2 py-1 rounded bg-surface-container-high text-on-surface font-label-sm text-label-sm flex items-center gap-1">
<span class="material-symbols-outlined text-[14px] text-secondary">wifi</span>
Wi-Fi d'urgence ouvert
</span>
<span class="px-2 py-1 rounded bg-surface-container-high text-on-surface font-label-sm text-label-sm flex items-center gap-1">
<span class="material-symbols-outlined text-[14px] text-tertiary-fixed">accessible</span>
Accès PMR total
</span>
</div>
<div class="grid grid-cols-2 gap-space-sm pt-space-xs relative z-10">
<a href="#liste-residences" class="w-full py-2.5 px-space-sm rounded-lg bg-primary-container text-on-primary-container font-label-md text-label-md font-semibold hover:bg-primary transition-colors flex items-center justify-center gap-1.5 shadow">
<span class="material-symbols-outlined text-[18px]">directions_walk</span>
<span>Itinéraire Ombragé</span>
</a>
<a href="tel:0800066666" class="w-full py-2.5 px-space-sm rounded-lg bg-surface-container-highest text-on-surface font-label-md text-label-md hover:bg-surface-variant transition-colors flex items-center justify-center gap-1.5">
<span class="material-symbols-outlined text-[18px] text-primary">call</span>
<span>Contacter l'accueil</span>
</a>
</div>
</div>
@endif
<div class="flex flex-col gap-space-sm overflow-y-auto max-h-[460px] pr-1" id="liste-residences">
<p class="font-label-sm text-label-sm uppercase tracking-wider text-on-surface-variant px-1">
Résidences du quartier — {{ $nbResidences }} vérifiée(s)
</p>
@forelse ($quartier->residences as $residence)
<article class="p-space-md rounded-xl bg-surface-container-low hover:bg-surface-container transition-colors shadow-sm group flex flex-col gap-2">
<div class="flex items-start justify-between gap-space-sm">
<div class="flex items-center gap-2">
<div class="p-2 rounded-lg {{ $residence->point_fraicheur ? 'bg-primary/10 text-primary group-hover:bg-primary group-hover:text-on-primary' : 'bg-secondary/10 text-secondary group-hover:bg-secondary group-hover:text-on-secondary' }} transition-colors">
<span class="material-symbols-outlined text-[20px]">{{ $residence->point_fraicheur ? 'ac_unit' : ($residence->salle_climatisee ? 'emergency' : 'home') }}</span>
</div>
<div>
<h3 class="font-title-md text-title-md text-on-surface group-hover:text-primary transition-colors">{{ $residence->nom }}</h3>
<span class="font-body-sm text-body-sm text-on-surface-variant">{{ $residence->adresse }} • {{ $residence->nombre_logements }} logement(s)</span>
</div>
</div>
</div>
<div class="flex flex-wrap items-center gap-2 pt-1">
@if ($residence->point_fraicheur)
<span class="px-2 py-0.5 rounded-md bg-primary-container/15 text-primary font-label-sm text-label-sm flex items-center gap-1"><span class="material-symbols-outlined text-[14px]">water_drop</span>Point de fraîcheur</span>
@endif
@if ($residence->salle_climatisee)
<span class="px-2 py-0.5 rounded-md bg-surface-variant text-primary font-label-sm text-label-sm flex items-center gap-1"><span class="material-symbols-outlined text-[14px]">ac_unit</span>Salle climatisée</span>
@endif
@if (!$residence->point_fraicheur && !$residence->salle_climatisee)
<span class="px-2 py-0.5 rounded-md bg-surface-container-high text-on-surface-variant font-label-sm text-label-sm flex items-center gap-1"><span class="material-symbols-outlined text-[14px]">home</span>Résidence suivie</span>
@endif
<span class="flex items-center gap-1 text-primary-fixed-dim font-body-sm text-body-sm"><span class="material-symbols-outlined text-[16px]">schedule</span>Refuge à moins de 500 m</span>
</div>
</article>
@empty
<div class="rounded-xl bg-surface-container-low p-space-md text-on-surface-variant">Aucune résidence enregistrée dans ce quartier.</div>
@endforelse
</div>
<div class="p-space-md rounded-xl bg-surface-container-lowest flex items-center justify-between gap-space-md shadow-sm">
<div class="flex items-center gap-space-sm">
<div class="p-2 rounded-full bg-primary/10 text-primary">
<span class="material-symbols-outlined text-[20px]">volunteer_activism</span>
</div>
<div>
<p class="font-title-md text-title-md text-on-surface">Voisin vulnérable ou isolé ?</p>
<p class="font-body-sm text-body-sm text-on-surface-variant">Demander le passage d'une maraude de rafraîchissement</p>
</div>
</div>
<a href="tel:190" class="shrink-0 px-space-md py-2 rounded-lg bg-surface-container-high text-primary hover:bg-surface-variant font-label-md text-label-md font-medium transition-colors">Signaler</a>
</div>
</div>
</div>
</section>
<script>
  const filterBtns = document.querySelectorAll('.filter-btn');
  filterBtns.forEach(btn => {
    btn.addEventListener('click', () => {
      filterBtns.forEach(b => {
        b.classList.remove('bg-primary-container', 'text-on-primary-container', 'shadow-sm');
        b.classList.add('bg-surface-container', 'text-on-surface-variant');
      });
      btn.classList.add('bg-primary-container', 'text-on-primary-container', 'shadow-sm');
      btn.classList.remove('bg-surface-container', 'text-on-surface-variant');
    });
  });
  const pins = document.querySelectorAll('.pin-marker');
  pins.forEach(pin => {
    pin.addEventListener('click', () => {
      pins.forEach(p => p.classList.remove('ring-4', 'ring-primary-container'));
      pin.classList.add('ring-4', 'ring-primary-container');
    });
  });
  const gpsBtn = document.getElementById('btnGpsLocate');
  if (gpsBtn) {
    gpsBtn.addEventListener('click', () => {
      gpsBtn.classList.add('text-primary-container', 'animate-spin');
      setTimeout(() => { gpsBtn.classList.remove('text-primary-container', 'animate-spin'); }, 1000);
    });
  }
  const thermalToggle = document.getElementById('btnThermalToggle');
  if (thermalToggle) {
    let active = true;
    thermalToggle.addEventListener('click', () => {
      active = !active;
      thermalToggle.setAttribute('aria-pressed', active ? 'true' : 'false');
      if (active) {
        thermalToggle.classList.add('text-primary', 'bg-surface-container-high');
        thermalToggle.classList.remove('text-on-surface-variant');
        thermalToggle.querySelector('span:last-child').textContent = 'Thermographie active';
      } else {
        thermalToggle.classList.remove('text-primary', 'bg-surface-container-high');
        thermalToggle.classList.add('text-on-surface-variant');
        thermalToggle.querySelector('span:last-child').textContent = 'Mode standard';
      }
    });
  }
</script>
</x-public-layout>
