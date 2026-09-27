{{-- Footer ChillNet — minimal : une seule ligne fine --}}
<footer class="w-full bg-surface-container-lowest border-t border-outline-variant/20">
<div class="w-full max-w-[1440px] mx-auto px-margin md:px-margin-lg py-3 flex flex-col sm:flex-row items-center justify-between gap-2 font-label-sm text-label-sm text-on-surface-variant">
<span class="inline-flex items-center gap-1.5">
<span class="flex h-6 w-6 items-center justify-center rounded-lg bg-surface-container"><span class="material-symbols-outlined text-primary-container text-[16px]">ac_unit</span></span>
<span>© 2026 ChillNet</span>
</span>
<nav class="flex flex-wrap items-center justify-center gap-x-4 gap-y-1" aria-label="Pied de page">
<a class="hover:text-on-surface transition-colors" href="{{ route('coupures.index') }}">Coupures</a>
<a class="hover:text-on-surface transition-colors" href="{{ route('home') }}#refuges">Refuges</a>
<a class="hover:text-on-surface transition-colors" href="{{ route('conseils') }}">Conseils</a>
<a class="hover:text-on-surface transition-colors" href="tel:190">Urgence : 190</a>
</nav>
</div>
</footer>
