{{-- Navbar ChillNet — vitrine si invité (indicateur de vigilance centré + actions à droite),
     espace citoyen ou gestion si connecté. --}}
@php
    $isManager = auth()->check() && (auth()->user()->isAdmin() || auth()->user()->isGestionnaire());
    $isAdmin = auth()->check() && auth()->user()->isAdmin();
    // Invité : pas de liens dans la barre (les ancres de la landing ont été retirées) — le
    // milieu de la barre est occupé par l'indicateur de vigilance, les actions sont à droite.
    // Habitant : espace citoyen (dashboard front + modules front).
    // Manager (admin/gestionnaire) : UNIQUEMENT l'espace gestion — aucune page
    // habitant visible (ni Mon espace, ni Alertes/Coupures/Refuges...). La
    // sidebar du layout back contient déjà toute la navigation gestion.
    $habitantLinks = [
        ['href' => route('dashboard'), 'label' => 'Mon espace', 'active' => request()->routeIs('dashboard')],
        ['href' => route('alertes.index'), 'label' => 'Alertes', 'active' => request()->routeIs('alertes.*')],
        ['href' => route('coupures.index'), 'label' => 'Coupures', 'active' => request()->routeIs('coupures.*')],
        ['href' => route('refuges.index'), 'label' => 'Refuges', 'active' => request()->routeIs('refuges.*', 'points.*')],
        ['href' => route('conseils'), 'label' => 'Conseils', 'active' => request()->routeIs('conseils')],
        ['href' => route('signalements.index'), 'label' => 'Signalements', 'active' => request()->routeIs('signalements.*')],
        ['href' => route('equipements.index'), 'label' => 'Équipements', 'active' => request()->routeIs('equipements.*')],
    ];
    $managerLinks = array_filter([
        ['href' => route('back.dashboard'), 'label' => 'Gestion', 'active' => request()->routeIs('back.dashboard')],
        $isAdmin ? ['href' => route('back.quartiers.index'), 'label' => 'Quartiers', 'active' => request()->routeIs('back.quartiers.*')] : null,
        ['href' => route('back.residences.index'), 'label' => $isAdmin ? 'Résidences' : 'Ma résidence', 'active' => request()->routeIs('back.residences.*')],
    ]);
    $navLinks = auth()->check()
        ? ($isManager ? $managerLinks : $habitantLinks)
        : [];
    $sansLiens = count($navLinks) === 0;
    // Le logo renvoie vers l'espace du visiteur : back-office si manager
    // (lien direct, sans redirection), dashboard front si habitant, landing sinon.
    $logoHref = auth()->check()
        ? ($isManager ? route('back.dashboard') : route('dashboard'))
        : route('home');
@endphp
{{-- Couleur de barre dédiée (--md-nav) : plus claire que la page en sombre, blanche en clair.
     L'ombre et le liseré sont définis dans layouts/stitch-head (par thème). --}}
<header class="fixed top-3 left-3 right-3 z-50 rounded-2xl bg-nav-surface/85 backdrop-blur-2xl">
<div class="relative h-16 w-full max-w-[1440px] mx-auto px-margin md:px-margin-lg flex items-center justify-between gap-space-md">
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
@if ($sansLiens)
{{-- Indicateur de vigilance, centré dans la barre (decoratif : aucun clic). --}}
<div class="pointer-events-none absolute left-1/2 top-1/2 hidden lg:flex -translate-x-1/2 -translate-y-1/2 items-center gap-space-sm px-space-sm py-1 rounded-full bg-surface-container/70">
<span class="relative flex h-2 w-2"><span class="animate-ping absolute inline-flex h-full w-full rounded-full bg-tertiary-container opacity-75"></span><span class="relative inline-flex rounded-full h-2 w-2 bg-tertiary-fixed-dim"></span></span>
<span class="font-label-sm text-label-sm text-tertiary-fixed tracking-wide uppercase whitespace-nowrap">Vigilance Canicule</span>
<span class="text-outline-variant font-body-sm text-body-sm">|</span>
<span class="font-body-sm text-body-sm text-on-surface-variant whitespace-nowrap">Réseau sous surveillance</span>
</div>
@endif
<nav class="hidden lg:flex items-center gap-space-xs" aria-label="Navigation principale">
@foreach ($navLinks as $link)
<a href="{{ $link['href'] }}" class="px-space-sm py-1.5 rounded-lg font-label-md text-label-md transition-colors {{ $link['active'] ? 'bg-primary-container text-on-primary-container font-semibold shadow-[0_0_16px_rgba(0,229,255,0.25)]' : 'text-on-surface-variant hover:bg-surface-container-high hover:text-on-surface' }}">{{ $link['label'] }}</a>
@endforeach
@if (request()->routeIs('home', 'accueil'))
<button type="button" x-data="{ light: document.documentElement.classList.contains('light') }"
    @click="light = !light; document.documentElement.classList.toggle('light', light); document.documentElement.classList.toggle('dark', !light); try { localStorage.setItem('chillnet-theme', light ? 'light' : 'dark'); } catch (e) {}"
    class="p-2 rounded-lg text-on-surface-variant hover:bg-surface-container-high hover:text-on-surface transition-colors"
    :aria-label="light ? 'Activer le thème sombre' : 'Activer le thème clair'" aria-label="Basculer le thème">
    <span class="material-symbols-outlined text-[20px]" x-text="light ? 'dark_mode' : 'light_mode'">light_mode</span>
</button>
@endif
@auth
<form method="POST" action="{{ route('logout') }}" class="inline">@csrf<button type="submit" class="px-space-sm py-1.5 rounded-lg font-label-md text-label-md text-on-surface-variant hover:bg-surface-container-high hover:text-on-surface transition-colors">Déconnexion</button></form>
@else
<a href="{{ route('login') }}" class="px-space-sm py-1.5 rounded-lg font-label-md text-label-md text-on-surface-variant hover:bg-surface-container-high hover:text-on-surface transition-colors">Connexion</a>
<a href="{{ route('register') }}" class="px-space-sm py-1.5 rounded-lg bg-primary-container text-on-primary-container font-label-md text-label-md font-semibold hover:opacity-95 transition">Inscription</a>
@endauth
</nav>
{{-- Navigation mobile : le menu contient les liens éventuels + les actions. --}}
<div class="flex lg:hidden items-center gap-1" x-data="{ open: false }">
@if (request()->routeIs('home', 'accueil'))
<button type="button" x-data="{ light: document.documentElement.classList.contains('light') }"
    @click="light = !light; document.documentElement.classList.toggle('light', light); document.documentElement.classList.toggle('dark', !light); try { localStorage.setItem('chillnet-theme', light ? 'light' : 'dark'); } catch (e) {}"
    class="p-2 rounded-full text-on-surface-variant hover:bg-surface-container-high"
    :aria-label="light ? 'Activer le thème sombre' : 'Activer le thème clair'" aria-label="Basculer le thème">
    <span class="material-symbols-outlined text-[20px]" x-text="light ? 'dark_mode' : 'light_mode'">light_mode</span>
</button>
@endif
<button type="button" @click="open = !open" class="p-2 rounded-full text-on-surface-variant hover:bg-surface-container-high" :aria-expanded="open ? 'true' : 'false'" aria-label="Ouvrir le menu">
<span class="material-symbols-outlined" x-text="open ? 'close' : 'menu'">menu</span>
</button>
<div x-show="open" x-cloak @click.outside="open = false" class="absolute left-margin right-margin top-16 rounded-xl border border-outline-variant/20 bg-nav-surface/95 p-space-sm shadow-xl backdrop-blur-xl flex flex-col gap-1">
@foreach ($navLinks as $link)
<a href="{{ $link['href'] }}" @click="open = false" class="px-3 py-2 rounded-lg font-label-md text-label-md text-on-surface-variant hover:bg-surface-container-high hover:text-on-surface">{{ $link['label'] }}</a>
@endforeach
@if (! $sansLiens || auth()->check())
<div class="h-px bg-outline-variant/20 my-1"></div>
@endif
@auth
<a href="{{ route('profile.edit') }}" class="px-3 py-2 rounded-lg font-label-md text-label-md text-on-surface-variant hover:bg-surface-container-high hover:text-on-surface">Mon profil</a>
<form method="POST" action="{{ route('logout') }}">@csrf<button type="submit" class="w-full text-left px-3 py-2 rounded-lg font-label-md text-label-md text-on-surface-variant hover:bg-surface-container-high hover:text-on-surface">Déconnexion</button></form>
@else
<a href="{{ route('login') }}" class="px-3 py-2 rounded-lg font-label-md text-label-md text-on-surface-variant hover:bg-surface-container-high hover:text-on-surface">Connexion</a>
<a href="{{ route('register') }}" class="px-3 py-2 rounded-lg bg-primary-container text-on-primary-container font-label-md text-label-md font-semibold hover:opacity-95 transition">Inscription</a>
@endauth
</div>
</div>
</div>
</header>
