<!DOCTYPE html>
<html class="dark" lang="fr">
<head>
<meta charset="utf-8" />
<meta name="viewport" content="width=device-width, initial-scale=1" />
<meta name="csrf-token" content="{{ csrf_token() }}" />
<title>{{ config('app.name', 'ChillNet') }} — {{ $title ?? 'Votre espace citoyen' }}</title>
@include('layouts.stitch-head')
@vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="bg-background font-body-md text-body-md text-on-surface antialiased min-h-screen">
@include('layouts.stitch-nav')
<main class="w-full pt-16 min-h-screen">
<div class="w-full max-w-[1440px] mx-auto px-margin md:px-margin-lg py-space-lg">
<div class="grid grid-cols-1 lg:grid-cols-2 gap-space-lg items-stretch">

{{-- Panneau visuel — image plein cadre sur toute la partie gauche --}}
<aside class="hidden lg:flex relative overflow-hidden rounded-xl shadow-xl border border-outline-variant/15 min-h-[560px]">
<img src="https://images.unsplash.com/photo-1504608524841-42fe6f032b4b?q=80&w=1200&auto=format&fit=crop" alt="Quartier en été" class="absolute inset-0 h-full w-full object-cover" />
<div class="absolute inset-0" style="background:linear-gradient(to top, rgba(5,8,15,.88) 10%, rgba(5,8,15,.35) 55%, rgba(5,8,15,.15))"></div>
<div class="relative z-10 mt-auto flex flex-col gap-space-sm p-space-lg">
<span class="font-label-sm text-label-sm uppercase tracking-widest text-primary font-bold">ChillNet</span>
<h2 class="font-headline-lg text-headline-lg text-white">Restez au frais, ensemble.</h2>
<div class="flex flex-wrap gap-2">
<span class="rounded-full bg-black/45 px-3 py-1 font-label-sm text-label-sm text-slate-200 backdrop-blur">Alertes</span>
<span class="rounded-full bg-black/45 px-3 py-1 font-label-sm text-label-sm text-slate-200 backdrop-blur">Refuges climatisés</span>
<span class="rounded-full bg-black/45 px-3 py-1 font-label-sm text-label-sm text-slate-200 backdrop-blur">Entraide</span>
</div>
<p class="font-body-sm text-body-sm text-slate-300">Urgence : <a class="text-primary hover:underline" href="tel:15">15</a> · <a class="text-primary hover:underline" href="tel:0800066666">0800 06 66 66</a></p>
</div>
</aside>

{{-- Formulaire --}}
<div class="w-full max-w-md mx-auto lg:mx-0 lg:max-w-none rounded-xl bg-surface-container-low/80 backdrop-blur-xl p-space-lg shadow-xl border border-outline-variant/15 flex flex-col gap-space-md">
<div class="flex items-center gap-space-md">
<div class="w-12 h-12 shrink-0 rounded-xl bg-surface-container flex items-center justify-center"><span class="material-symbols-outlined text-primary-container text-[26px]">shield</span></div>
<div class="flex flex-col">
<span class="font-label-sm text-label-sm uppercase tracking-widest text-primary font-bold">Plateforme Citoyenne Locale</span>
<h1 class="font-headline-sm text-headline-sm text-on-surface">{{ $title ?? 'Votre espace citoyen' }}</h1>
</div>
</div>
@isset($subtitle)
<p class="font-body-sm text-body-sm text-on-surface-variant">{{ $subtitle }}</p>
@endisset
@if (session('success'))
<div class="rounded-lg bg-surface-container border border-primary-container/30 px-3 py-2 flex items-center gap-2 font-body-sm text-body-sm"><span class="material-symbols-outlined text-primary text-[18px]">check_circle</span><span>{{ session('success') }}</span></div>
@endif
@if (session('error'))
<div class="rounded-lg bg-error-container text-on-error-container px-3 py-2 flex items-center gap-2 font-body-sm text-body-sm"><span class="material-symbols-outlined text-[18px]">warning</span><span>{{ session('error') }}</span></div>
@endif
{{ $slot }}
<p class="font-label-sm text-label-sm text-on-surface-variant text-center border-t border-outline-variant/20 pt-space-sm">🔒 Connexion sécurisée · ChillNet ne partage jamais votre adresse avec des tiers.</p>
</div>

</div>
</div>
</main>
@include('layouts.stitch-footer')
</body>
</html>
