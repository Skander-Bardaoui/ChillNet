<x-public-layout title="Coupures de courant">
<h1 class="sr-only">Coupures de courant — carte et signalement</h1>
<a href="{{ route('home') }}" class="inline-flex items-center gap-1 font-body-sm text-body-sm text-primary hover:underline"><span class="material-symbols-outlined text-[16px]">arrow_back</span>Retour à l'accueil</a>

{{-- Bandeau carte des coupures actives (statique) --}}
<section class="relative rounded-xl overflow-hidden bg-surface-container-low shadow-xl">
<img src="https://images.unsplash.com/photo-1473341304170-971dccb5ac1e?auto=format&fit=crop&w=1600&q=80" alt="" aria-hidden="true" class="w-full h-56 md:h-72 object-cover saturate-50 contrast-125 brightness-50" />
<div class="absolute inset-0 bg-gradient-to-t from-surface-container-lowest via-surface-container-lowest/30 to-transparent pointer-events-none"></div>
<div class="absolute top-space-md left-space-md flex items-center gap-space-xs bg-surface-container-lowest/90 backdrop-blur-md px-space-md py-1.5 rounded-lg shadow-sm">
<span class="h-2 w-2 rounded-full bg-tertiary-container animate-pulse"></span>
<span class="font-label-sm text-label-sm text-on-surface uppercase tracking-wide">Carte des coupures actives — secteur suivi (démo)</span>
</div>
<div class="absolute bottom-space-md left-space-md right-space-md flex flex-wrap items-center gap-space-sm">
<div class="px-space-sm py-1.5 rounded-lg bg-surface-container-lowest/90 backdrop-blur-md font-body-sm text-body-sm text-on-surface flex items-center gap-2"><span class="material-symbols-outlined text-[18px] text-tertiary-container">bolt</span>Charge réseau : <strong class="text-tertiary-fixed">84%</strong> (stable)</div>
<div class="px-space-sm py-1.5 rounded-lg bg-surface-container-lowest/90 backdrop-blur-md font-body-sm text-body-sm text-on-surface flex items-center gap-2"><span class="material-symbols-outlined text-[18px] text-primary">power_off</span>2 coupures en cours · 1 prévue</div>
<a href="#liste-coupures" class="ml-auto px-space-md py-2 rounded-lg bg-primary-container text-on-primary-container font-label-md text-label-md font-semibold hover:opacity-95 shadow">Voir la liste</a>
</div>
</section>

{{-- Liste de 3 coupures --}}
<section id="liste-coupures" class="flex flex-col gap-space-sm scroll-mt-28">
<p class="font-label-sm text-label-sm uppercase tracking-wider text-on-surface-variant px-1">Coupures suivies — 3 (données statiques de démo)</p>
<article class="rounded-xl bg-surface-container-low p-space-md shadow-sm flex flex-col md:flex-row md:items-center gap-space-sm">
<div class="p-2 rounded-lg bg-red-100 text-red-800 self-start"><span class="material-symbols-outlined text-[20px]">power_off</span></div>
<div class="flex-1">
<div class="flex items-center gap-2 flex-wrap"><h2 class="font-title-md text-title-md text-on-surface">Délestage — Faubourg Nord</h2><span class="px-2 py-0.5 rounded-full bg-red-100 text-red-800 font-label-sm text-label-sm uppercase">En cours</span></div>
<p class="font-body-sm text-body-sm text-on-surface-variant">Type : délestage · De 18h00 à 20h00 · Rue des Lilas, Oasis Nord. Batterie de quartier à 94%.</p>
</div>
<a href="tel:0800066666" class="shrink-0 inline-flex items-center gap-1 px-space-md py-2 rounded-lg bg-surface-container-high text-on-surface font-label-md text-label-md hover:bg-surface-variant"><span class="material-symbols-outlined text-[16px] text-primary">call</span>0800 06 66 66</a>
</article>
<article class="rounded-xl bg-surface-container-low p-space-md shadow-sm flex flex-col md:flex-row md:items-center gap-space-sm">
<div class="p-2 rounded-lg bg-orange-100 text-orange-800 self-start"><span class="material-symbols-outlined text-[20px]">construction</span></div>
<div class="flex-1">
<div class="flex items-center gap-2 flex-wrap"><h2 class="font-title-md text-title-md text-on-surface">Panne — Berges Sud</h2><span class="px-2 py-0.5 rounded-full bg-orange-100 text-orange-800 font-label-sm text-label-sm uppercase">Prévue</span></div>
<p class="font-body-sm text-body-sm text-on-surface-variant">Type : panne · Intervention prévue demain de 09h00 à 11h30 · Quai des Brumes.</p>
</div>
<a href="tel:0800066666" class="shrink-0 inline-flex items-center gap-1 px-space-md py-2 rounded-lg bg-surface-container-high text-on-surface font-label-md text-label-md hover:bg-surface-variant"><span class="material-symbols-outlined text-[16px] text-primary">call</span>0800 06 66 66</a>
</article>
<article class="rounded-xl bg-surface-container-low p-space-md shadow-sm flex flex-col md:flex-row md:items-center gap-space-sm">
<div class="p-2 rounded-lg bg-primary/10 text-primary self-start"><span class="material-symbols-outlined text-[20px]">check_circle</span></div>
<div class="flex-1">
<div class="flex items-center gap-2 flex-wrap"><h2 class="font-title-md text-title-md text-on-surface">Délestage — Centre Historique</h2><span class="px-2 py-0.5 rounded-full bg-primary/10 text-primary font-label-sm text-label-sm uppercase">Résolue</span></div>
<p class="font-body-sm text-body-sm text-on-surface-variant">Type : délestage · Hier de 17h00 à 18h15 · Place du Capitole. Réseau rétabli.</p>
</div>
<a href="#signaler" class="shrink-0 inline-flex items-center gap-1 px-space-md py-2 rounded-lg bg-surface-container-high text-on-surface font-label-md text-label-md hover:bg-surface-variant"><span class="material-symbols-outlined text-[16px] text-primary">rate_review</span>Voir le formulaire</a>
</article>
</section>

{{-- Formulaire Signaler une coupure (statique) --}}
<section id="signaler" class="rounded-xl bg-surface-container-low p-space-md shadow-md scroll-mt-28">
<div class="flex items-center gap-space-sm mb-space-sm">
<div class="p-2 rounded-lg bg-primary/10 text-primary"><span class="material-symbols-outlined text-[20px]">report</span></div>
<div><h2 class="font-headline-sm text-headline-sm text-on-surface">Signaler une coupure</h2><p class="font-body-sm text-body-sm text-on-surface-variant">Formulaire de démonstration — aucune donnée envoyée.</p></div>
</div>
<form action="#" method="post" class="grid grid-cols-1 md:grid-cols-2 gap-space-sm">
@csrf
<div class="flex flex-col gap-1">
<label for="zone" class="font-label-md text-label-md text-on-surface">Zone</label>
<select id="zone" name="zone" class="rounded-lg bg-surface-container-high text-on-surface px-space-sm py-2 focus:outline-none"><option>Centre Historique</option><option>Oasis Nord</option><option>Berges Sud</option></select>
</div>
<div class="flex flex-col gap-1">
<label for="type" class="font-label-md text-label-md text-on-surface">Type</label>
<select id="type" name="type" class="rounded-lg bg-surface-container-high text-on-surface px-space-sm py-2 focus:outline-none"><option>Délestage</option><option>Panne</option><option>Maintenance</option></select>
</div>
<div class="flex flex-col gap-1 md:col-span-2">
<label for="description" class="font-label-md text-label-md text-on-surface">Description</label>
<textarea id="description" name="description" rows="3" placeholder="Ex. : coupure rue des Lilas depuis 18h05…" class="rounded-lg bg-surface-container-high text-on-surface placeholder:text-outline px-space-sm py-2 focus:outline-none"></textarea>
</div>
<div class="md:col-span-2 flex items-center gap-space-sm">
<button type="submit" class="px-space-md py-2.5 rounded-lg bg-primary-container text-on-primary-container font-label-md text-label-md font-semibold hover:opacity-95 shadow inline-flex items-center gap-2"><span class="material-symbols-outlined text-[18px]">send</span>Envoyer le signalement</button>
<a href="tel:190" class="inline-flex items-center gap-1 font-label-md text-label-md text-on-surface-variant hover:text-primary"><span class="material-symbols-outlined text-[16px]">emergency</span>Urgence : 190</a>
</div>
</form>
</section>
</x-public-layout>
