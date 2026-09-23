{{-- Navbar ChillNet — liens contextuels : vitrine si invité, espace citoyen si connecté --}}
@php
    $isManager = auth()->check() && (auth()->user()->isAdmin() || auth()->user()->isGestionnaire());
    // Invité : navigation 100% landing (ancres internes, aucun renvoi ailleurs).
    // Connecté : navigation 100% pages dédiées, sans aucun lien vers l'accueil.
    $navLinks = auth()->check()
        ? array_filter([
            ['href' => route('dashboard'), 'label' => 'Mon espace', 'active' => request()->routeIs('dashboard')],
            ['href' => route('alertes.index'), 'label' => 'Alertes', 'active' => request()->routeIs('alertes.*')],
            ['href' => route('coupures.index'), 'label' => 'Coupures', 'active' => request()->routeIs('coupures.*')],
            ['href' => route('refuges.index'), 'label' => 'Refuges', 'active' => request()->routeIs('refuges.*', 'points.*')],
            ['href' => route('conseils'), 'label' => 'Conseils', 'active' => request()->routeIs('conseils')],
            ['href' => route('signalements.index'), 'label' => 'Signalements', 'active' => request()->routeIs('signalements.*')],
            $isManager ? ['href' => route('back.dashboard'), 'label' => 'Gestion', 'active' => request()->routeIs('back.*')] : null,
        ])
        : [
            ['href' => route('home').'#accueil', 'label' => 'Accueil', 'active' => request()->routeIs('home')],
            ['href' => route('home').'#refuges', 'label' => 'Refuges', 'active' => false],
            ['href' => route('home').'#quartiers', 'label' => 'Quartiers', 'active' => false],
            ['href' => route('home').'#conseils', 'label' => 'Conseils', 'active' => false],
        ];
    // Le logo renvoie vers l'espace du visiteur : dashboard si connecté, landing sinon.
    $logoHref = auth()->check() ? route('dashboard') : route('home');
@endphp
<header class="fixed top-0 left-0 right-0 z-50 bg-surface-container-lowest/80 backdrop-blur-2xl shadow-[0_4px_24px_rgba(0,0,0,0.4)]">
<div class="h-16 w-full max-w-[1440px] mx-auto px-margin md:px-margin-lg flex items-center justify-between gap-space-md">
<div class="flex items-center gap-space-md shrink-0">
<a href="{{ $logoHref }}" class="flex items-center gap-space-md">
<div class="w-10 h-10 rounded-xl bg-surface-container flex items-center justify-center shadow-inner">
<span class="material-symbols-outlined text-primary-container text-[26px]">ac_unit</span>
</div>
<div class="flex flex-col">
<span class="font-title-md text-title-md text-primary tracking-tight">ChillNet</span>
<span class="font-label-sm text-label-sm text-on-surface-variant hidden sm:inline-block">Canicule &amp; Réseau</span>
</div>
</a>
</div>
<div class="hidden xl:flex items-center gap-space-sm px-space-sm py-1 rounded-full bg-surface-container/70">
<span class="relative flex h-2 w-2"><span class="animate-ping absolute inline-flex h-full w-full rounded-full bg-tertiary-container opacity-75"></span><span class="relative inline-flex rounded-full h-2 w-2 bg-tertiary-fixed-dim"></span></span>
<span class="font-label-sm text-label-sm text-tertiary-fixed tracking-wide uppercase">Vigilance Canicule</span>
<span class="text-outline-variant font-body-sm text-body-sm">|</span>
<span class="font-body-sm text-body-sm text-on-surface-variant">Réseau sous surveillance</span>
</div>
<nav class="hidden lg:flex items-center gap-space-xs" aria-label="Navigation principale">
@foreach ($navLinks as $link)
<a href="{{ $link['href'] }}" class="px-space-sm py-1.5 rounded-lg font-label-md text-label-md transition-colors {{ $link['active'] ? 'bg-primary-container text-on-primary-container font-semibold shadow-[0_0_16px_rgba(0,229,255,0.25)]' : 'text-on-surface-variant hover:bg-surface-container-high hover:text-on-surface' }}">{{ $link['label'] }}</a>
@endforeach
@auth
<form method="POST" action="{{ route('logout') }}" class="inline">@csrf<button type="submit" class="px-space-sm py-1.5 rounded-lg font-label-md text-label-md text-on-surface-variant hover:bg-surface-container-high hover:text-on-surface transition-colors">Déconnexion</button></form>
@else
<a href="{{ route('login') }}" class="px-space-sm py-1.5 rounded-lg font-label-md text-label-md text-on-surface-variant hover:bg-surface-container-high hover:text-on-surface transition-colors">Connexion</a>
<a href="{{ route('register') }}" class="px-space-sm py-1.5 rounded-lg bg-primary-container text-on-primary-container font-label-md text-label-md font-semibold hover:opacity-95 transition">Inscription</a>
@endauth
</nav>
{{-- Navigation mobile --}}
<div class="flex lg:hidden items-center gap-1" x-data="{ open: false }">
@guest
<a href="{{ route('register') }}" class="px-space-sm py-1.5 rounded-lg bg-primary-container text-on-primary-container font-label-md text-label-md font-semibold">Inscription</a>
@endguest
<button type="button" @click="open = !open" class="p-2 rounded-full text-on-surface-variant hover:bg-surface-container-high" :aria-expanded="open ? 'true' : 'false'" aria-label="Ouvrir le menu">
<span class="material-symbols-outlined" x-text="open ? 'close' : 'menu'">menu</span>
</button>
<div x-show="open" x-cloak @click.outside="open = false" class="absolute left-margin right-margin top-16 rounded-xl border border-outline-variant/20 bg-surface-container-low/95 p-space-sm shadow-xl backdrop-blur-xl flex flex-col gap-1">
@foreach ($navLinks as $link)
<a href="{{ $link['href'] }}" @click="open = false" class="px-3 py-2 rounded-lg font-label-md text-label-md text-on-surface-variant hover:bg-surface-container-high hover:text-on-surface">{{ $link['label'] }}</a>
@endforeach
<div class="h-px bg-outline-variant/20 my-1"></div>
@auth
<a href="{{ route('equipements.index') }}" class="px-3 py-2 rounded-lg font-label-md text-label-md text-on-surface-variant hover:bg-surface-container-high hover:text-on-surface">Mes équipements</a>
<a href="{{ route('profile.edit') }}" class="px-3 py-2 rounded-lg font-label-md text-label-md text-on-surface-variant hover:bg-surface-container-high hover:text-on-surface">Mon profil</a>
<form method="POST" action="{{ route('logout') }}">@csrf<button type="submit" class="w-full text-left px-3 py-2 rounded-lg font-label-md text-label-md text-on-surface-variant hover:bg-surface-container-high hover:text-on-surface">Déconnexion</button></form>
@else
<a href="{{ route('login') }}" class="px-3 py-2 rounded-lg font-label-md text-label-md text-on-surface-variant hover:bg-surface-container-high hover:text-on-surface">Connexion</a>
@endauth
</div>
</div>
</div>
</header>
