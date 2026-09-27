<x-app-layout>
<x-slot name="header">
<div class="flex items-center gap-space-sm">
<div class="p-2 rounded-lg bg-primary/10 text-primary"><span class="material-symbols-outlined text-[22px]">home_health</span></div>
<div><h1 class="font-headline-sm text-headline-sm text-on-surface">Mes équipements sensibles</h1><p class="font-body-sm text-body-sm text-on-surface-variant">Déclarez vos appareils critiques pour une vigilance personnalisée (démo, aucune donnée envoyée).</p></div>
</div>
</x-slot>

{{-- Formulaire statique --}}
<section class="rounded-xl bg-surface-container-low p-space-md shadow-md" x-data="{ type: 'refrigerateur' }">
<div class="flex items-center gap-space-sm mb-space-sm">
<span class="material-symbols-outlined text-primary text-[22px]">add_home</span>
<h2 class="font-title-md text-title-md text-on-surface">Déclarer un équipement</h2>
</div>
<form action="#" method="post" class="grid grid-cols-1 md:grid-cols-2 gap-space-sm">
@csrf
<div class="flex flex-col gap-1">
<label for="type" class="font-label-md text-label-md text-on-surface">Type d'équipement</label>
<select id="type" name="type" x-model="type" class="rounded-lg bg-surface-container-high text-on-surface px-space-sm py-2 focus:outline-none"><option value="refrigerateur">Réfrigérateur / congélateur</option><option value="respirateur">Respirateur / appareil médical</option><option value="climatiseur">Climatiseur</option><option value="ventilateur">Ventilateur brumisateur</option><option value="autre">Autre équipement</option></select>
</div>
<div class="flex flex-col gap-1">
<label for="criticite" class="font-label-md text-label-md text-on-surface">Criticité</label>
<select id="criticite" name="criticite" class="rounded-lg bg-surface-container-high text-on-surface px-space-sm py-2 focus:outline-none"><option>Normale</option><option>Élevée</option><option>Vital (médical)</option></select>
</div>
<div class="flex flex-col gap-1 md:col-span-2" x-show="type === 'respirateur'" x-cloak>
<label for="contact_urgence" class="font-label-md text-label-md text-error flex items-center gap-1"><span class="material-symbols-outlined text-[16px]">emergency</span>Contact d'urgence (requis pour équipement médical — démo)</label>
<input id="contact_urgence" name="contact_urgence" type="text" placeholder="Ex. : fille — 06 12 34 56 78" class="rounded-lg bg-surface-container-high text-on-surface placeholder:text-outline px-space-sm py-2 focus:outline-none border border-error/30" />
<p class="font-body-sm text-body-sm text-on-surface-variant">En cas de coupure, ce contact sera prévenu en priorité (simulation).</p>
</div>
<div class="md:col-span-2"><button type="submit" class="px-space-md py-2.5 rounded-lg bg-primary-container text-on-primary-container font-label-md text-label-md font-semibold hover:opacity-95 shadow inline-flex items-center gap-2"><span class="material-symbols-outlined text-[18px]">save</span>Enregistrer l'équipement</button></div>
</form>
</section>

{{-- Liste statique de 2 équipements --}}
<section class="flex flex-col gap-space-sm">
<p class="font-label-sm text-label-sm uppercase tracking-wider text-on-surface-variant px-1">Équipements déclarés — 2 (démo)</p>
<article class="rounded-xl bg-surface-container-low p-space-md shadow-sm flex flex-col md:flex-row md:items-center gap-space-sm">
<div class="p-2 rounded-lg bg-primary/10 text-primary self-start"><span class="material-symbols-outlined text-[20px]">kitchen</span></div>
<div class="flex-1"><div class="flex items-center gap-2 flex-wrap"><h3 class="font-title-md text-title-md text-on-surface">Réfrigérateur familial</h3><span class="px-2 py-0.5 rounded-full bg-surface-container-high text-on-surface-variant font-label-sm text-label-sm uppercase">Criticité normale</span></div><p class="font-body-sm text-body-sm text-on-surface-variant">Cuisine · à protéger en cas de coupure &gt; 2h : glacière + point relais frais.</p></div>
<a href="tel:0800066666" class="shrink-0 inline-flex items-center gap-1 font-label-md text-label-md text-on-surface-variant hover:text-primary"><span class="material-symbols-outlined text-[16px]">call</span>0800 06 66 66</a>
</article>
<article class="rounded-xl bg-surface-container-low p-space-md shadow-sm flex flex-col md:flex-row md:items-center gap-space-sm border border-error/20">
<div class="p-2 rounded-lg bg-red-100 text-red-800 self-start"><span class="material-symbols-outlined text-[20px]">pulmonology</span></div>
<div class="flex-1"><div class="flex items-center gap-2 flex-wrap"><h3 class="font-title-md text-title-md text-on-surface">Respirateur nocturne</h3><span class="px-2 py-0.5 rounded-full bg-red-100 text-red-800 font-label-sm text-label-sm uppercase">Vital (médical)</span></div><p class="font-body-sm text-body-sm text-on-surface-variant">Chambre · batterie de secours 8h · contact d'urgence : fille — 06 12 34 56 78 (démo).</p></div>
<a href="tel:190" class="shrink-0 inline-flex items-center gap-1 px-space-md py-2 rounded-lg bg-red-600 text-white font-label-md text-label-md font-semibold"><span class="material-symbols-outlined text-[16px]">emergency</span>190 en cas de coupure</a>
</article>
</section>

{{-- 2 conseils personnalisés statiques --}}
<section class="grid grid-cols-1 md:grid-cols-2 gap-space-md">
<article class="rounded-xl bg-surface-container-low p-space-md shadow-sm flex flex-col gap-2">
<div class="flex items-center gap-2 text-primary"><span class="material-symbols-outlined text-[20px]">tips_and_updates</span><h3 class="font-title-md text-title-md text-on-surface">Conseil n°1 — chaîne du froid</h3></div>
<p class="font-body-sm text-body-sm text-on-surface-variant">En cas de coupure de plus de 2h, gardez le réfrigérateur fermé et rapprochez-vous du point de fraîcheur « Médiathèque Mandela » (350 m). <a href="{{ route('conseils') }}" class="text-primary hover:underline">Voir tous les conseils</a>.</p>
</article>
<article class="rounded-xl bg-surface-container-low p-space-md shadow-sm flex flex-col gap-2">
<div class="flex items-center gap-2 text-primary"><span class="material-symbols-outlined text-[20px]">battery_charging_full</span><h3 class="font-title-md text-title-md text-on-surface">Conseil n°2 — appareil médical</h3></div>
<p class="font-body-sm text-body-sm text-on-surface-variant">Chargez la batterie du respirateur avant 17h00 (pic réseau). En cas d'alerte, appelez le <a href="tel:190" class="text-primary font-semibold hover:underline">190</a> sans attendre.</p>
</article>
</section>
</x-app-layout>
