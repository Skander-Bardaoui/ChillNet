<x-app-layout>
<x-slot name="header">
<div class="flex flex-col md:flex-row md:items-center justify-between gap-3">
<div>
<p class="font-label-sm text-label-sm text-primary uppercase tracking-wider font-semibold">Tableau de bord résident — ChillNet</p>
<h1 class="font-headline-lg text-headline-lg text-on-surface">Bonjour {{ auth()->user()->name }}</h1>
<p class="font-body-sm text-body-sm text-on-surface-variant mt-1">
@if ($lieu)
<span class="inline-flex items-center gap-1"><span class="material-symbols-outlined text-[16px] text-primary">{{ $lieu->type->icone() }}</span>{{ $lieu->nom }}</span>@if ($lieu->adresse) — {{ $lieu->adresse }}@endif
· <a href="{{ route('lieux.index') }}" class="text-primary hover:underline">Gérer mes lieux</a>
@else
<a href="{{ route('lieux.index') }}" class="text-primary hover:underline">Ajoutez un lieu (Domicile, Travail…) pour personnaliser la vigilance →</a>
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

<link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" />

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

@unless ($lieu)
{{-- État vide : aucun lieu enregistré — on invite à en créer un. --}}
<section class="rounded-xl bg-surface-container-high/90 backdrop-blur-xl shadow-xl p-space-md md:p-space-lg flex flex-col sm:flex-row items-start sm:items-center justify-between gap-space-md">
<div class="flex items-start gap-space-md">
<div class="flex items-center justify-center w-12 h-12 rounded-xl bg-primary-container/15 text-primary shrink-0">
<span class="material-symbols-outlined text-[26px]">add_location_alt</span>
</div>
<div class="flex flex-col gap-0.5">
<span class="px-2 py-0.5 rounded-full bg-primary-container/20 text-primary font-label-sm text-label-sm tracking-wider uppercase font-semibold self-start">Aucun lieu enregistré</span>
<p class="font-title-md text-title-md text-on-surface">Ajoutez votre premier lieu</p>
<p class="font-body-sm text-body-sm text-on-surface-variant">Posez un point sur la carte (Domicile, Travail…) pour voir la météo, les alertes et les coupures qui vous concernent.</p>
</div>
</div>
<a href="{{ route('lieux.index') }}" class="shrink-0 px-space-md py-2.5 rounded-lg bg-primary-container text-on-primary-container font-label-md text-label-md hover:opacity-95 shadow-md flex items-center justify-center gap-2 font-semibold">
<span class="material-symbols-outlined text-[18px]">add</span>
<span>Créer un lieu</span>
</a>
</section>
@endunless

{{-- Fond lumineux d'ambiance --}}
<div class="relative w-full">
<div class="absolute -top-32 left-1/4 w-96 h-96 bg-primary-container/10 rounded-full blur-[120px] pointer-events-none"></div>
<div class="absolute top-12 right-12 w-80 h-80 bg-tertiary-container/10 rounded-full blur-[140px] pointer-events-none"></div>

{{-- Bandeau protocole vigilance (alimenté par l'alerte active des lieux du foyer) --}}
@if ($alerteActive)
@php
    $niveau = $alerteActive->niveau;
    $couleur = $niveau->couleurHex();
    $tempAffichee = $meteo?->temperature ?? $alerteActive->temperature_actuelle;
@endphp
<section class="relative overflow-hidden rounded-xl bg-surface-container-high/90 backdrop-blur-xl shadow-xl">
<div class="absolute inset-y-0 left-0 w-2" style="background: {{ $couleur }};"></div>
<div class="p-space-md md:p-space-lg flex flex-col lg:flex-row items-start lg:items-center justify-between gap-space-md">
<div class="flex items-start gap-space-md">
<div class="relative flex items-center justify-center w-12 h-12 rounded-xl shrink-0" style="background: {{ $couleur }}22; color: {{ $couleur }};">
<span class="material-symbols-outlined text-[26px]">{{ $niveau->icone() }}</span>
<span class="absolute -top-1 -right-1 flex h-3 w-3">
<span class="animate-ping absolute inline-flex h-full w-full rounded-full opacity-75" style="background: {{ $couleur }};"></span>
<span class="relative inline-flex rounded-full h-3 w-3" style="background: {{ $couleur }};"></span>
</span>
</div>
<div class="flex flex-col gap-0.5">
<div class="flex items-center gap-2 flex-wrap">
<span class="px-2 py-0.5 rounded-full font-label-sm text-label-sm tracking-wider uppercase font-semibold {{ $niveau->badgeClasses() }}">Vigilance {{ $niveau->label() }} Canicule</span>
<span class="font-body-sm text-body-sm text-on-surface-variant flex items-center gap-1">
<span class="material-symbols-outlined text-[14px] text-primary">schedule</span>
Jusqu'au {{ $alerteActive->fin?->format('d/m à H:i') }}
</span>
</div>
<p class="font-title-md text-title-md text-on-surface">{{ $alerteActive->titre }}@if ($tempAffichee !== null) — <span style="color: {{ $couleur }};" class="font-bold">{{ number_format((float) $tempAffichee, 1) }}°C</span>@endif</p>
@if ($alerteActive->message)
<p class="font-body-sm text-body-sm text-on-surface-variant">{{ \Illuminate\Support\Str::limit($alerteActive->message, 220) }}</p>
@elseif ($messagePersonnalise)
<p class="font-body-sm text-body-sm text-on-surface-variant">{{ \Illuminate\Support\Str::limit($messagePersonnalise, 220) }}</p>
@endif
</div>
</div>
<div class="flex items-center gap-space-sm w-full lg:w-auto shrink-0 pt-2 lg:pt-0">
<a href="tel:190" class="flex-1 lg:flex-initial px-space-md py-2.5 rounded-lg bg-surface-container text-primary font-label-md text-label-md hover:bg-surface-variant transition-colors flex items-center justify-center gap-2">
<span class="material-symbols-outlined text-[18px]">radio</span>
<span>Point de Situation — 15</span>
</a>
<a href="{{ route('alertes.index') }}" class="flex-1 lg:flex-initial px-space-md py-2.5 rounded-lg bg-primary-container text-on-primary-container font-label-md text-label-md hover:opacity-95 shadow-md flex items-center justify-center gap-2 font-semibold">
<span class="material-symbols-outlined text-[18px]">shield</span>
<span>Voir les consignes</span>
</a>
</div>
</div>
</section>
@else
<section class="relative overflow-hidden rounded-xl bg-surface-container-high/90 backdrop-blur-xl shadow-xl">
<div class="absolute inset-y-0 left-0 w-2 bg-gradient-to-b from-primary-container to-tertiary-container"></div>
<div class="p-space-md md:p-space-lg flex flex-col lg:flex-row items-start lg:items-center justify-between gap-space-md">
<div class="flex items-start gap-space-md">
<div class="relative flex items-center justify-center w-12 h-12 rounded-xl bg-primary-container/15 text-primary shrink-0">
<span class="material-symbols-outlined text-[26px]">verified_user</span>
</div>
<div class="flex flex-col gap-0.5">
<span class="px-2 py-0.5 rounded-full bg-primary-container/20 text-primary font-label-sm text-label-sm tracking-wider uppercase font-semibold self-start">Aucune vigilance en cours</span>
<p class="font-title-md text-title-md text-on-surface">Pas d'alerte canicule active pour vos lieux</p>
<p class="font-body-sm text-body-sm text-on-surface-variant">
@if ($lieu)
Restez informé : une alerte s'affichera ici automatiquement dès sa publication par votre gestionnaire.
@else
<a href="{{ route('lieux.index') }}" class="text-primary hover:underline">Ajoutez un lieu</a> pour recevoir les alertes qui vous concernent.
@endif
</p>
</div>
</div>
<div class="flex items-center gap-space-sm w-full lg:w-auto shrink-0 pt-2 lg:pt-0">
<a href="tel:190" class="flex-1 lg:flex-initial px-space-md py-2.5 rounded-lg bg-surface-container text-primary font-label-md text-label-md hover:bg-surface-variant transition-colors flex items-center justify-center gap-2">
<span class="material-symbols-outlined text-[18px]">radio</span>
<span>Point de Situation — 15</span>
</a>
<a href="{{ route('alertes.index') }}" class="flex-1 lg:flex-initial px-space-md py-2.5 rounded-lg bg-primary-container text-on-primary-container font-label-md text-label-md hover:opacity-95 shadow-md flex items-center justify-center gap-2 font-semibold">
<span class="material-symbols-outlined text-[18px]">shield</span>
<span>Consulter les conseils</span>
</a>
</div>
</div>
</section>
@endif

{{-- 4 cartes télémétrie --}}
<section class="grid grid-cols-1 sm:grid-cols-2 xl:grid-cols-4 gap-space-md mt-space-lg">
<div class="relative overflow-hidden rounded-xl bg-surface-container/70 backdrop-blur-md p-space-md flex flex-col justify-between shadow-md">
<div class="flex items-center justify-between text-on-surface-variant">
<span class="font-label-md text-label-md uppercase tracking-wider text-on-surface-variant">Température Locale</span>
@if ($meteo)
<span class="flex items-center gap-1 text-tertiary-container font-label-sm text-label-sm font-semibold">
<span class="material-symbols-outlined text-[16px]">cloud</span> {{ $meteo->condition ?? 'Live' }}
</span>
@endif
</div>
<div class="my-space-sm flex items-baseline gap-space-xs">
@if ($meteo)
<span class="font-display-lg text-display-lg text-on-surface tracking-tighter">{{ number_format((float) $meteo->temperature, 1) }}</span>
<span class="font-title-md text-title-md text-on-surface-variant font-medium">°C</span>
@if ($meteo->ressentie !== null)
<span class="ml-auto px-2 py-0.5 rounded-md bg-surface-variant text-tertiary-fixed font-label-sm text-label-sm">Ressenti {{ number_format((float) $meteo->ressentie, 0) }}°C</span>
@endif
@else
<span class="font-display-lg text-display-lg text-on-surface-variant tracking-tighter">—</span>
<span class="font-title-md text-title-md text-on-surface-variant font-medium">°C</span>
<span class="ml-auto font-body-sm text-body-sm text-on-surface-variant">Données indisponibles</span>
@endif
</div>
<div class="pt-2 flex flex-col gap-1.5">
<div class="flex justify-between items-center text-on-surface-variant font-body-sm text-body-sm">
@if ($meteo)
<span>{{ $meteo->ville ?? 'Votre lieu' }}</span>
@if ($meteo->humidite !== null)
<span class="text-tertiary-fixed font-medium">Humidité {{ $meteo->humidite }}%</span>
@endif
<span>Vent {{ number_format((float) ($meteo->vent ?? 0), 0) }} km/h</span>
@else
<span>Météo en direct indisponible pour le moment.</span>
@endif
</div>
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
<span class="material-symbols-outlined text-[16px]">battery_charging_full</span> Batterie secteur
</span>
<span class="font-semibold text-on-surface">94% chargée</span>
</div>
</div>

<div class="relative overflow-hidden rounded-xl bg-surface-container/70 backdrop-blur-md p-space-md flex flex-col justify-between shadow-md">
<div class="flex items-center justify-between text-on-surface-variant">
<span class="font-label-md text-label-md uppercase tracking-wider text-on-surface-variant">Refuges Climat</span>
<span class="px-2 py-0.5 rounded-md bg-primary-container/20 text-primary-fixed-dim font-label-sm text-label-sm font-semibold">Rayon 800 m</span>
</div>
<div class="my-space-sm flex items-baseline gap-space-xs">
<span class="font-display-lg text-display-lg text-primary tracking-tighter">{{ $refuges->count() }}</span>
<span class="font-title-md text-title-md text-on-surface font-medium">{{ $refugesOuverts ? 'Ouverts' : 'Répertoriés' }}</span>
</div>
<div class="pt-2 flex items-center justify-between text-on-surface-variant font-body-sm text-body-sm">
@if ($refuges->isNotEmpty())
<span class="truncate">Le plus proche : <strong class="text-on-surface">{{ $refuges->first()['nom'] }} ({{ $refuges->first()['distanceM'] }}m)</strong></span>
@else
<span class="truncate">Aucun refuge dans votre périmètre</span>
@endif
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
<span class="flex items-center gap-1.5 text-on-surface-variant"><span class="w-3 h-3 rounded-full bg-primary-container"></span> Frais &amp; réseau stable</span>
<span class="flex items-center gap-1.5 text-on-surface-variant"><span class="w-3 h-3 rounded-full bg-error"></span> Coupure signalée</span>
<span class="flex items-center gap-1.5 text-on-surface-variant"><span class="w-3 h-3 rounded-full bg-tertiary-container"></span> Pic canicule</span>
</div>
</div>
<div class="w-full bg-surface-container-low rounded-xl p-space-md flex flex-col gap-space-md">
<div class="grid grid-cols-6 sm:grid-cols-8 gap-2 overflow-x-auto pb-1">
@forelse ($heures as $h)
@php
$tension = $h['stress'];
$chaud = $h['pic'];
$fond = $tension
    ? 'bg-error-container/20'
    : ($chaud ? 'bg-tertiary-container/15 shadow-[0_0_12px_rgba(255,199,105,0.2)]' : 'bg-surface-container-high/60');
@endphp
<div class="flex flex-col items-center p-2 rounded-lg {{ $fond }} text-center gap-1">
<span class="font-label-sm text-label-sm {{ $tension ? 'text-error font-semibold' : 'text-on-surface-variant' }}">{{ $h['heure']->format('H:i') }}</span>
<span class="material-symbols-outlined text-[18px] {{ $tension || $chaud ? 'text-error' : 'text-tertiary-container' }}">{{ $h['icone'] }}</span>
<span class="font-title-md text-title-md {{ $chaud ? 'text-error font-bold' : 'text-on-surface font-semibold' }}">{{ number_format($h['temperature'], 0) }}°</span>
@if ($tension)
<span class="px-1.5 py-0.5 rounded text-[10px] bg-error-container text-on-error-container font-semibold">COUPURE</span>
@elseif ($chaud)
<span class="px-1.5 py-0.5 rounded text-[10px] bg-error-container text-on-error-container font-semibold">PIC</span>
@elseif ($h['humidite'] !== null)
<span class="px-1.5 py-0.5 rounded text-[10px] bg-surface-variant text-on-surface-variant">{{ $h['humidite'] }}% ch.</span>
@endif
</div>
@empty
<div class="col-span-full rounded-lg bg-surface-container-high/60 p-space-sm text-on-surface-variant font-body-sm text-body-sm flex items-center gap-2">
<span class="material-symbols-outlined text-[20px]">cloud_off</span>
<span>Prévision météo indisponible pour le moment. Vos réflexes restent valables : hydratez-vous et évitez le soleil aux heures chaudes.</span>
</div>
@endforelse
</div>
<div class="flex flex-col gap-2 pt-2">
@if ($fenetreOptimale)
<div class="flex items-center gap-space-sm p-space-sm rounded-lg bg-primary-container/10 text-on-surface font-body-sm text-body-sm">
<span class="material-symbols-outlined text-primary text-[20px] shrink-0">bolt</span>
<span class="flex-1"><strong class="text-primary font-semibold">Fenêtre Optimale Recommandée :</strong> chargez vos téléphones, batteries de secours et accumulateurs de froid entre <span class="text-primary underline font-medium">{{ $fenetreOptimale['debut']->format('H:i') }} et {{ $fenetreOptimale['fin']->format('H:i') }}</span> — les heures les plus fraîches, réseau au repos.</span>
</div>
@endif
@if ($plageCritique)
<div class="flex items-center gap-space-sm p-space-sm rounded-lg bg-error-container/20 text-on-surface font-body-sm text-body-sm">
<span class="material-symbols-outlined text-error text-[20px] shrink-0">power_off</span>
<span class="flex-1"><strong class="text-error font-semibold">Plage Critique d'Alerte :</strong> évitez impérativement les équipements énergivores (fours, lave-linge, recharge VE) entre <span class="text-error font-bold">{{ $plageCritique['debut']->format('H:i') }} et {{ $plageCritique['fin']->format('H:i') }}</span> — pic de chaleur ou coupure signalée près de ce lieu.</span>
</div>
@endif
@if (! $fenetreOptimale && ! $plageCritique)
<div class="flex items-center gap-space-sm p-space-sm rounded-lg bg-surface-container-high/60 text-on-surface-variant font-body-sm text-body-sm">
<span class="material-symbols-outlined text-[20px] shrink-0">info</span>
<span class="flex-1">Planification indisponible sans prévision météo. Reportez vos usages énergivores aux heures les plus fraîches (tôt le matin, tard le soir).</span>
</div>
@endif
</div>
</div>
<div class="flex flex-col gap-2 pt-space-xs">
<div class="flex items-center justify-between gap-2">
<span class="font-title-md text-title-md text-on-surface">Zones d'Ombre &amp; Refuges Actifs (Périmètre 800m)</span>
@if ($origine)
<button type="button" id="btn-localiser-dashboard" class="font-label-sm text-label-sm text-primary flex items-center gap-1 hover:underline disabled:opacity-50">
<span class="material-symbols-outlined text-[16px]">my_location</span> Me localiser
</button>
@else
<span class="font-label-sm text-label-sm text-on-surface-variant flex items-center gap-1">
<span class="material-symbols-outlined text-[16px]">location_off</span> Position non renseignée
</span>
@endif
</div>
@if ($origine)
<div id="carte-refuges" class="w-full h-56 rounded-xl overflow-hidden z-0 shadow-inner"></div>
<div class="flex flex-wrap items-center gap-3">
<span class="inline-flex items-center gap-1.5 font-label-sm text-label-sm text-on-surface-variant"><span class="w-3 h-3 rounded-full" style="background:#1b77ba;"></span> Salle climatisée</span>
<span class="inline-flex items-center gap-1.5 font-label-sm text-label-sm text-on-surface-variant"><span class="w-3 h-3 rounded-full" style="background:#16a34a;"></span> Zone d'ombre / parc</span>
<span class="inline-flex items-center gap-1.5 font-label-sm text-label-sm text-on-surface-variant"><span class="w-3 h-3 rounded-full border-2 border-[#1b77ba] bg-transparent"></span> Votre position · 800 m</span>
</div>
@else
<div class="w-full h-56 rounded-xl bg-surface-container-lowest flex flex-col items-center justify-center gap-2 text-center p-space-md shadow-inner">
<span class="material-symbols-outlined text-[32px] text-on-surface-variant">wrong_location</span>
<p class="font-body-sm text-body-sm text-on-surface-variant">Ajoutez un lieu avec sa position (ou utilisez « Me localiser ») pour afficher les refuges et zones d'ombre dans un rayon de 800 m.</p>
</div>
@endif
<div class="flex flex-wrap items-center justify-between gap-2">
<p class="font-body-sm text-body-sm text-on-surface-variant">
@if ($refuges->isNotEmpty())
<strong class="text-on-surface">{{ $refuges->count() }}</strong> refuge(s) / zone(s) d'ombre à moins de 800 m.
@elseif ($origine)
Aucun refuge référencé dans un rayon de 800 m pour le moment.
@endif
</p>
<a href="{{ route('refuges.index') }}" class="font-body-sm text-body-sm text-primary hover:underline">Voir la carte des refuges →</a>
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
<p class="font-body-md text-body-md text-on-surface-variant">Pièce fraîche en sous-sol disponible pour les personnes âgées du secteur ou isolées sans ventilateur (étage R-1 avec brumisateur). N'hésitez pas à toquer au n°14.</p>
<div class="flex items-center gap-2 pt-1">
<a href="{{ route('conseils') }}" class="px-2 py-0.5 rounded bg-surface-variant text-primary font-label-sm text-label-sm hover:underline">Entraide Voisinage</a>
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

<script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
<script>
(function initCarteRefuges() {
  const el = document.getElementById('carte-refuges');
  if (!el || typeof L === 'undefined') return;

  const ORIGINE = @json($origine);
  const REFUGES = @json($refuges, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT);
  const RAYON = @json(\App\Support\Geo::RAYON_REFUGES_M);
  if (!ORIGINE) return;

  const carte = L.map('carte-refuges').setView(ORIGINE, 15);
  L.tileLayer('https://tile.openstreetmap.org/{z}/{x}/{y}.png', {
    maxZoom: 19,
    attribution: '&copy; OpenStreetMap',
  }).addTo(carte);

  // Périmètre de 800 m + position du foyer.
  L.circle(ORIGINE, { radius: RAYON, color: '#1b77ba', weight: 1.5, fillColor: '#1b77ba', fillOpacity: 0.08 }).addTo(carte);
  L.circleMarker(ORIGINE, { radius: 7, color: '#1b77ba', fillColor: '#1b77ba', fillOpacity: 1, weight: 3 })
    .addTo(carte).bindPopup('<strong>Votre foyer</strong>');

  // Les popups affichent du HTML : on échappe les données saisies.
  function echapper(texte) {
    const div = document.createElement('div');
    div.textContent = texte ?? '';
    return div.innerHTML;
  }
  function distanceKm(lat1, lng1, lat2, lng2) {
    const r = 6371;
    const dLat = (lat2 - lat1) * Math.PI / 180;
    const dLng = (lng2 - lng1) * Math.PI / 180;
    const a = Math.sin(dLat / 2) ** 2 + Math.cos(lat1 * Math.PI / 180) * Math.cos(lat2 * Math.PI / 180) * Math.sin(dLng / 2) ** 2;
    return 2 * r * Math.asin(Math.min(1, Math.sqrt(a)));
  }

  const marqueurs = [];
  REFUGES.forEach((r) => {
    const couleur = r.climatisee ? '#1b77ba' : '#16a34a';
    const contenu = (distance) => '<strong>' + echapper(r.nom) + '</strong><br>' + echapper(r.adresse)
      + (r.quartier ? '<br>Zone : ' + echapper(r.quartier) : '')
      + '<br>' + distance + ' m · ' + (r.climatisee ? 'Salle climatisée' : "Zone d'ombre / parc");
    const marker = L.circleMarker([r.lat, r.lng], { radius: 9, color: couleur, fillColor: couleur, fillOpacity: 0.75, weight: 2 })
      .addTo(carte).bindPopup(contenu(r.distanceM));
    marqueurs.push({ marker: marker, refuge: r, contenu: contenu });
  });

  // Bouton « Me localiser » : recentre sur la position du navigateur et
  // recalcule les distances affichées dans les popups.
  const btn = document.getElementById('btn-localiser-dashboard');
  if (btn && navigator.geolocation) {
    btn.addEventListener('click', () => {
      btn.disabled = true;
      navigator.geolocation.getCurrentPosition((pos) => {
        const lat = pos.coords.latitude;
        const lng = pos.coords.longitude;
        carte.setView([lat, lng], 15);
        marqueurs.forEach((item) => {
          item.refuge.distanceM = Math.round(distanceKm(lat, lng, item.refuge.lat, item.refuge.lng) * 1000);
          item.marker.setPopupContent(item.contenu(item.refuge.distanceM));
        });
        L.circleMarker([lat, lng], { radius: 7, color: '#dc2626', fillColor: '#dc2626', fillOpacity: 1, weight: 3 })
          .addTo(carte).bindPopup('<strong>Votre position actuelle</strong>').openPopup();
        btn.disabled = false;
      }, () => { btn.disabled = false; });
    });
  }
})();
</script>
</x-app-layout>
