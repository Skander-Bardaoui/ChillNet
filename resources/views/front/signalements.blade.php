<x-app-layout>
<x-slot name="header">
<div class="flex items-center gap-space-sm">
<div class="p-2 rounded-lg bg-primary/10 text-primary"><span class="material-symbols-outlined text-[22px]">volunteer_activism</span></div>
<div><h1 class="font-headline-sm text-headline-sm text-on-surface">Signalements communautaires</h1><p class="font-body-sm text-body-sm text-on-surface-variant">Signalez une situation liée à la chaleur et suivez son traitement (démo, aucune donnée envoyée).</p></div>
</div>
</x-slot>

@guest
<div class="rounded-xl border border-primary-container/30 bg-surface-container-low px-space-md py-space-sm flex flex-wrap items-center gap-space-sm">
<span class="material-symbols-outlined text-primary">login</span>
<p class="font-body-sm text-body-sm text-on-surface-variant flex-1 min-w-52">Connectez-vous pour envoyer un signalement et suivre vos demandes.</p>
<a href="{{ route('login') }}" class="px-3 py-1.5 rounded-lg border border-outline-variant text-on-surface font-label-md text-label-md hover:bg-surface-container-high transition-colors">Connexion</a>
<a href="{{ route('register') }}" class="px-3 py-1.5 rounded-lg bg-primary-container text-on-primary-container font-label-md text-label-md font-semibold hover:opacity-95 transition">Inscription</a>
</div>
@endguest

{{-- Formulaire Nouveau signalement (statique) --}}
<section class="rounded-xl bg-surface-container-low p-space-md shadow-md">
<div class="flex items-center gap-space-sm mb-space-sm">
<span class="material-symbols-outlined text-primary text-[22px]">add_alert</span>
<h2 class="font-title-md text-title-md text-on-surface">Nouveau signalement</h2>
</div>
<form action="#" method="post" class="grid grid-cols-1 md:grid-cols-2 gap-space-sm">
@csrf
<div class="flex flex-col gap-1">
<label for="categorie" class="font-label-md text-label-md text-on-surface">Catégorie</label>
<select id="categorie" name="categorie" class="rounded-lg bg-surface-container-high text-on-surface px-space-sm py-2 focus:outline-none"><option>Voisin isolé / vulnérable</option><option>Point d'eau / fontaine en panne</option><option>Salle climatisée saturée</option><option>Coupure / surchauffe logement</option><option>Autre</option></select>
</div>
<div class="flex flex-col gap-1">
<label for="urgence" class="font-label-md text-label-md text-on-surface">Urgence</label>
<select id="urgence" name="urgence" class="rounded-lg bg-surface-container-high text-on-surface px-space-sm py-2 focus:outline-none"><option>Normale</option><option>Prioritaire</option><option>Vitale — appeler le 15</option></select>
</div>
<div class="flex flex-col gap-1 md:col-span-2">
<label for="description" class="font-label-md text-label-md text-on-surface">Description (min. 10 caractères — démo)</label>
<textarea id="description" name="description" rows="3" minlength="10" placeholder="Ex. : voisine âgée au 3e étage, volets fermés depuis 2 jours…" class="rounded-lg bg-surface-container-high text-on-surface placeholder:text-outline px-space-sm py-2 focus:outline-none"></textarea>
</div>
<div class="md:col-span-2 flex items-center gap-space-sm flex-wrap">
<button type="submit" class="px-space-md py-2.5 rounded-lg bg-primary-container text-on-primary-container font-label-md text-label-md font-semibold hover:opacity-95 shadow inline-flex items-center gap-2"><span class="material-symbols-outlined text-[18px]">send</span>Envoyer le signalement</button>
<a href="tel:15" class="inline-flex items-center gap-1 font-label-md text-label-md text-error hover:underline"><span class="material-symbols-outlined text-[16px]">emergency</span>Situation vitale ? Appelez le 15</a>
</div>
</form>
</section>

{{-- Tableau de suivi statique --}}
<section class="rounded-xl bg-surface-container-low p-space-md shadow-sm overflow-x-auto">
<p class="font-label-sm text-label-sm uppercase tracking-wider text-on-surface-variant mb-space-sm">Suivi de mes signalements — 3 (démo)</p>
<table class="w-full text-left min-w-[640px]">
<thead><tr class="font-label-sm text-label-sm uppercase text-on-surface-variant border-b border-surface-variant"><th class="py-2 pr-4">Objet</th><th class="py-2 pr-4">Catégorie</th><th class="py-2 pr-4">Urgence</th><th class="py-2 pr-4">Statut</th><th class="py-2">Date</th></tr></thead>
<tbody class="font-body-sm text-body-sm text-on-surface">
<tr class="border-b border-surface-variant"><td class="py-2 pr-4">Voisine isolée, rue des Lilas</td><td class="py-2 pr-4">Voisin vulnérable</td><td class="py-2 pr-4">Prioritaire</td><td class="py-2 pr-4"><span class="px-2 py-0.5 rounded-full bg-secondary-container/30 text-secondary font-label-sm text-label-sm">Nouveau</span></td><td class="py-2">24/09/2026</td></tr>
<tr class="border-b border-surface-variant"><td class="py-2 pr-4">Fontaine place du Marché en panne</td><td class="py-2 pr-4">Point d'eau</td><td class="py-2 pr-4">Normale</td><td class="py-2 pr-4"><span class="px-2 py-0.5 rounded-full bg-tertiary-container/20 text-tertiary font-label-sm text-label-sm">En traitement</span></td><td class="py-2">22/09/2026</td></tr>
<tr><td class="py-2 pr-4">Salle climatisée bondée samedi</td><td class="py-2 pr-4">Salle saturée</td><td class="py-2 pr-4">Normale</td><td class="py-2 pr-4"><span class="px-2 py-0.5 rounded-full bg-primary/10 text-primary font-label-sm text-label-sm">Résolu</span></td><td class="py-2">19/09/2026</td></tr>
</tbody>
</table>
<div class="mt-space-sm flex items-center gap-2 font-body-sm text-body-sm text-on-surface-variant"><span class="material-symbols-outlined text-[16px] text-primary">info</span><span>Urgence vitale ? Appelez directement le <a href="tel:15" class="text-primary font-semibold hover:underline">15</a> ou la plateforme <a href="tel:0800066666" class="text-primary font-semibold hover:underline">0800 06 66 66</a>.</span></div>
</section>
</x-app-layout>
