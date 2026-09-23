<x-public-layout title="Point de fraîcheur">
<a href="{{ route('home') }}" class="inline-flex items-center gap-1 font-body-sm text-body-sm text-primary hover:underline"><span class="material-symbols-outlined text-[16px]">arrow_back</span>Retour à l'accueil</a>

{{-- Fiche statique du point de fraîcheur --}}
<section class="rounded-xl bg-surface-container-low shadow-lg overflow-hidden">
<img src="https://images.unsplash.com/photo-1519331379826-f10be5486c6f?auto=format&fit=crop&w=1600&q=80" alt="Parc ombragé, point de fraîcheur" class="w-full h-60 md:h-80 object-cover" />
<div class="p-space-md flex flex-col gap-space-sm">
<div class="flex items-start justify-between gap-space-sm flex-wrap">
<div>
<div class="flex items-center gap-2 mb-1 flex-wrap">
<span class="px-2 py-0.5 rounded-full bg-secondary-container/40 text-secondary font-label-sm text-label-sm uppercase tracking-wider flex items-center gap-1"><span class="material-symbols-outlined text-[13px]">ac_unit</span>Salle climatisée</span>
<span class="px-2 py-0.5 rounded-full bg-primary-container/15 text-primary font-label-sm text-label-sm uppercase tracking-wider">Parc / point de fraîcheur</span>
</div>
<h1 class="font-headline-sm text-headline-sm text-on-surface">Médiathèque Mandela — Espace fraîcheur</h1>
<p class="font-body-sm text-body-sm text-on-surface-variant">12 rue des Tilleuls, Oasis Nord · Ouvert aujourd'hui de 09h00 à 22h00 (nocturne canicule)</p>
</div>
<div class="flex flex-col items-end"><div class="px-2.5 py-1 rounded-lg bg-surface-container-highest text-primary font-headline-sm text-headline-sm shadow-sm">23°C</div><span class="font-label-sm text-label-sm text-on-surface-variant mt-0.5">T° intérieure</span></div>
</div>
<div class="grid grid-cols-2 sm:grid-cols-4 gap-space-sm">
<div class="p-space-sm rounded-lg bg-surface-container p-space-sm"><span class="font-label-sm text-label-sm uppercase text-on-surface-variant block">Capacité</span><span class="font-title-md text-title-md text-on-surface">120 places</span></div>
<div class="p-space-sm rounded-lg bg-surface-container p-space-sm"><span class="font-label-sm text-label-sm uppercase text-on-surface-variant block">Horaires</span><span class="font-title-md text-title-md text-on-surface">09h – 22h</span></div>
<div class="p-space-sm rounded-lg bg-surface-container p-space-sm"><span class="font-label-sm text-label-sm uppercase text-on-surface-variant block">Distance</span><span class="font-title-md text-title-md text-primary">350 m · 6 min</span></div>
<div class="p-space-sm rounded-lg bg-surface-container p-space-sm"><span class="font-label-sm text-label-sm uppercase text-on-surface-variant block">Accès</span><span class="font-title-md text-title-md text-on-surface">PMR · gratuit</span></div>
</div>
<div class="flex flex-wrap gap-1.5">
<span class="px-2 py-1 rounded bg-surface-container-high text-on-surface font-label-sm text-label-sm flex items-center gap-1"><span class="material-symbols-outlined text-[14px] text-primary">water_bottle</span>Fontaine filtrée fraîche</span>
<span class="px-2 py-1 rounded bg-surface-container-high text-on-surface font-label-sm text-label-sm flex items-center gap-1"><span class="material-symbols-outlined text-[14px] text-secondary">wifi</span>Wi-Fi d'urgence ouvert</span>
<span class="px-2 py-1 rounded bg-surface-container-high text-on-surface font-label-sm text-label-sm flex items-center gap-1"><span class="material-symbols-outlined text-[14px] text-primary">power</span>Prises recharge tél. & méd.</span>
</div>
<div class="flex flex-wrap gap-space-sm pt-space-xs">
<a href="#avis" class="px-space-md py-2.5 rounded-lg bg-primary-container text-on-primary-container font-label-md text-label-md font-semibold hover:opacity-95 shadow inline-flex items-center gap-1.5"><span class="material-symbols-outlined text-[18px]">directions_walk</span>Itinéraire ombragé</a>
<a href="tel:0800066666" class="px-space-md py-2.5 rounded-lg bg-surface-container-highest text-on-surface font-label-md text-label-md hover:bg-surface-variant inline-flex items-center gap-1.5"><span class="material-symbols-outlined text-[18px] text-primary">call</span>0800 06 66 66</a>
</div>
</div>
</section>

{{-- 2 avis statiques --}}
<section id="avis" class="flex flex-col gap-space-sm scroll-mt-28">
<p class="font-label-sm text-label-sm uppercase tracking-wider text-on-surface-variant px-1">Avis des visiteurs — 2 (démo)</p>
<article class="rounded-xl bg-surface-container-low p-space-md shadow-sm flex flex-col gap-2">
<div class="flex items-center gap-2"><div class="w-9 h-9 rounded-full bg-primary-container/20 text-primary flex items-center justify-center font-title-md text-title-md">S</div><div><p class="font-title-md text-title-md text-on-surface">Salma B.</p><p class="font-body-sm text-body-sm text-on-surface-variant flex items-center gap-1"><span class="material-symbols-outlined text-[14px] text-tertiary-container">star</span>5/5 · 22/09/2026</p></div></div>
<p class="font-body-md text-body-md text-on-surface">« Salle très fraîche, accueil bienveillant et fontaine d'eau à l'entrée. Idéal avec les enfants l'après-midi. »</p>
</article>
<article class="rounded-xl bg-surface-container-low p-space-md shadow-sm flex flex-col gap-2">
<div class="flex items-center gap-2"><div class="w-9 h-9 rounded-full bg-secondary-container/30 text-secondary flex items-center justify-center font-title-md text-title-md">Y</div><div><p class="font-title-md text-title-md text-on-surface">Yassine K.</p><p class="font-body-sm text-body-sm text-on-surface-variant flex items-center gap-1"><span class="material-symbols-outlined text-[14px] text-tertiary-container">star</span>4/5 · 20/09/2026</p></div></div>
<p class="font-body-md text-body-md text-on-surface">« Parc ombragé juste à côté, parfait en fin de journée. Un peu d'attente à l'entrée vers 16h. »</p>
</article>
</section>

{{-- Formulaire Déposer un avis (statique) --}}
<section class="rounded-xl bg-surface-container-low p-space-md shadow-md">
<div class="flex items-center gap-space-sm mb-space-sm">
<div class="p-2 rounded-lg bg-primary/10 text-primary"><span class="material-symbols-outlined text-[20px]">rate_review</span></div>
<div><h2 class="font-headline-sm text-headline-sm text-on-surface">Déposer un avis</h2><p class="font-body-sm text-body-sm text-on-surface-variant">Formulaire de démonstration — aucune donnée envoyée.</p></div>
</div>
<form action="#" method="post" class="grid grid-cols-1 md:grid-cols-2 gap-space-sm">
@csrf
<div class="flex flex-col gap-1">
<label for="note" class="font-label-md text-label-md text-on-surface">Note (1 à 5)</label>
<select id="note" name="note" class="rounded-lg bg-surface-container-high text-on-surface px-space-sm py-2 focus:outline-none"><option>5 — Excellent</option><option>4 — Bien</option><option>3 — Moyen</option><option>2 — Passable</option><option>1 — À améliorer</option></select>
</div>
<div class="flex flex-col gap-1">
<label for="pseudo" class="font-label-md text-label-md text-on-surface">Pseudo (démo)</label>
<input id="pseudo" name="pseudo" type="text" placeholder="Ex. : Voisin du quartier" class="rounded-lg bg-surface-container-high text-on-surface placeholder:text-outline px-space-sm py-2 focus:outline-none" />
</div>
<div class="flex flex-col gap-1 md:col-span-2">
<label for="commentaire" class="font-label-md text-label-md text-on-surface">Commentaire</label>
<textarea id="commentaire" name="commentaire" rows="3" placeholder="Partagez votre expérience…" class="rounded-lg bg-surface-container-high text-on-surface placeholder:text-outline px-space-sm py-2 focus:outline-none"></textarea>
</div>
<div class="md:col-span-2"><button type="submit" class="px-space-md py-2.5 rounded-lg bg-primary-container text-on-primary-container font-label-md text-label-md font-semibold hover:opacity-95 shadow inline-flex items-center gap-2"><span class="material-symbols-outlined text-[18px]">send</span>Publier mon avis</button></div>
</form>
</section>
</x-public-layout>
