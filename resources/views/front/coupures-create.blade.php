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
<div><p class="font-title-md text-title-md text-on-surface font-semibold">Localisez</p><p class="font-body-sm text-body-sm text-on-surface-variant">Choisissez le lieu concerné (domicile, travail…).</p></div>
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

{{-- Bloc Où ? : le lieu personnel du foyer (le quartier est déduit). --}}
<fieldset class="rounded-xl border border-outline-variant/30 p-space-md">
<legend class="px-2 font-title-md text-title-md text-on-surface font-semibold flex items-center gap-2"><span class="material-symbols-outlined text-primary">location_on</span>Étape 1 — Où se passe la coupure ?</legend>

@if ($lieux->isEmpty())
<div class="mt-2 rounded-xl bg-surface-container-high p-space-md flex flex-col sm:flex-row sm:items-center gap-3">
<span class="material-symbols-outlined text-primary text-[28px]">add_location_alt</span>
<p class="flex-1 font-body-sm text-body-sm text-on-surface-variant">Vous n'avez encore aucun lieu enregistré. Ajoutez d'abord votre domicile (ou un autre lieu) pour signaler une coupure.</p>
<a href="{{ route('lieux.index') }}" class="shrink-0 px-4 py-2.5 rounded-xl bg-primary-container text-on-primary-container font-label-md text-label-md font-semibold inline-flex items-center gap-2"><span class="material-symbols-outlined text-[18px]">add</span>Ajouter un lieu</a>
</div>
@else
<p class="font-body-sm text-body-sm text-on-surface-variant mt-2">Choisissez le lieu où vous constatez la coupure. La zone interne est déduite automatiquement de ce lieu — aucune liste de quartiers à parcourir.</p>

<div class="mt-2 flex flex-col gap-1">
<label for="lieu_id" class="font-label-md text-label-md text-on-surface font-medium">Lieu concerné <span class="text-error">*</span></label>
<select id="lieu_id" name="lieu_id" required class="rounded-xl bg-surface-container-high text-on-surface px-3 py-2.5 focus:outline-none focus:ring-2 focus:ring-primary @error('lieu_id') ring-2 ring-error @enderror">
@foreach ($lieux as $lieu)
<option value="{{ $lieu->id }}" @selected((string) old('lieu_id', $lieuIdParDefaut) === (string) $lieu->id)>{{ $lieu->nom }} — {{ $lieu->type->label() }}@if ($lieu->est_principal) (principal)@endif</option>
@endforeach
</select>
@error('lieu_id')
<p class="mt-1 rounded-lg bg-error-container/20 border border-error/30 text-error font-body-sm text-body-sm px-3 py-2 flex items-center gap-2"><span class="material-symbols-outlined text-[18px]">error</span>{{ $message }}</p>
@enderror
</div>

<div id="carte-lieu" class="mt-2 w-full h-64 rounded-xl border border-outline-variant/30 z-0"></div>

<div class="mt-2 flex flex-col gap-1">
<label for="lieu" class="font-label-md text-label-md text-on-surface font-medium">Rue / précision <span class="font-body-sm text-body-sm text-on-surface-variant font-normal">(optionnel — 2 rues différentes = 2 coupures acceptées en même temps)</span></label>
<input type="text" id="lieu" name="lieu" value="{{ old('lieu') }}" maxlength="255" placeholder="Ex. : rue des Lilas" class="rounded-xl bg-surface-container-high text-on-surface placeholder:text-outline px-3 py-2.5 focus:outline-none focus:ring-2 focus:ring-primary" />
@error('lieu')
<p class="text-error font-body-sm text-body-sm flex items-center gap-1"><span class="material-symbols-outlined text-[16px]">error</span>{{ $message }}</p>
@enderror
</div>
@endif
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
<p class="mt-2 rounded-xl bg-surface-container-high p-3 font-body-sm text-body-sm text-on-surface-variant flex gap-2"><span class="material-symbols-outlined text-primary text-[20px] shrink-0">verified</span><span>Si une coupure <strong>en cours ou prévue</strong> existe déjà <strong>au même lieu</strong> (même rue) sur ce créneau, le formulaire vous le signale. Deux rues différentes = deux coupures acceptées.</span></p>
</fieldset>

{{-- Actions --}}
<div class="flex flex-col sm:flex-row items-stretch sm:items-center gap-3 pt-2">
@if ($lieux->isNotEmpty())
<button type="submit" class="flex-1 px-space-md py-3 rounded-xl bg-red-700 text-white font-label-lg text-label-lg font-bold hover:bg-red-800 shadow inline-flex items-center justify-center gap-2"><span class="material-symbols-outlined text-[20px]">send</span>Publier mon signalement</button>
@else
<button type="button" disabled class="flex-1 px-space-md py-3 rounded-xl bg-red-700/50 text-white font-label-lg text-label-lg font-bold shadow inline-flex items-center justify-center gap-2 cursor-not-allowed"><span class="material-symbols-outlined text-[20px]">block</span>Ajoutez d'abord un lieu</button>
@endif
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
<p class="font-title-md text-title-md text-red-900 font-semibold"><span id="apercu-type">Panne</span> — <span id="apercu-zone">lieu à choisir</span></p>
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

@if ($lieux->isNotEmpty())
<script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
<script>
// Carte de contexte : un repère par lieu du foyer. Le lieu choisi dans la
// liste est mis en évidence ; le quartier interne est déduit côté serveur.
const LIEUX = @json($lieuxJson ?? []);
const CENTRE = @json($centre ?? [36.8065, 10.1815]);

const lieuxGeo = LIEUX.filter((l) => l.lat !== null && l.lng !== null);
const carteLieu = L.map('carte-lieu').setView(CENTRE, 12);
L.tileLayer('https://tile.openstreetmap.org/{z}/{x}/{y}.png', {
    maxZoom: 19,
    attribution: '&copy; OpenStreetMap',
}).addTo(carteLieu);

const marqueurs = {};
lieuxGeo.forEach((l) => {
    const couleur = l.principal ? '#1b77ba' : '#7a8a99';
    const m = L.circleMarker([l.lat, l.lng], {
        radius: 9, color: couleur, fillColor: couleur, fillOpacity: 0.6, weight: 2,
    }).addTo(carteLieu);
    const contenu = document.createElement('div');
    const titre = document.createElement('strong');
    titre.textContent = l.nom;
    contenu.appendChild(titre);
    const meta = document.createElement('div');
    meta.textContent = l.typeLabel;
    contenu.appendChild(meta);
    m.bindPopup(contenu);
    marqueurs[l.id] = m;
});

if (lieuxGeo.length > 1) {
    carteLieu.fitBounds(lieuxGeo.map((l) => [l.lat, l.lng]), { maxZoom: 14, padding: [24, 24] });
}

const selectLieu = document.getElementById('lieu_id');
const desc = document.getElementById('description');
const compteur = document.getElementById('compteur-desc');

function nomLieuChoisi() {
    const opt = selectLieu ? selectLieu.options[selectLieu.selectedIndex] : null;
    const nom = opt ? opt.textContent.split(' — ')[0] : '';
    const precision = document.getElementById('lieu') ? document.getElementById('lieu').value.trim() : '';
    return (nom || 'lieu à choisir') + (precision ? ' — ' + (precision.length > 30 ? precision.slice(0, 30) + '…' : precision) : '');
}

function highlight(id) {
    Object.entries(marqueurs).forEach(([mid, m]) => {
        const actif = String(mid) === String(id);
        m.setStyle({ color: actif ? '#dc2626' : '#1b77ba', fillColor: actif ? '#dc2626' : '#1b77ba', fillOpacity: actif ? 0.85 : 0.5, radius: actif ? 12 : 9 });
    });
    const l = lieuxGeo.find((x) => String(x.id) === String(id));
    if (l) {
        carteLieu.setView([l.lat, l.lng], 14);
        if (marqueurs[l.id]) { marqueurs[l.id].openPopup(); }
    }
    majApercu();
}

function majApercu() {
    const typeCoche = document.querySelector('input[name="type"]:checked');
    const labels = { panne: 'Panne', surcharge: 'Surcharge', delestage: 'Délestage', maintenance: 'Travaux' };
    document.getElementById('apercu-zone').textContent = nomLieuChoisi();
    document.getElementById('apercu-type').textContent = typeCoche ? (labels[typeCoche.value] || typeCoche.value) : 'Panne';
    document.getElementById('apercu-debut').textContent = document.getElementById('debut').value.replace('T', ' à ') || '—';
    const txt = desc.value.trim();
    document.getElementById('apercu-desc').textContent = txt ? (txt.length > 80 ? txt.slice(0, 80) + '…' : txt) : 'sans description';
    compteur.textContent = desc.value.length;
}

if (selectLieu) {
    selectLieu.addEventListener('change', (e) => highlight(e.target.value));
    highlight(selectLieu.value);
}
document.getElementById('lieu').addEventListener('input', majApercu);
document.getElementById('debut').addEventListener('change', majApercu);
document.getElementById('debut').addEventListener('input', majApercu);
document.querySelectorAll('input[name="type"]').forEach((r) => r.addEventListener('change', majApercu));
desc.addEventListener('input', majApercu);
</script>
@endif
</x-public-layout>
