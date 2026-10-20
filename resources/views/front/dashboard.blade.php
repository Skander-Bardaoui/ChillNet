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
<span>SAMU — 190</span>
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
<span>SAMU — 190</span>
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
<span class="font-label-md text-label-md uppercase tracking-wider text-on-surface-variant">Coupures dans ma zone</span>
<span class="px-2 py-0.5 rounded-md {{ $coupuresEnCours->isNotEmpty() ? 'bg-red-100 text-red-800' : 'bg-green-100 text-green-800' }} font-label-sm text-label-sm font-semibold">{{ $coupuresEnCours->isNotEmpty() ? 'En cours' : 'Sous contrôle' }}</span>
</div>
<div class="my-space-sm flex items-baseline gap-space-xs">
<span class="font-display-lg text-display-lg text-on-surface tracking-tighter">{{ $coupuresEnCours->count() }}</span>
<span class="font-title-md text-title-md text-on-surface-variant font-medium">en cours</span>
<span class="ml-auto font-body-sm text-body-sm text-on-surface-variant">{{ $coupuresZone->count() }} dans le quartier</span>
</div>
<div class="pt-2 flex flex-col gap-1 text-on-surface-variant font-body-sm text-body-sm">
@if ($coupuresEnCours->isNotEmpty())
@php $premiere = $coupuresEnCours->first(); @endphp
<span class="text-on-surface font-medium">{{ $premiere->lieu ?? 'Zone non précisée' }}</span>
<span>Depuis {{ $premiere->debut?->format('H:i') }} · rétablissement prévu {{ $premiere->fin?->format('H:i') ?? 'non communiqué' }}</span>
@else
<span>Aucune coupure en cours dans votre quartier.</span>
@endif
<a href="{{ route('coupures.index') }}" class="text-primary hover:underline pt-1">Voir le détail →</a>
</div>
</div>

<div class="relative overflow-hidden rounded-xl bg-surface-container/70 backdrop-blur-md p-space-md flex flex-col justify-between shadow-md">
<div class="flex items-center justify-between text-on-surface-variant">
<span class="font-label-md text-label-md uppercase tracking-wider text-on-surface-variant">Prochaine coupure programmée</span>
@if ($prochaineCoupure)
<span class="flex items-center gap-1.5 text-on-surface-variant font-label-sm text-label-sm">
<span class="w-2 h-2 rounded-full bg-tertiary-container animate-ping"></span> Planifiée
</span>
@else
<span class="font-label-sm text-label-sm text-on-surface-variant">Aucune</span>
@endif
</div>
<div class="my-space-sm flex flex-col">
@if ($prochaineCoupure)
<div class="flex items-baseline gap-2">
<span class="font-headline-lg text-headline-lg text-tertiary-fixed font-semibold tracking-tight">{{ $prochaineCoupure->debut?->format('d/m H:i') }}</span>
</div>
<span class="font-body-sm text-body-sm text-on-surface-variant mt-0.5">{{ $prochaineCoupure->lieu ?? 'Zone non précisée' }} · {{ $prochaineCoupure->type?->label() }}</span>
<p class="font-body-sm text-body-sm text-on-surface-variant mt-1">{{ \Illuminate\Support\Str::limit($prochaineCoupure->description, 90) }}</p>
@else
<span class="font-headline-lg text-headline-lg text-on-surface font-semibold tracking-tight">—</span>
<span class="font-body-sm text-body-sm text-on-surface-variant mt-0.5">Aucune coupure programmée dans votre quartier.</span>
@endif
</div>
<div class="pt-2 flex items-center justify-between bg-surface-container-high px-space-sm py-1.5 rounded-lg text-on-surface-variant font-body-sm text-body-sm">
<span class="flex items-center gap-1 text-primary">
<span class="material-symbols-outlined text-[16px]">schedule</span> Coupures suivies
</span>
<a href="{{ route('coupures.index') }}" class="font-semibold text-on-surface hover:underline">Consulter</a>
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
@php
    $catSignalement = ['fuite' => 'Fuite', 'panne_locale' => 'Panne locale', 'personne_vulnerable' => 'Personne vulnérable', 'autre' => 'Autre'];
    $urgClasses = ['vitale' => 'bg-red-100 text-red-800', 'prioritaire' => 'bg-orange-100 text-orange-800', 'normale' => 'bg-surface-variant text-on-surface-variant'];
    $urgLabels = ['vitale' => 'Vitale', 'prioritaire' => 'Prioritaire', 'normale' => 'Normale'];
@endphp
<div class="rounded-xl bg-surface-container/70 backdrop-blur-md p-space-md md:p-space-lg shadow-md flex flex-col gap-space-md">
<div class="flex items-center justify-between">
<div class="flex items-center gap-2">
<span class="material-symbols-outlined text-primary text-[22px]">feed</span>
<h3 class="font-headline-sm text-headline-sm text-on-surface">Fil direct de mon quartier</h3>
</div>
<span class="px-2 py-0.5 rounded-full bg-surface-variant font-label-sm text-label-sm text-on-surface-variant">{{ $filSignalements->count() + $filCoupures->count() }} publication(s)</span>
</div>
<div class="flex flex-col gap-space-sm divide-y-0">
@forelse ($filSignalements as $signalement)
<div class="flex items-start gap-space-md p-space-sm rounded-lg bg-surface-container-high/40 hover:bg-surface-container-high/80 transition-colors">
<div class="w-9 h-9 rounded-lg bg-primary-container/20 text-primary flex items-center justify-center shrink-0">
<span class="material-symbols-outlined text-[20px]">report</span>
</div>
<div class="flex flex-col flex-1 gap-0.5">
<div class="flex items-center justify-between gap-2">
<span class="font-title-md text-title-md text-on-surface font-medium">{{ $catSignalement[$signalement->categorie] ?? ucfirst($signalement->categorie) }} — {{ $signalement->residence?->nom ?? 'Sans résidence' }}</span>
<span class="font-label-sm text-label-sm text-on-surface-variant whitespace-nowrap">{{ $signalement->created_at?->diffForHumans() }}</span>
</div>
<p class="font-body-md text-body-md text-on-surface-variant">{{ \Illuminate\Support\Str::limit($signalement->description, 150) }}</p>
<div class="flex items-center gap-2 pt-1 flex-wrap">
<span class="px-2 py-0.5 rounded-full {{ $urgClasses[$signalement->urgence] ?? 'bg-surface-variant text-on-surface-variant' }} font-label-sm text-label-sm font-semibold">{{ $urgLabels[$signalement->urgence] ?? ucfirst($signalement->urgence) }}</span>
<span class="text-on-surface-variant font-body-sm text-body-sm">Signalé par {{ $signalement->habitant?->name ?? 'un habitant' }}</span>
</div>
</div>
</div>
@empty
@endforelse
@foreach ($filCoupures as $coupure)
<div class="flex items-start gap-space-md p-space-sm rounded-lg bg-surface-container-high/40 hover:bg-surface-container-high/80 transition-colors">
<div class="w-9 h-9 rounded-lg bg-secondary-container/30 text-secondary flex items-center justify-center shrink-0">
<span class="material-symbols-outlined text-[20px]">electric_bolt</span>
</div>
<div class="flex flex-col flex-1 gap-0.5">
<div class="flex items-center justify-between gap-2">
<span class="font-title-md text-title-md text-on-surface font-medium">{{ $coupure->type?->label() }} — {{ $coupure->lieu ?? $coupure->quartier?->nom ?? 'Zone non précisée' }}</span>
<span class="font-label-sm text-label-sm text-on-surface-variant whitespace-nowrap">{{ $coupure->debut?->diffForHumans() }}</span>
</div>
<p class="font-body-md text-body-md text-on-surface-variant">{{ \Illuminate\Support\Str::limit($coupure->description, 150) }}</p>
<div class="flex items-center gap-2 pt-1">
<span class="text-on-surface-variant font-body-sm text-body-sm">{{ $coupure->statut?->label() }} · {{ $coupure->confirmations }} confirmation(s)</span>
</div>
</div>
</div>
@endforeach
@if ($filSignalements->isEmpty() && $filCoupures->isEmpty())
<div class="p-space-sm rounded-lg bg-surface-container-high/40 text-on-surface-variant font-body-sm text-body-sm">Aucune publication récente dans votre quartier.</div>
@endif
</div>
</div>
</div>

{{-- Colonne droite : dispositif + réflexes --}}
<div class="lg:col-span-4 flex flex-col gap-space-lg" id="quick-actions">
<div class="rounded-xl bg-gradient-to-b from-surface-container-high to-surface-container p-space-md md:p-space-lg shadow-xl relative overflow-hidden">
<div class="absolute -right-12 -top-12 w-40 h-40 bg-primary-container/20 rounded-full blur-3xl pointer-events-none"></div>
<div class="flex flex-col gap-space-sm">
<span class="font-label-sm text-label-sm text-primary uppercase tracking-wider font-semibold">Prévention canicule</span>
<h3 class="font-headline-sm text-headline-sm text-on-surface">Conseils du moment</h3>
<p class="font-body-sm text-body-sm text-on-surface-variant">Recommandations publiées par l'équipe ChillNet pour votre foyer.</p>
<div class="mt-space-sm flex flex-col gap-2">
@forelse ($conseils as $conseil)
<div class="p-space-sm rounded-xl bg-surface-container-lowest/80 flex items-start gap-space-sm">
<div class="w-9 h-9 rounded-lg bg-primary-container/20 text-primary flex items-center justify-center shrink-0">
<span class="material-symbols-outlined text-[20px]">{{ $conseil->icone ?: 'lightbulb' }}</span>
</div>
<div class="flex flex-col">
<span class="font-title-sm text-title-sm text-on-surface font-semibold">{{ $conseil->titre }}</span>
<span class="font-body-sm text-body-sm text-on-surface-variant">{{ \Illuminate\Support\Str::limit($conseil->contenu, 130) }}</span>
</div>
</div>
@empty
<p class="font-body-sm text-body-sm text-on-surface-variant">Aucun conseil publié pour le moment.</p>
@endforelse
</div>
<a href="{{ route('conseils') }}" class="font-body-sm text-body-sm text-primary hover:underline">Voir tous les conseils →</a>
</div>
</div>

<div class="rounded-xl bg-surface-container/70 backdrop-blur-md p-space-md md:p-space-lg shadow-md flex flex-col gap-space-md">
<div class="flex items-center justify-between">
<h3 class="font-title-md text-title-md text-on-surface">Mes équipements sensibles</h3>
<span class="font-label-sm text-label-sm px-2 py-0.5 rounded-full bg-surface-container-high text-primary font-semibold">{{ $equipements->count() }} déclaré(s)</span>
</div>
<p class="font-body-sm text-body-sm text-on-surface-variant">Équipements suivis pour être priorisés en cas de coupure.</p>
<div class="flex flex-col gap-space-sm">
@forelse ($equipements as $equipement)
<div class="flex items-start gap-space-sm p-space-sm rounded-lg bg-surface-container-high/60">
<span class="material-symbols-outlined text-primary text-[20px] shrink-0">{{ $equipement->criticite?->value === 'vitale' ? 'emergency' : 'power' }}</span>
<div class="flex flex-col flex-1">
<span class="font-body-md text-body-md text-on-surface font-medium">{{ $equipement->nom }}</span>
<span class="font-body-sm text-body-sm text-on-surface-variant">{{ $equipement->type?->nom ?? 'Type non précisé' }}@if ($equipement->notes) · {{ $equipement->notes }}@endif</span>
</div>
<span class="px-2 py-0.5 rounded-full bg-surface-variant text-on-surface-variant font-label-sm text-label-sm font-semibold whitespace-nowrap">{{ $equipement->criticite?->label() }}</span>
</div>
@empty
<p class="font-body-sm text-body-sm text-on-surface-variant">Aucun équipement déclaré.</p>
@endforelse
</div>
<div class="mt-space-xs p-space-sm rounded-lg bg-surface-container-lowest flex items-center justify-between">
<div class="flex items-center gap-2">
<span class="material-symbols-outlined text-error text-[20px]">phone_in_talk</span>
<div class="flex flex-col">
<span class="font-label-sm text-label-sm text-on-surface font-semibold">Urgences Santé</span>
<span class="font-body-sm text-body-sm text-on-surface-variant">SAMU 190 · Protection civile 198</span>
</div>
</div>
<a class="px-2.5 py-1 rounded bg-surface-variant text-on-surface font-label-sm text-label-sm hover:bg-surface-bright transition-colors font-medium" href="tel:190">Appeler</a>
</div>
</div>

<div class="relative overflow-hidden rounded-xl bg-surface-container-low shadow-md">
<div class="p-space-md md:p-space-lg flex flex-col gap-2 bg-gradient-to-br from-primary-container/20 via-surface-container-low to-surface-container-high">
<div class="flex items-center gap-1.5 text-primary font-label-sm text-label-sm font-semibold uppercase">
<span class="material-symbols-outlined text-[16px]">park</span> Refuge le plus proche
</div>
@if ($refuges->isNotEmpty())
@php $refuge = $refuges->first(); @endphp
<h4 class="font-title-md text-title-md text-on-surface font-semibold">{{ $refuge['nom'] }}</h4>
<p class="font-body-sm text-body-sm text-on-surface-variant">{{ $refuge['adresse'] ?? 'Adresse non renseignée' }} · {{ $refuge['distanceM'] }} m</p>
<p class="font-body-sm text-body-sm text-on-surface-variant">{{ $refuge['climatisee'] ? 'Salle climatisée' : 'Zone d\'ombre / point de fraîcheur' }}</p>
<a href="{{ route('refuges.index') }}" class="mt-1 font-body-sm text-body-sm text-primary hover:underline">Voir la carte des refuges →</a>
@else
<h4 class="font-title-md text-title-md text-on-surface font-semibold">Aucun refuge répertorié</h4>
<p class="font-body-sm text-body-sm text-on-surface-variant">Aucun point de fraîcheur à moins de 800 m de votre foyer.</p>
<a href="{{ route('refuges.index') }}" class="mt-1 font-body-sm text-body-sm text-primary hover:underline">Explorer les refuges →</a>
@endif
</div>
</div>
</div>
</div>
</div>

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
