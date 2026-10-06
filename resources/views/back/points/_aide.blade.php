{{-- Colonne d'aide des formulaires back office (création / modification). --}}
<aside class="flex flex-col gap-3 lg:sticky lg:top-6">
@if (isset($point) && $point)
<div class="rounded-2xl bg-surface-container-low shadow-sm border border-outline-variant/20 p-space-md">
<h2 class="font-title-md text-title-md text-on-surface font-semibold flex items-center gap-2"><span class="material-symbols-outlined text-primary">info</span>État actuel</h2>
<div class="mt-2 flex flex-col gap-2 font-body-sm text-body-sm text-on-surface-variant">
<p><strong class="text-on-surface">Statut :</strong> <span class="px-2 py-0.5 rounded-full font-label-sm text-label-sm {{ $point->statut->badgeClasses() }}">{{ $point->statut->label() }}</span></p>
<p><strong class="text-on-surface">Quartier :</strong> {{ $point->quartier?->nom ?? '—' }} <span class="text-on-surface-variant/80">(déduit du point GPS)</span></p>
<p><strong class="text-on-surface">Proposé par :</strong> {{ $point->auteur?->name ?? '—' }}</p>
</div>
</div>
@endif
<div class="rounded-2xl bg-surface-container-low shadow-sm border border-outline-variant/20 p-space-md">
<h2 class="font-title-md text-title-md text-on-surface font-semibold flex items-center gap-2"><span class="material-symbols-outlined text-primary">lightbulb</span>Règles de saisie</h2>
<ul class="mt-2 flex flex-col gap-2 font-body-sm text-body-sm text-on-surface-variant">
<li class="flex gap-2"><span class="material-symbols-outlined text-green-700 text-[18px] shrink-0">check</span><span>Capacité obligatoire sauf pour une fontaine.</span></li>
<li class="flex gap-2"><span class="material-symbols-outlined text-green-700 text-[18px] shrink-0">check</span><span>Horaires obligatoires sauf « 24h/24 » ; nocturne après minuit acceptée.</span></li>
<li class="flex gap-2"><span class="material-symbols-outlined text-green-700 text-[18px] shrink-0">check</span><span>Coordonnées GPS en Tunisie, sans doublon du même type à moins de 50 m.</span></li>
<li class="flex gap-2"><span class="material-symbols-outlined text-green-700 text-[18px] shrink-0">check</span><span>Les équipements (PMR, clim, ombre, eau) alimentent la recommandation IA.</span></li>
</ul>
</div>
</aside>
