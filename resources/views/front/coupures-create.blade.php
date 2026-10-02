<x-public-layout title="Signaler une coupure">
<link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" />

{{-- Fil d'Ariane --}}
<nav aria-label="Fil d'Ariane" class="flex items-center gap-2 font-body-sm text-body-sm text-on-surface-variant">
<a href="{{ route('home') }}" class="inline-flex items-center gap-1 text-primary hover:underline"><span class="material-symbols-outlined text-[16px]">home</span>Accueil</a>
<span aria-hidden="true">/</span>
<a href="{{ route('coupures.index') }}" class="text-primary hover:underline">Coupures</a>
<span aria-hidden="true">/</span>
<span class="text-on-surface font-medium">Signaler</span>
</nav>

{{-- En-tête --}}
<header class="rounded-2xl bg-surface-container-low p-space-md md:p-space-lg shadow-sm flex items-start gap-space-sm">
<div class="p-3 rounded-xl bg-red-100 text-red-800 shrink-0"><span class="material-symbols-outlined text-[28px]">report</span></div>
<div class="flex-1">
<p class="font-label-sm text-label-sm uppercase tracking-widest text-red-800 font-semibold">Module 2 · Espace habitant</p>
<h1 class="font-headline-md text-headline-md md:text-headline-lg text-on-surface font-bold">Signaler une coupure en cours</h1>
<p class="font-body-md text-body-md text-on-surface-variant mt-1 max-w-2xl">Décrivez ce que vous constatez <strong>maintenant</strong> : votre signalement crée une coupure <span class="px-2 py-0.5 rounded-full bg-red-100 text-red-800 font-label-sm text-label-sm uppercase font-semibold">en cours</span>, visible aussitôt sur la carte après contrôle anti-doublon.</p>
</div>
</header>

{{-- 3 étapes --}}
<ol class="grid grid-cols-1 md:grid-cols-3 gap-space-sm">
<li class="rounded-xl bg-surface-container-low border border-outline-variant/20 p-space-md flex gap-3">
<span class="shrink-0 w-8 h-8 rounded-full bg-primary-container text-on-primary-container font-bold flex items-center justify-center">1</span>
<div><p class="font-title-md text-title-md text-on-surface font-semibold">Localisez</p><p class="font-body-sm text-body-sm text-on-surface-variant">Choisissez la zone, ou laissez le GPS la trouver pour vous.</p></div>
</li>
<li class="rounded-xl bg-surface-container-low border border-outline-variant/20 p-space-md flex gap-3">
<span class="shrink-0 w-8 h-8 rounded-full bg-primary-container text-on-primary-container font-bold flex items-center justify-center">2</span>
<div><p class="font-title-md text-title-md text-on-surface font-semibold">Décrivez</p><p class="font-body-sm text-body-sm text-on-surface-variant">Type de coupure, heure constatée, détails utiles.</p></div>
</li>
<li class="rounded-xl bg-surface-container-low border border-outline-variant/20 p-space-md flex gap-3">
<span class="shrink-0 w-8 h-8 rounded-full bg-primary-container text-on-primary-container font-bold flex items-center justify-center">3</span>
<div><p class="font-title-md text-title-md text-on-surface font-semibold">Publiez</p><p class="font-body-sm text-body-sm text-on-surface-variant">Votre signalement rejoint la carte et alerte le quartier.</p></div>
</li>
</ol>

<div class="grid grid-cols-1 lg:grid-cols-3 gap-space-sm items-start">

{{-- ============ FORMULAIRE ============ --}}
<section class="lg:col-span-2 rounded-2xl bg-surface-container-low shadow-md border border-outline-variant/20 p-space-md md:p-space-lg">
<form method="POST" action="{{ route('coupures.store') }}" class="flex flex-col gap-space-md">
@csrf

{{-- Bloc Où ? : zone existante (carte) OU déclarée à la volée (comme l'inscription). --}}
<fieldset class="rounded-xl border border-outline-variant/30 p-space-md">
<legend class="px-2 font-title-md text-title-md text-on-surface font-semibold flex items-center gap-2"><span class="material-symbols-outlined text-primary">location_on</span>Étape 1 — Où se passe la coupure ?</legend>
<div class="mt-2 grid grid-cols-1 sm:grid-cols-2 gap-2">
<label class="cursor-pointer rounded-xl border-2 p-3 flex gap-2 items-center transition-all has-checked:border-primary has-checked:bg-primary-container/10 border-outline-variant/30">
<input type="radio" name="zone_mode" value="existante" @checked(old('zone_mode', 'existante') === 'existante') class="w-5 h-5 accent-primary" />
<span><span class="font-title-md text-title-md text-on-surface font-semibold block">Ma zone existe</span><span class="font-body-sm text-body-sm text-on-surface-variant">Je la choisis sur la carte.</span></span>
</label>
<label class="cursor-pointer rounded-xl border-2 p-3 flex gap-2 items-center transition-all has-checked:border-primary has-checked:bg-primary-container/10 border-outline-variant/30">
<input type="radio" name="zone_mode" value="nouvelle" @checked(old('zone_mode') === 'nouvelle') class="w-5 h-5 accent-primary" />
<span><span class="font-title-md text-title-md text-on-surface font-semibold block">Ma zone manque</span><span class="font-body-sm text-body-sm text-on-surface-variant">Je la déclare (nom + ville).</span></span>
</label>
</div>
@error('zone_mode')
<p class="mt-2 rounded-lg bg-error-container/20 border border-error/30 text-error font-body-sm text-body-sm px-3 py-2 flex items-center gap-2"><span class="material-symbols-outlined text-[18px]">error</span>{{ $message }}</p>
@enderror

<div id="bloc-zone-existante">
<p class="font-body-sm text-body-sm text-on-surface-variant mt-2">Cliquez le <strong>point de votre quartier</strong> sur la carte, ou <strong>recherchez-le</strong> : la carte y bascule directement.</p>
<div class="relative mt-2">
<label for="recherche-zone" class="sr-only">Rechercher une zone</label>
<input type="text" id="recherche-zone" placeholder="Rechercher une zone : tapez Centre, Berges…" autocomplete="off" class="w-full rounded-xl bg-surface-container-high text-on-surface placeholder:text-outline px-3 py-2.5 focus:outline-none focus:ring-2 focus:ring-primary" />
<ul id="resultats-zone" class="hidden absolute z-10 mt-1 w-full max-h-48 overflow-auto rounded-xl border border-outline-variant/30 bg-surface-container-low shadow-lg"></ul>
</div>
<div id="carte-choix-zone" class="mt-2 w-full h-64 rounded-xl border border-outline-variant/30 z-0"></div>
<div class="mt-2 flex flex-col sm:flex-row sm:items-center gap-2">
{{-- Vrai champ envoyé au serveur en mode existante : rempli par la carte / le GPS. --}}
<input type="hidden" id="quartier_id" name="quartier_id" value="{{ old('quartier_id') }}" />
<p class="flex-1 rounded-xl bg-surface-container-high px-3 py-2.5 font-body-md text-body-md text-on-surface">Zone sélectionnée : <strong id="zone-nom">—</strong></p>
<button type="button" id="btn-localiser" class="shrink-0 px-4 py-2.5 rounded-xl bg-primary-container/15 text-primary font-label-md text-label-md font-semibold hover:bg-primary-container/25 inline-flex items-center justify-center gap-2"><span class="material-symbols-outlined text-[18px]">my_location</span>Me localiser</button>
</div>
<p id="geo-message" class="font-body-sm text-body-sm text-on-surface-variant min-h-5" role="status"></p>
{{-- Secours sans carte : liste classique (même nom de champ). --}}
<details class="mt-1 rounded-xl bg-surface-container-high px-3 py-2">
<summary class="cursor-pointer font-body-sm text-body-sm text-on-surface-variant">La carte ne s'affiche pas ? Choisir dans une liste</summary>
<select id="quartier_id_fallback" class="mt-2 w-full rounded-xl bg-surface-container-low text-on-surface px-3 py-2.5 focus:outline-none">
<option value="">— Sélectionnez votre quartier —</option>
@foreach ($quartiers as $q)
<option value="{{ $q->id }}" @selected(old('quartier_id') == $q->id)>{{ $q->nom }} ({{ $q->ville }})</option>
@endforeach
</select>
</details>
@error('quartier_id')
<p class="rounded-lg bg-error-container/20 border border-error/30 text-error font-body-sm text-body-sm px-3 py-2 flex items-center gap-2"><span class="material-symbols-outlined text-[18px]">error</span>{{ $message }}</p>
@enderror
<div class="mt-2 flex flex-col gap-1">
<label for="lieu" class="font-label-md text-label-md text-on-surface font-medium">Rue / lieu précis <span class="font-body-sm text-body-sm text-on-surface-variant font-normal">(optionnel — 2 rues différentes = 2 coupures acceptées en même temps)</span></label>
<input type="text" id="lieu" name="lieu" value="{{ old('lieu') }}" maxlength="255" placeholder="Ex. : rue des Lilas" class="rounded-xl bg-surface-container-high text-on-surface placeholder:text-outline px-3 py-2.5 focus:outline-none focus:ring-2 focus:ring-primary" />
@error('lieu')
<p class="text-error font-body-sm text-body-sm flex items-center gap-1"><span class="material-symbols-outlined text-[16px]">error</span>{{ $message }}</p>
@enderror
</div>
</div>

<div id="bloc-zone-nouvelle" class="hidden">
<p class="font-body-sm text-body-sm text-on-surface-variant mt-2">Votre quartier n'est pas sur la carte ? <strong>Déclarez-le</strong> : il sera créé (position du dernier clic carte / GPS) et votre coupure lui sera rattachée. Si un voisin l'a déjà déclaré, il est réutilisé.</p>
<div class="mt-2 grid grid-cols-1 sm:grid-cols-2 gap-3">
<div class="flex flex-col gap-1">
<label for="nouveau_quartier_nom" class="font-label-md text-label-md text-on-surface font-medium">Nom du quartier <span class="text-error">*</span></label>
<input type="text" id="nouveau_quartier_nom" name="nouveau_quartier_nom" value="{{ old('nouveau_quartier_nom') }}" placeholder="Ex. : Cité El Manar" class="rounded-xl bg-surface-container-high text-on-surface placeholder:text-outline px-3 py-2.5 focus:outline-none focus:ring-2 focus:ring-primary" />
@error('nouveau_quartier_nom')
<p class="text-error font-body-sm text-body-sm flex items-center gap-1"><span class="material-symbols-outlined text-[16px]">error</span>{{ $message }}</p>
@enderror
</div>
<div class="flex flex-col gap-1">
<label for="nouveau_quartier_ville" class="font-label-md text-label-md text-on-surface font-medium">Ville <span class="text-error">*</span></label>
<input type="text" id="nouveau_quartier_ville" name="nouveau_quartier_ville" value="{{ old('nouveau_quartier_ville') }}" placeholder="Ex. : Tunis" class="rounded-xl bg-surface-container-high text-on-surface placeholder:text-outline px-3 py-2.5 focus:outline-none focus:ring-2 focus:ring-primary" />
@error('nouveau_quartier_ville')
<p class="text-error font-body-sm text-body-sm flex items-center gap-1"><span class="material-symbols-outlined text-[16px]">error</span>{{ $message }}</p>
@enderror
</div>
</div>
<div class="mt-3 flex flex-col gap-1">
<label for="nouveau_quartier_code_postal" class="font-label-md text-label-md text-on-surface font-medium">Code postal <span class="font-body-sm text-body-sm text-on-surface-variant font-normal">(si vous le connaissez)</span></label>
<input type="text" id="nouveau_quartier_code_postal" name="nouveau_quartier_code_postal" value="{{ old('nouveau_quartier_code_postal') }}" placeholder="Ex. : 1000" class="rounded-xl bg-surface-container-high text-on-surface placeholder:text-outline px-3 py-2.5 focus:outline-none focus:ring-2 focus:ring-primary" />
@error('nouveau_quartier_code_postal')
<p class="text-error font-body-sm text-body-sm flex items-center gap-1"><span class="material-symbols-outlined text-[16px]">error</span>{{ $message }}</p>
@enderror
</div>
{{-- Position héritée du dernier clic carte / GPS (remplie en JS, modifiable nulle part : simple). --}}
<input type="hidden" id="nouveau_latitude" name="nouveau_latitude" value="{{ old('nouveau_latitude') }}" />
<input type="hidden" id="nouveau_longitude" name="nouveau_longitude" value="{{ old('nouveau_longitude') }}" />
<p class="mt-2 font-body-sm text-body-sm text-on-surface-variant">Position reprise : <span id="nouvelle-position">aucun clic carte / GPS pour l'instant (optionnel)</span></p>
</div>
</fieldset>

{{-- Bloc Quoi ? --}}
<fieldset class="rounded-xl border border-outline-variant/30 p-space-md">
<legend class="px-2 font-title-md text-title-md text-on-surface font-semibold flex items-center gap-2"><span class="material-symbols-outlined text-primary">bolt</span>Étape 2 — Que se passe-t-il ?</legend>
<div class="grid grid-cols-1 sm:grid-cols-2 gap-4 mt-2">
<label class="cursor-pointer rounded-xl border-2 p-4 flex gap-3 transition-all has-checked:border-red-600 has-checked:bg-red-50 border-outline-variant/30 hover:border-outline">
<input type="radio" name="type" value="panne" @checked(old('type', 'panne') === 'panne') class="mt-1 w-5 h-5 accent-red-600" />
<span><span class="font-title-md text-title-md text-on-surface font-semibold flex items-center gap-1"><span class="material-symbols-outlined text-[20px]">power_off</span>Panne</span><span class="font-body-sm text-body-sm text-on-surface-variant">Tout est coupé d'un coup, sans prévenir.</span></span>
</label>
<label class="cursor-pointer rounded-xl border-2 p-4 flex gap-3 transition-all has-checked:border-red-600 has-checked:bg-red-50 border-outline-variant/30 hover:border-outline">
<input type="radio" name="type" value="surcharge" @checked(old('type') === 'surcharge') class="mt-1 w-5 h-5 accent-red-600" />
<span><span class="font-title-md text-title-md text-on-surface font-semibold flex items-center gap-1"><span class="material-symbols-outlined text-[20px]">trending_up</span>Surcharge</span><span class="font-body-sm text-body-sm text-on-surface-variant">Coupures brèves à répétition (clim, pic de chaleur).</span></span>
</label>
<label class="cursor-pointer rounded-xl border-2 p-4 flex gap-3 transition-all has-checked:border-red-600 has-checked:bg-red-50 border-outline-variant/30 hover:border-outline">
<input type="radio" name="type" value="delestage" @checked(old('type') === 'delestage') class="mt-1 w-5 h-5 accent-red-600" />
<span><span class="font-title-md text-title-md text-on-surface font-semibold flex items-center gap-1"><span class="material-symbols-outlined text-[20px]">schedule</span>Délestage</span><span class="font-body-sm text-body-sm text-on-surface-variant">Coupure tournante annoncée par le réseau.</span></span>
</label>
<label class="cursor-pointer rounded-xl border-2 p-4 flex gap-3 transition-all has-checked:border-red-600 has-checked:bg-red-50 border-outline-variant/30 hover:border-outline">
<input type="radio" name="type" value="maintenance" @checked(old('type') === 'maintenance') class="mt-1 w-5 h-5 accent-red-600" />
<span><span class="font-title-md text-title-md text-on-surface font-semibold flex items-center gap-1"><span class="material-symbols-outlined text-[20px]">construction</span>Travaux</span><span class="font-body-sm text-body-sm text-on-surface-variant">Techniciens visibles dans la rue.</span></span>
</label>
</div>
@error('type')
<p class="mt-2 rounded-lg bg-error-container/20 border border-error/30 text-error font-body-sm text-body-sm px-3 py-2 flex items-center gap-2"><span class="material-symbols-outlined text-[18px]">error</span>{{ $message }}</p>
@enderror
<div class="flex flex-col gap-1 mt-4">
<label for="description" class="font-label-md text-label-md text-on-surface font-medium">Décrivez ce que vous voyez <span class="font-body-sm text-body-sm text-on-surface-variant font-normal">(rue, depuis quand, ce qui marche encore…)</span></label>
<textarea id="description" name="description" rows="3" maxlength="2000" placeholder="Ex. : coupure rue des Lilas depuis 18h05, ascenseur bloqué, voisins prévenus…" class="rounded-xl bg-surface-container-high text-on-surface placeholder:text-outline px-3 py-2.5 focus:outline-none focus:ring-2 focus:ring-primary @error('description') ring-2 ring-error @enderror">{{ old('description') }}</textarea>
<div class="flex justify-between items-center">
@error('description')
<p class="text-error font-body-sm text-body-sm flex items-center gap-1"><span class="material-symbols-outlined text-[16px]">error</span>{{ $message }}</p>
@else
<p class="font-body-sm text-body-sm text-on-surface-variant">Optionnel, mais très utile aux voisins.</p>
@enderror
<p class="font-body-sm text-body-sm text-on-surface-variant"><span id="compteur-desc">0</span>/2000</p>
</div>
</div>
</fieldset>

{{-- Bloc Quand ? --}}
<fieldset class="rounded-xl border border-outline-variant/30 p-space-md">
<legend class="px-2 font-title-md text-title-md text-on-surface font-semibold flex items-center gap-2"><span class="material-symbols-outlined text-primary">schedule</span>Étape 3 — Depuis quand ?</legend>
<div class="grid grid-cols-1 sm:grid-cols-2 gap-4 mt-2">
<div class="flex flex-col gap-1">
<label for="debut" class="font-label-md text-label-md text-on-surface font-medium">Début constaté <span class="text-error">*</span></label>
<input type="datetime-local" id="debut" name="debut" value="{{ old('debut', now()->format('Y-m-d\TH:i')) }}" required class="rounded-xl bg-surface-container-high text-on-surface px-3 py-2.5 focus:outline-none focus:ring-2 focus:ring-primary @error('debut') ring-2 ring-error @enderror" />
@error('debut')
<p class="rounded-lg bg-error-container/20 border border-error/30 text-error font-body-sm text-body-sm px-3 py-2 flex items-center gap-2"><span class="material-symbols-outlined text-[18px]">error</span>{{ $message }}</p>
@enderror
</div>
<div class="flex flex-col gap-1">
<label for="fin" class="font-label-md text-label-md text-on-surface font-medium">Fin estimée <span class="font-body-sm text-body-sm text-on-surface-variant font-normal">(si vous la connaissez)</span></label>
<input type="datetime-local" id="fin" name="fin" value="{{ old('fin') }}" class="rounded-xl bg-surface-container-high text-on-surface px-3 py-2.5 focus:outline-none focus:ring-2 focus:ring-primary @error('fin') ring-2 ring-error @enderror" />
@error('fin')
<p class="rounded-lg bg-error-container/20 border border-error/30 text-error font-body-sm text-body-sm px-3 py-2 flex items-center gap-2"><span class="material-symbols-outlined text-[18px]">error</span>{{ $message }}</p>
@enderror
</div>
</div>
<p class="mt-2 rounded-xl bg-surface-container-high p-3 font-body-sm text-body-sm text-on-surface-variant flex gap-2"><span class="material-symbols-outlined text-primary text-[20px] shrink-0">verified</span><span>Si une coupure <strong>en cours ou prévue</strong> existe déjà <strong>au même endroit</strong> (même zone, même rue) sur ce créneau, le formulaire vous le signale. Deux rues différentes = deux coupures acceptées.</span></p>
</fieldset>

{{-- Actions --}}
<div class="flex flex-col sm:flex-row items-stretch sm:items-center gap-3 pt-2">
<button type="submit" class="flex-1 px-space-md py-3 rounded-xl bg-red-700 text-white font-label-lg text-label-lg font-bold hover:bg-red-800 shadow inline-flex items-center justify-center gap-2"><span class="material-symbols-outlined text-[20px]">send</span>Publier mon signalement</button>
<a href="{{ route('coupures.index') }}" class="px-space-md py-3 rounded-xl font-label-md text-label-md text-on-surface-variant hover:bg-surface-container-high text-center">Annuler</a>
</div>
<p class="font-body-sm text-body-sm text-on-surface-variant text-center">En publiant, votre signalement devient une coupure <strong>« en cours »</strong> rattachée à votre compte.</p>
</form>
</section>

{{-- ============ COLONNE AIDE ============ --}}
<aside class="flex flex-col gap-space-sm lg:sticky lg:top-24">
<div class="rounded-2xl bg-surface-container-low shadow-sm border border-outline-variant/20 p-space-md">
<h2 class="font-title-md text-title-md text-on-surface font-semibold flex items-center gap-2"><span class="material-symbols-outlined text-primary">preview</span>Récapitulatif en direct</h2>
<div class="mt-3 rounded-xl bg-red-50 border border-red-200 p-3">
<p class="font-title-md text-title-md text-red-900 font-semibold"><span id="apercu-type">Panne</span> — <span id="apercu-zone">zone à choisir</span></p>
<p class="font-body-sm text-body-sm text-red-800"><span id="apercu-debut">—</span> · <span id="apercu-desc">sans description</span></p>
</div>
<p class="mt-2 font-body-sm text-body-sm text-on-surface-variant">C'est exactement ce que verront vos voisins sur la carte.</p>
</div>
<div class="rounded-2xl bg-surface-container-low shadow-sm border border-outline-variant/20 p-space-md">
<h2 class="font-title-md text-title-md text-on-surface font-semibold flex items-center gap-2"><span class="material-symbols-outlined text-primary">lightbulb</span>Bien signaler</h2>
<ul class="mt-2 flex flex-col gap-2 font-body-sm text-body-sm text-on-surface-variant">
<li class="flex gap-2"><span class="material-symbols-outlined text-green-700 text-[18px] shrink-0">check</span>Précisez la rue et l'heure exacte constatée.</li>
<li class="flex gap-2"><span class="material-symbols-outlined text-green-700 text-[18px] shrink-0">check</span>Un seul signalement par coupure : vérifiez la carte avant.</li>
<li class="flex gap-2"><span class="material-symbols-outlined text-green-700 text-[18px] shrink-0">check</span>Protégez vos équipements sensibles (frigo, clim) en attendant.</li>
</ul>
</div>
<div class="rounded-2xl bg-red-700 text-white p-space-md shadow-md">
<h2 class="font-title-md text-title-md font-semibold flex items-center gap-2"><span class="material-symbols-outlined">emergency</span>Urgence ?</h2>
<p class="font-body-sm text-body-sm text-red-100 mt-1">Personne bloquée, danger électrique, matériel médical : n'attendez pas le site.</p>
<div class="mt-3 flex flex-wrap gap-2">
<a href="tel:190" class="px-4 py-2 rounded-xl bg-white text-red-800 font-label-md text-label-md font-bold inline-flex items-center gap-1"><span class="material-symbols-outlined text-[18px]">call</span>190</a>
<a href="tel:0800066666" class="px-4 py-2 rounded-xl bg-red-800 border border-red-500 text-white font-label-md text-label-md inline-flex items-center gap-1"><span class="material-symbols-outlined text-[18px]">bolt</span>0800 06 66 66</a>
</div>
</div>
<a href="{{ route('coupures.index') }}" class="rounded-2xl bg-surface-container-low border border-outline-variant/20 p-space-md flex items-center gap-3 hover:border-primary">
<span class="material-symbols-outlined text-primary text-[28px]">map</span>
<span><span class="font-title-md text-title-md text-on-surface font-semibold block">Voir la carte des coupures</span><span class="font-body-sm text-body-sm text-on-surface-variant">Vérifiez qu'elle n'est pas déjà signalée.</span></span>
</a>
</aside>
</div>

<script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
<script>
// Choix de la zone SUR CARTE : le champ envoyé est le hidden #quartier_id.
// Un marqueur par quartier géolocalisé ; clic marqueur = choisir,
// clic ailleurs = quartier le plus proche (Haversine, sans API externe).
const QUARTIERS_GEO = @json($quartiersGeo ?? []);
const QUARTIERS_SEARCH = @json($quartiersSearch ?? []);

function distanceKm(lat1, lng1, lat2, lng2) {
    const r = 6371;
    const dLat = (lat2 - lat1) * Math.PI / 180;
    const dLng = (lng2 - lng1) * Math.PI / 180;
    const a = Math.sin(dLat / 2) * Math.sin(dLat / 2)
        + Math.cos(lat1 * Math.PI / 180) * Math.cos(lat2 * Math.PI / 180)
        * Math.sin(dLng / 2) * Math.sin(dLng / 2);
    return 2 * r * Math.asin(Math.sqrt(a));
}

function plusProche(lat, lng) {
    let meilleur = null;
    let distMin = Infinity;
    QUARTIERS_GEO.forEach((q) => {
        const d = distanceKm(lat, lng, q.lat, q.lng);
        if (d < distMin) {
            distMin = d;
            meilleur = { quartier: q, distance: d };
        }
    });
    return meilleur;
}

const hiddenZone = document.getElementById('quartier_id');
const zoneNom = document.getElementById('zone-nom');
const msg = document.getElementById('geo-message');
const fallback = document.getElementById('quartier_id_fallback');

const carteChoix = L.map('carte-choix-zone').setView(
    QUARTIERS_GEO.length ? [QUARTIERS_GEO[0].lat, QUARTIERS_GEO[0].lng] : [36.8065, 10.1815],
    12
);
L.tileLayer('https://tile.openstreetmap.org/{z}/{x}/{y}.png', {
    maxZoom: 19,
    attribution: '&copy; OpenStreetMap',
}).addTo(carteChoix);

const marqueursChoix = {};
QUARTIERS_GEO.forEach((q) => {
    const m = L.circleMarker([q.lat, q.lng], {
        radius: 10, color: '#1b77ba', fillColor: '#1b77ba', fillOpacity: 0.5, weight: 2,
    }).addTo(carteChoix).bindPopup('<strong>' + q.nom + '</strong><br>Cliquez pour choisir cette zone');
    m.on('click', () => choisirZone(q.id, 'Zone sélectionnée : cliquez Envoyer quand c\'est bon.'));
    marqueursChoix[q.id] = m;
});

function choisirZone(id, message) {
    hiddenZone.value = id;
    const q = QUARTIERS_SEARCH.find((x) => String(x.id) === String(id))
        || QUARTIERS_GEO.find((x) => String(x.id) === String(id));
    zoneNom.textContent = q ? q.nom : '—';
    Object.entries(marqueursChoix).forEach(([mid, m]) => {
        const actif = String(mid) === String(id);
        m.setStyle({ color: actif ? '#dc2626' : '#1b77ba', fillColor: actif ? '#dc2626' : '#1b77ba', fillOpacity: actif ? 0.8 : 0.5, radius: actif ? 13 : 10 });
        if (actif) {
            m.openPopup();
        }
    });
    if (fallback) {
        fallback.value = id;
    }
    if (message) {
        msg.textContent = message;
    }
    majApercu();
}

// Bascule existante / nouvelle : un seul mode visible et envoyé à la fois.
const blocExistant = document.getElementById('bloc-zone-existante');
const blocNouveau = document.getElementById('bloc-zone-nouvelle');
function majModeZone() {
    const mode = document.querySelector('input[name="zone_mode"]:checked')?.value || 'existante';
    blocExistant.classList.toggle('hidden', mode !== 'existante');
    blocNouveau.classList.toggle('hidden', mode !== 'nouvelle');
    // On agrandit la carte quand elle réapparaît (sinon tuiles grises).
    if (mode === 'existante') {
        setTimeout(() => carteChoix.invalidateSize(), 50);
    }
    majApercu();
}
document.querySelectorAll('input[name="zone_mode"]').forEach((r) => r.addEventListener('change', majModeZone));

// Dernière position connue (clic carte / GPS / point déplacé) :
// réutilisée comme position du quartier déclaré à la volée.
function memoriserPosition(lat, lng) {
    document.getElementById('nouveau_latitude').value = lat.toFixed(7);
    document.getElementById('nouveau_longitude').value = lng.toFixed(7);
    document.getElementById('nouvelle-position').textContent = Number(lat).toFixed(5) + ', ' + Number(lng).toFixed(5);
}

// Clic sur la carte (hors marqueur) = zone la plus proche du point cliqué.
carteChoix.on('click', (e) => {
    memoriserPosition(e.latlng.lat, e.latlng.lng);
    const p = plusProche(e.latlng.lat, e.latlng.lng);
    if (p) {
        choisirZone(p.quartier.id, 'Point proche de ' + p.quartier.nom + ' (' + p.distance.toFixed(1) + ' km).');
    }
});

// Liste de secours synchronisée avec la carte.
if (fallback) {
    fallback.addEventListener('change', (e) => {
        if (e.target.value) {
            choisirZone(e.target.value, '');
        }
    });
}

// Recherche de zone : tapez 2 lettres, cliquez le résultat,
// la carte bascule (flyTo) directement dessus et la zone est choisie.
const champRecherche = document.getElementById('recherche-zone');
const listeResultats = document.getElementById('resultats-zone');

function normaliser(texte) {
    return (texte || '').toLowerCase().normalize('NFD').replace(/[\u0300-\u036f]/g, '');
}

champRecherche.addEventListener('input', () => {
    const saisie = normaliser(champRecherche.value.trim());
    listeResultats.innerHTML = '';
    if (saisie.length < 2) {
        listeResultats.classList.add('hidden');
        return;
    }
    const trouves = QUARTIERS_SEARCH.filter((q) =>
        normaliser(q.nom).includes(saisie) || normaliser(q.ville).includes(saisie)
    ).slice(0, 6);
    if (! trouves.length) {
        listeResultats.classList.remove('hidden');
        listeResultats.innerHTML = '<li class="px-3 py-2 font-body-sm text-body-sm text-on-surface-variant">Aucune zone trouvée.</li>';
        return;
    }
    trouves.forEach((q) => {
        const li = document.createElement('li');
        const btn = document.createElement('button');
        btn.type = 'button';
        btn.className = 'w-full text-left px-3 py-2 hover:bg-surface-container-high font-body-md text-body-md text-on-surface flex items-center gap-2';
        btn.innerHTML = '<span class="material-symbols-outlined text-primary text-[18px]">location_on</span>' + q.nom + ' (' + q.ville + ')';
        btn.addEventListener('click', () => {
            listeResultats.classList.add('hidden');
            champRecherche.value = q.nom;
            if (q.lat && q.lng) {
                carteChoix.flyTo([q.lat, q.lng], 14, { duration: 1 });
            }
            choisirZone(q.id, q.lat && q.lng ? 'Zone trouvée : ' + q.nom + ' — carte centrée dessus.' : 'Zone trouvée : ' + q.nom + ' (non géolocalisée : demandez à un admin ses coordonnées).');
        });
        li.appendChild(btn);
        listeResultats.appendChild(li);
    });
    listeResultats.classList.remove('hidden');
});

// Clic hors résultats = on referme la liste.
document.addEventListener('click', (e) => {
    if (! e.target.closest('#recherche-zone') && ! e.target.closest('#resultats-zone')) {
        listeResultats.classList.add('hidden');
    }
});

// Valeur déjà saisie (old() après erreur de validation) : on la ré-affiche.
if (hiddenZone.value) {
    choisirZone(hiddenZone.value, '');
}
if (document.getElementById('nouveau_latitude').value && document.getElementById('nouveau_longitude').value) {
    document.getElementById('nouvelle-position').textContent =
        Number(document.getElementById('nouveau_latitude').value).toFixed(5) + ', ' +
        Number(document.getElementById('nouveau_longitude').value).toFixed(5);
}

const btnLocaliser = document.getElementById('btn-localiser');
let marqueurMoi = null;
let cerclePrecision = null;

btnLocaliser.addEventListener('click', () => {
    if (! navigator.geolocation) {
        msg.textContent = 'Géolocalisation non supportée par votre navigateur : cliquez votre zone sur la carte.';
        return;
    }
    // État de chargement : évite les doubles clics.
    btnLocaliser.disabled = true;
    btnLocaliser.classList.add('opacity-60');
    msg.textContent = 'Localisation précise en cours… (autorisez le GPS, restez à découvert)';
    navigator.geolocation.getCurrentPosition((pos) => {
        btnLocaliser.disabled = false;
        btnLocaliser.classList.remove('opacity-60');
        const lat = pos.coords.latitude;
        const lng = pos.coords.longitude;
        const precision = Math.round(pos.coords.accuracy || 0); // mètres

        // Marqueur "vous êtes ici" + cercle de précision (votre vraie position).
        if (marqueurMoi) {
            carteChoix.removeLayer(marqueurMoi);
        }
        if (cerclePrecision) {
            carteChoix.removeLayer(cerclePrecision);
        }
        cerclePrecision = L.circle([lat, lng], {
            radius: precision || 50, color: '#1b77ba', fillColor: '#1b77ba', fillOpacity: 0.15, weight: 1,
        }).addTo(carteChoix);
        // Point BLEU déplaçable (vrai marqueur Leaflet) : l'utilisateur corrige
        // la position GPS à la main, la zone est recalculée à chaque déplacement.
        marqueurMoi = L.marker([lat, lng], { draggable: true }).addTo(carteChoix)
            .bindPopup('<strong>Vous êtes ici</strong><br>Précision : ±' + precision + ' m<br><em>Astuce : glissez ce point sur votre position exacte.</em>').openPopup();
        marqueurMoi.on('dragend', () => {
            const posAjustee = marqueurMoi.getLatLng();
            memoriserPosition(posAjustee.lat, posAjustee.lng);
            const pAjuste = plusProche(posAjustee.lat, posAjustee.lng);
            if (pAjuste) {
                choisirZone(pAjuste.quartier.id, 'Position ajustée à la main — zone : ' + pAjuste.quartier.nom + ' (' + pAjuste.distance.toFixed(1) + ' km).');
            }
        });
        carteChoix.setView([lat, lng], 14);

        memoriserPosition(lat, lng);
        const p = plusProche(lat, lng);
        if (p) {
            choisirZone(p.quartier.id, 'Vous êtes à ±' + precision + ' m — zone la plus proche : ' + p.quartier.nom + ' (' + p.distance.toFixed(1) + ' km). Vérifiez avant d\'envoyer.');
        } else {
            msg.textContent = 'Position trouvée (±' + precision + ' m) mais aucun quartier géolocalisé pour comparer.';
        }
    }, (err) => {
        btnLocaliser.disabled = false;
        btnLocaliser.classList.remove('opacity-60');
        if (err.code === 1) {
            msg.textContent = 'Position refusée : cliquez l\'icône cadenas dans la barre d\'adresse pour autoriser, ou choisissez la zone sur la carte.';
        } else if (err.code === 2) {
            msg.textContent = 'GPS indisponible (intérieur ?). Approchez une fenêtre ou choisissez la zone sur la carte.';
        } else if (err.code === 3) {
            msg.textContent = 'Délai dépassé : réessayez à découvert, ou choisissez la zone sur la carte.';
        } else {
            msg.textContent = 'Position introuvable : choisissez la zone sur la carte.';
        }
    }, {
        enableHighAccuracy: true, // GPS exact plutôt que position approximative (Wi-Fi/IP)
        timeout: 15000,
        maximumAge: 0, // jamais de vieille position en cache
    });
});

// Compteur + récapitulatif en direct (pédagogique : montre ce qui sera publié).
const desc = document.getElementById('description');
const compteur = document.getElementById('compteur-desc');
function majApercu() {
    const mode = document.querySelector('input[name="zone_mode"]:checked')?.value || 'existante';
    let nom = 'zone à choisir (cliquez la carte)';
    if (mode === 'nouvelle') {
        const nn = document.getElementById('nouveau_quartier_nom').value.trim();
        const nv = document.getElementById('nouveau_quartier_ville').value.trim();
        nom = nn ? 'nouveau : ' + nn + (nv ? ' (' + nv + ')' : '') : 'nouveau quartier à nommer';
    } else if (zoneNom.textContent && zoneNom.textContent !== '—') {
        nom = zoneNom.textContent;
    }
    const lieuSaisi = document.getElementById('lieu').value.trim();
    if (lieuSaisi) {
        nom += ' — ' + (lieuSaisi.length > 30 ? lieuSaisi.slice(0, 30) + '…' : lieuSaisi);
    }
    const typeCoche = document.querySelector('input[name="type"]:checked');
    const labels = { panne: 'Panne', surcharge: 'Surcharge', delestage: 'Délestage', maintenance: 'Travaux' };
    document.getElementById('apercu-zone').textContent = nom;
    document.getElementById('apercu-type').textContent = typeCoche ? (labels[typeCoche.value] || typeCoche.value) : 'Panne';
    document.getElementById('apercu-debut').textContent = document.getElementById('debut').value.replace('T', ' à ') || '—';
    const txt = desc.value.trim();
    document.getElementById('apercu-desc').textContent = txt ? (txt.length > 80 ? txt.slice(0, 80) + '…' : txt) : 'sans description';
    compteur.textContent = desc.value.length;
}
document.getElementById('debut').addEventListener('change', majApercu);
document.querySelectorAll('input[name="type"]').forEach((r) => r.addEventListener('change', majApercu));
desc.addEventListener('input', majApercu);
document.getElementById('debut').addEventListener('input', majApercu);
document.getElementById('nouveau_quartier_nom').addEventListener('input', majApercu);
document.getElementById('nouveau_quartier_ville').addEventListener('input', majApercu);
document.getElementById('lieu').addEventListener('input', majApercu);
majModeZone();
</script>
</x-public-layout>
