{{-- Footer ChillNet — structure par colonnes : marque, exploration, ressources, espace citoyen --}}
<footer class="w-full bg-surface-container-lowest py-space-xl shadow-[0_-1px_12px_rgba(0,0,0,0.3)]">
<div class="w-full max-w-[1440px] mx-auto px-margin md:px-margin-lg flex flex-col gap-space-lg">

<div class="grid grid-cols-1 md:grid-cols-2 xl:grid-cols-4 gap-space-lg">

{{-- Marque --}}
<div class="flex flex-col gap-space-sm">
<a href="{{ route('home') }}" class="flex items-center gap-space-sm">
<span class="flex h-10 w-10 items-center justify-center rounded-xl bg-surface-container"><span class="material-symbols-outlined text-primary-container text-[24px]">ac_unit</span></span>
<span class="font-title-md text-title-md text-primary font-semibold">ChillNet</span>
</a>
<p class="font-body-sm text-body-sm text-on-surface-variant max-w-xs">ChillNet · Veille climatique &amp; solidarité citoyenne. Anticiper la canicule, quartier par quartier, foyer par foyer.</p>
<div class="flex flex-col gap-1 font-body-sm text-body-sm text-on-surface-variant">
<a class="hover:text-on-surface transition-colors inline-flex items-center gap-2" href="tel:15"><span class="material-symbols-outlined text-[16px] text-tertiary-fixed">call</span>SAMU : 15</a>
<a class="hover:text-on-surface transition-colors inline-flex items-center gap-2" href="tel:0800066666"><span class="material-symbols-outlined text-[16px] text-tertiary-fixed">call</span>N° Vert Canicule : 0800 06 66 66</a>
</div>
</div>

{{-- Exploration de la page d'accueil --}}
<nav class="flex flex-col gap-space-sm" aria-label="Explorer le site">
<span class="font-label-sm text-label-sm uppercase tracking-widest text-primary font-bold">Explorer</span>
<div class="flex flex-col gap-2 font-body-sm text-body-sm text-on-surface-variant">
@auth<a class="hover:text-on-surface transition-colors" href="{{ route('alertes.index') }}">Alertes canicule</a>@endauth
<a class="hover:text-on-surface transition-colors" href="{{ route('coupures.index') }}">Coupures de courant</a>
<a class="hover:text-on-surface transition-colors" href="{{ route('home') }}#refuges">Points de fraîcheur</a>
<a class="hover:text-on-surface transition-colors" href="{{ route('home') }}#quartiers">Quartiers couverts</a>
<a class="hover:text-on-surface transition-colors" href="{{ route('conseils') }}">Conseils</a>
</div>
</nav>

{{-- Ressources --}}
<div class="flex flex-col gap-space-sm">
<span class="font-label-sm text-label-sm uppercase tracking-widest text-primary font-bold">Urgences</span>
<div class="flex flex-col gap-2 font-body-sm text-body-sm text-on-surface-variant">
<a class="hover:text-on-surface transition-colors" href="tel:15">SAMU — 15</a>
<a class="hover:text-on-surface transition-colors" href="tel:0800066666">N° Vert Canicule — 0800 06 66 66</a>
</div>
</div>

{{-- Espace citoyen --}}
<div class="flex flex-col gap-space-sm">
<span class="font-label-sm text-label-sm uppercase tracking-widest text-primary font-bold">Espace citoyen</span>
@auth
<div class="flex flex-col gap-2 font-body-sm text-body-sm text-on-surface-variant">
<a class="hover:text-on-surface transition-colors" href="{{ route('dashboard') }}">Mon espace habitant</a>
@if(auth()->user()->isAdmin() || auth()->user()->isGestionnaire())
<a class="hover:text-on-surface transition-colors" href="{{ route('back.dashboard') }}">Espace de gestion</a>
@endif
<a class="hover:text-on-surface transition-colors" href="{{ route('profile.edit') }}">Mon profil</a>
</div>
@else
<div class="flex flex-col gap-space-sm">
<div class="flex flex-wrap gap-space-xs">
<a href="{{ route('login') }}" class="px-space-sm py-1.5 rounded-lg border border-outline-variant text-on-surface hover:bg-surface-container-high transition-colors font-label-md text-label-md">Connexion</a>
<a href="{{ route('register') }}" class="px-space-sm py-1.5 rounded-lg bg-primary-container text-on-primary-container font-label-md text-label-md font-semibold hover:opacity-95 transition">Inscription</a>
</div>
<p class="font-label-sm text-label-sm text-on-surface-variant">Créez votre foyer résilient en une minute.</p>
</div>
@endauth
</div>

</div>

<div class="flex flex-col md:flex-row items-center justify-between gap-space-sm border-t border-outline-variant/20 pt-space-md font-body-sm text-body-sm text-on-surface-variant">
<span>© 2026 ChillNet. Plateforme citoyenne de veille climatique.</span>
<div class="flex flex-wrap items-center gap-space-md">
<a class="hover:text-on-surface transition-colors" href="{{ route('home') }}#refuges">Refuges</a>
<a class="hover:text-on-surface transition-colors" href="{{ route('home') }}#quartiers">Quartiers</a>
</div>
</div>

</div>
</footer>
