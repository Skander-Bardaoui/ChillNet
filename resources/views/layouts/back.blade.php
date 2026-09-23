@php
    // Espace de gestion (admin / gestionnaire) : AUCUNE navbar ni footer du site ici — la
    // barre latérale est la seule navigation, le manager n'a que son espace de gestion.
    $gestionnaire = auth()->user();
    $estAdmin = $gestionnaire->isAdmin();
    $liens = array_values(array_filter([
        ['href' => route('back.dashboard'), 'label' => 'Tableau de bord', 'active' => request()->routeIs('back.dashboard')],
        $estAdmin ? ['href' => route('back.quartiers.index'), 'label' => 'Quartiers', 'active' => request()->routeIs('back.quartiers.*')] : null,
        ['href' => route('back.residences.index'), 'label' => $estAdmin ? 'Résidences' : 'Ma résidence', 'active' => request()->routeIs('back.residences.*')],
        ['href' => route('back.alertes.index'), 'label' => 'Alertes', 'active' => request()->routeIs('back.alertes.*')],
        ['href' => route('back.coupures.index'), 'label' => 'Coupures', 'active' => request()->routeIs('back.coupures.*')],
        ['href' => route('back.points.index'), 'label' => 'Points de fraîcheur', 'active' => request()->routeIs('back.points.*')],
        ['href' => route('back.conseils.index'), 'label' => 'Conseils', 'active' => request()->routeIs('back.conseils.*')],
        ['href' => route('back.signalements.index'), 'label' => 'Signalements', 'active' => request()->routeIs('back.signalements.*')],
    ]));
@endphp
<!DOCTYPE html>
<html class="dark" lang="fr">
<head>
<meta charset="utf-8" />
<meta name="viewport" content="width=device-width, initial-scale=1" />
<meta name="csrf-token" content="{{ csrf_token() }}" />
<title>{{ config('app.name', 'ChillNet') }} — Gestion {{ isset($title) && $title ? '· '.$title : '' }}</title>
@include('layouts.stitch-head')
@vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="bg-background font-body-md text-body-md text-on-surface antialiased min-h-screen">
<div class="min-h-screen flex flex-col md:flex-row">

{{-- Barre latérale de gestion : marque, navigation, sortie. Sur mobile elle devient une
     bande horizontale défilante, pour que la gestion reste utilisable sans navbar. --}}
<aside class="flex items-center md:items-stretch md:flex-col gap-space-xs md:w-64 md:shrink-0 px-margin py-space-sm md:py-space-md border-b md:border-b-0 md:border-r border-outline-variant/20 bg-surface-container-lowest/80 backdrop-blur-xl overflow-x-auto md:overflow-visible">

<div class="hidden md:flex flex-col gap-space-sm pb-space-sm mb-space-xs border-b border-outline-variant/20">
<a href="{{ route('back.dashboard') }}" class="flex items-center gap-space-sm">
<span class="flex h-10 w-10 items-center justify-center rounded-xl bg-surface-container"><span class="material-symbols-outlined text-primary-container text-[24px]">ac_unit</span></span>
<span class="flex flex-col">
<span class="font-title-md text-title-md text-primary font-semibold">ChillNet</span>
<span class="font-label-sm text-label-sm text-on-surface-variant">Espace de gestion</span>
</span>
</a>
<span class="font-label-sm text-label-sm text-on-surface-variant truncate">{{ $gestionnaire->name }} · {{ $gestionnaire->role->label() }}</span>
</div>

@foreach ($liens as $lien)
<a href="{{ $lien['href'] }}" class="whitespace-nowrap px-3 py-2 rounded-lg font-label-md text-label-md transition-colors {{ $lien['active'] ? 'bg-primary-container text-on-primary-container font-semibold' : 'text-on-surface-variant hover:bg-surface-container-high hover:text-on-surface' }}">{{ $lien['label'] }}</a>
@endforeach

<div class="flex items-center gap-space-xs shrink-0 md:mt-auto md:pt-space-sm md:border-t md:border-outline-variant/20">
<a href="{{ route('home') }}" class="whitespace-nowrap px-3 py-2 rounded-lg font-label-md text-label-md text-on-surface-variant hover:bg-surface-container-high hover:text-on-surface">&larr; Site</a>
<form method="POST" action="{{ route('logout') }}" class="shrink-0">@csrf<button type="submit" class="whitespace-nowrap px-3 py-2 rounded-lg font-label-md text-label-md text-on-surface-variant hover:bg-surface-container-high hover:text-on-surface">Déconnexion</button></form>
</div>
</aside>

<div class="flex-1 w-full max-w-[1440px] mx-auto px-margin md:px-margin-lg py-space-md flex flex-col gap-space-lg">
<div class="flex items-center justify-between gap-space-md">
<h1 class="font-headline-lg text-headline-lg text-on-surface">{{ $title ?? 'Gestion' }}</h1>
<span class="md:hidden font-label-sm text-label-sm text-on-surface-variant truncate">{{ $gestionnaire->name }} · {{ $gestionnaire->role->label() }}</span>
</div>
@if (session('success'))
<div class="rounded-xl bg-surface-container-low border border-primary-container/30 text-on-surface px-space-md py-space-sm flex items-center gap-2"><span class="material-symbols-outlined text-primary">check_circle</span><span>{{ session('success') }}</span></div>
@endif
@if (session('error'))
<div class="rounded-xl bg-error-container text-on-error-container px-space-md py-space-sm flex items-center gap-2"><span class="material-symbols-outlined">warning</span><span>{{ session('error') }}</span></div>
@endif
{{ $slot }}
</div>
</div>
</body>
</html>
