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
@include('layouts.stitch-nav')
<div class="pt-16 min-h-screen flex">
<aside class="hidden md:flex w-64 shrink-0 flex-col bg-surface-container-lowest/80 backdrop-blur-xl border-r border-outline-variant/20">
<div class="px-space-md py-space-md flex flex-col gap-space-xs">
<a href="{{ route('back.dashboard') }}" class="px-3 py-2 rounded-lg font-label-md text-label-md {{ request()->routeIs('back.dashboard') ? 'bg-primary-container text-on-primary-container font-semibold' : 'text-on-surface-variant hover:bg-surface-container-high hover:text-on-surface' }}">Tableau de bord</a>
@if(auth()->user()->isAdmin())
<a href="{{ route('back.quartiers.index') }}" class="px-3 py-2 rounded-lg font-label-md text-label-md {{ request()->routeIs('back.quartiers.*') ? 'bg-primary-container text-on-primary-container font-semibold' : 'text-on-surface-variant hover:bg-surface-container-high hover:text-on-surface' }}">Quartiers</a>
@endif
<a href="{{ route('back.residences.index') }}" class="px-3 py-2 rounded-lg font-label-md text-label-md {{ request()->routeIs('back.residences.*') ? 'bg-primary-container text-on-primary-container font-semibold' : 'text-on-surface-variant hover:bg-surface-container-high hover:text-on-surface' }}">Résidences</a>
<a href="{{ route('back.alertes.index') }}" class="px-3 py-2 rounded-lg font-label-md text-label-md {{ request()->routeIs('back.alertes.*') ? 'bg-primary-container text-on-primary-container font-semibold' : 'text-on-surface-variant hover:bg-surface-container-high hover:text-on-surface' }}">Alertes</a>
<a href="{{ route('back.coupures.index') }}" class="px-3 py-2 rounded-lg font-label-md text-label-md {{ request()->routeIs('back.coupures.*') ? 'bg-primary-container text-on-primary-container font-semibold' : 'text-on-surface-variant hover:bg-surface-container-high hover:text-on-surface' }}">Coupures</a>
<a href="{{ route('back.points.index') }}" class="px-3 py-2 rounded-lg font-label-md text-label-md {{ request()->routeIs('back.points.*') ? 'bg-primary-container text-on-primary-container font-semibold' : 'text-on-surface-variant hover:bg-surface-container-high hover:text-on-surface' }}">Points de fraîcheur</a>
<a href="{{ route('back.conseils.index') }}" class="px-3 py-2 rounded-lg font-label-md text-label-md {{ request()->routeIs('back.conseils.*') ? 'bg-primary-container text-on-primary-container font-semibold' : 'text-on-surface-variant hover:bg-surface-container-high hover:text-on-surface' }}">Conseils</a>
<a href="{{ route('back.signalements.index') }}" class="px-3 py-2 rounded-lg font-label-md text-label-md {{ request()->routeIs('back.signalements.*') ? 'bg-primary-container text-on-primary-container font-semibold' : 'text-on-surface-variant hover:bg-surface-container-high hover:text-on-surface' }}">Signalements</a>
<a href="{{ route('home') }}" class="px-3 py-2 rounded-lg font-label-md text-label-md text-on-surface-variant hover:bg-surface-container-high hover:text-on-surface mt-4 border-t border-outline-variant/20 pt-4">&larr; Retour au site</a>
</div>
</aside>
<div class="flex-1 w-full max-w-[1440px] mx-auto px-margin md:px-margin-lg py-space-md flex flex-col gap-space-lg">
<div class="flex items-center justify-between">
<h1 class="font-headline-lg text-headline-lg text-on-surface">{{ $title ?? 'Gestion' }}</h1>
<span class="font-label-sm text-label-sm text-on-surface-variant">{{ auth()->user()->name }} · {{ auth()->user()->role->label() }}</span>
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
@include('layouts.stitch-footer')
</body>
</html>
