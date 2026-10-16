{{--
    Formulaire partagé d'un point de fraîcheur (module 3).
    Inclus par back/points/{create,edit} et front/points/{create,edit}.
    Variables : $point (PointFraicheur|null), $centre [lat, lng], $retour (url).
    Validation serveur : App\Http\Requests\StorePointFraicheurRequest.
--}}
@php
    $estAdmin = auth()->user()?->isAdmin() && request()->routeIs('back.*');
    $typeCourant = old('type', $point?->type?->value ?? \App\Enums\TypePointFraicheur::Parc->value);
    $champ = 'w-full rounded-xl bg-surface-container border border-outline-variant/40 px-3 py-2.5 text-on-surface placeholder:text-outline focus:border-primary-container focus:outline-none';
    $erreur = fn (string $nom) => $errors->has($nom) ? ' border-error' : '';
@endphp
@csrf

<div x-data="{ type: @js($typeCourant), h24: @js((bool) old('ouvert_24h', $point?->ouvert_24h ?? false)), statut: @js(old('statut', $point?->statut?->value ?? 'valide')) }" class="flex flex-col gap-space-sm">

{{-- Identité --}}
<fieldset class="rounded-xl border border-outline-variant/30 p-4">
<legend class="px-2 font-title-md text-title-md text-on-surface font-semibold flex items-center gap-2"><span class="material-symbols-outlined text-primary text-[20px]">badge</span>Identité du point</legend>
<div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
<div class="flex flex-col gap-1 sm:col-span-2">
<label for="nom" class="font-label-md text-label-md text-on-surface font-medium">Nom <span class="text-error">*</span></label>
<input type="text" id="nom" name="nom" value="{{ old('nom', $point?->nom) }}" maxlength="150" required placeholder="Ex. : Parc du Belvédère — entrée nord" class="{{ $champ }}{{ $erreur('nom') }}" />
@error('nom') <p class="font-body-sm text-body-sm text-error flex items-center gap-1"><span class="material-symbols-outlined text-[16px]">error</span>{{ $message }}</p> @enderror
</div>
<div class="flex flex-col gap-1">
<label for="type" class="font-label-md text-label-md text-on-surface font-medium">Type <span class="text-error">*</span></label>
<select id="type" name="type" x-model="type" required class="{{ $champ }}{{ $erreur('type') }}">
@foreach (\App\Enums\TypePointFraicheur::cases() as $type)
<option value="{{ $type->value }}" @selected($typeCourant === $type->value)>{{ $type->label() }}</option>
@endforeach
</select>
@error('type') <p class="font-body-sm text-body-sm text-error flex items-center gap-1"><span class="material-symbols-outlined text-[16px]">error</span>{{ $message }}</p> @enderror
</div>
<div class="flex flex-col gap-1" x-show="type !== 'fontaine'" x-cloak>
<label for="capacite" class="font-label-md text-label-md text-on-surface font-medium">Capacité d'accueil <span class="text-error">*</span></label>
<input type="number" id="capacite" name="capacite" min="1" max="5000" step="1" value="{{ old('capacite', $point?->capacite) }}" :disabled="type === 'fontaine'" placeholder="Nombre de personnes" class="{{ $champ }}{{ $erreur('capacite') }}" />
@error('capacite') <p class="font-body-sm text-body-sm text-error flex items-center gap-1"><span class="material-symbols-outlined text-[16px]">error</span>{{ $message }}</p> @enderror
</div>
<p class="sm:col-span-1 self-end font-body-sm text-body-sm text-on-surface-variant" x-show="type === 'fontaine'" x-cloak>Une fontaine n'a pas de capacité d'accueil.</p>
<div class="flex flex-col gap-1 sm:col-span-2">
<label for="description" class="font-label-md text-label-md text-on-surface font-medium">Description <span class="font-body-sm text-body-sm text-on-surface-variant font-normal">(optionnelle)</span></label>
<textarea id="description" name="description" rows="3" maxlength="1000" placeholder="Accès, services sur place, conseils…" class="{{ $champ }}{{ $erreur('description') }}">{{ old('description', $point?->description) }}</textarea>
@error('description') <p class="font-body-sm text-body-sm text-error flex items-center gap-1"><span class="material-symbols-outlined text-[16px]">error</span>{{ $message }}</p> @enderror
</div>
</div>
</fieldset>

{{-- Localisation GPS --}}
<fieldset class="rounded-xl border border-outline-variant/30 p-4">
<legend class="px-2 font-title-md text-title-md text-on-surface font-semibold flex items-center gap-2"><span class="material-symbols-outlined text-primary text-[20px]">location_on</span>Localisation</legend>
<div class="flex flex-col gap-1">
<label for="adresse" class="font-label-md text-label-md text-on-surface font-medium">Adresse <span class="text-error">*</span></label>
<div class="relative">
<input type="text" id="adresse" name="adresse" value="{{ old('adresse', $point?->adresse) }}" maxlength="255" required placeholder="Ex. : Avenue de la République, Tunis" class="{{ $champ }}{{ $erreur('adresse') }} pr-10" />
<span id="adresse-spinner" class="hidden absolute right-3 top-1/2 -translate-y-1/2 flex items-center pointer-events-none"><span class="material-symbols-outlined text-[18px] text-primary animate-spin">progress_activity</span></span>
</div>
@error('adresse') <p class="font-body-sm text-body-sm text-error flex items-center gap-1"><span class="material-symbols-outlined text-[16px]">error</span>{{ $message }}</p> @enderror
<p class="font-body-sm text-body-sm text-on-surface-variant flex items-center gap-1"><span class="material-symbols-outlined text-[14px]">location_on</span>Remplie automatiquement d'après le point choisi sur la carte — modifiable à tout moment.</p>
</div>
<div class="mt-3 flex flex-wrap items-center justify-between gap-2">
<p class="font-body-sm text-body-sm text-on-surface-variant">Cliquez sur la carte ou déplacez le repère : les coordonnées GPS se remplissent automatiquement.</p>
<button type="button" id="btn-localiser-point" class="px-3 py-2 rounded-lg bg-surface-container text-on-surface font-label-md text-label-md hover:bg-surface-bright inline-flex items-center gap-2"><span class="material-symbols-outlined text-[18px]">my_location</span>Me localiser</button>
</div>
<link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" />
<style>[x-cloak] { display: none !important; }</style>
<div id="carte-point-form" class="mt-3 w-full h-64 rounded-xl overflow-hidden z-0 border border-outline-variant/30"></div>
<div class="mt-3 grid grid-cols-1 sm:grid-cols-2 gap-4">
<div class="flex flex-col gap-1">
<label for="latitude" class="font-label-md text-label-md text-on-surface font-medium">Latitude <span class="text-error">*</span></label>
<input type="text" inputmode="decimal" id="latitude" name="latitude" value="{{ old('latitude', $point?->latitude) }}" required placeholder="36.8065" class="{{ $champ }}{{ $erreur('latitude') }}" />
@error('latitude') <p class="font-body-sm text-body-sm text-error flex items-center gap-1"><span class="material-symbols-outlined text-[16px]">error</span>{{ $message }}</p> @enderror
</div>
<div class="flex flex-col gap-1">
<label for="longitude" class="font-label-md text-label-md text-on-surface font-medium">Longitude <span class="text-error">*</span></label>
<input type="text" inputmode="decimal" id="longitude" name="longitude" value="{{ old('longitude', $point?->longitude) }}" required placeholder="10.1815" class="{{ $champ }}{{ $erreur('longitude') }}" />
@error('longitude') <p class="font-body-sm text-body-sm text-error flex items-center gap-1"><span class="material-symbols-outlined text-[16px]">error</span>{{ $message }}</p> @enderror
</div>
</div>
<p class="mt-2 font-body-sm text-body-sm text-on-surface-variant">Coordonnées en Tunisie, 7 décimales max. Un point du même type ne peut pas être déclaré à moins de {{ \App\Http\Requests\StorePointFraicheurRequest::RAYON_DOUBLON_M }} m d'un autre.</p>
</fieldset>

{{-- Horaires --}}
<fieldset class="rounded-xl border border-outline-variant/30 p-4">
<legend class="px-2 font-title-md text-title-md text-on-surface font-semibold flex items-center gap-2"><span class="material-symbols-outlined text-primary text-[20px]">schedule</span>Horaires d'ouverture</legend>
<label class="flex items-center gap-3 cursor-pointer">
<input type="checkbox" name="ouvert_24h" value="1" x-model="h24" @checked(old('ouvert_24h', $point?->ouvert_24h)) class="w-5 h-5 accent-primary-container rounded" />
<span class="font-label-md text-label-md text-on-surface">Ouvert 24h/24 (parc libre d'accès, fontaine publique…)</span>
</label>
<div class="mt-3 grid grid-cols-1 sm:grid-cols-2 gap-4" x-show="!h24" x-cloak>
<div class="flex flex-col gap-1">
<label for="heure_ouverture" class="font-label-md text-label-md text-on-surface font-medium">Ouverture <span class="text-error">*</span></label>
<input type="time" id="heure_ouverture" name="heure_ouverture" value="{{ old('heure_ouverture', $point?->heure_ouverture ? substr($point->heure_ouverture, 0, 5) : '') }}" :disabled="h24" class="{{ $champ }}{{ $erreur('heure_ouverture') }}" />
@error('heure_ouverture') <p class="font-body-sm text-body-sm text-error flex items-center gap-1"><span class="material-symbols-outlined text-[16px]">error</span>{{ $message }}</p> @enderror
</div>
<div class="flex flex-col gap-1">
<label for="heure_fermeture" class="font-label-md text-label-md text-on-surface font-medium">Fermeture <span class="text-error">*</span></label>
<input type="time" id="heure_fermeture" name="heure_fermeture" value="{{ old('heure_fermeture', $point?->heure_fermeture ? substr($point->heure_fermeture, 0, 5) : '') }}" :disabled="h24" class="{{ $champ }}{{ $erreur('heure_fermeture') }}" />
@error('heure_fermeture') <p class="font-body-sm text-body-sm text-error flex items-center gap-1"><span class="material-symbols-outlined text-[16px]">error</span>{{ $message }}</p> @enderror
</div>
<p class="sm:col-span-2 font-body-sm text-body-sm text-on-surface-variant">Une fermeture après minuit est acceptée (ex. 18:00 → 02:00 pour une nocturne canicule).</p>
</div>
</fieldset>

{{-- Équipements (préférences du moteur de recommandation) --}}
<fieldset class="rounded-xl border border-outline-variant/30 p-4">
<legend class="px-2 font-title-md text-title-md text-on-surface font-semibold flex items-center gap-2"><span class="material-symbols-outlined text-primary text-[20px]">checklist</span>Équipements</legend>
<div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
@foreach (['accessible_pmr' => ['Accessible PMR', 'accessible'], 'climatise' => ['Climatisé', 'ac_unit'], 'ombrage' => ['Ombragé', 'park'], 'eau_potable' => ['Eau potable', 'water_drop']] as $cle => [$libelle, $icone])
<label class="flex items-center gap-3 p-3 rounded-xl bg-surface-container cursor-pointer hover:bg-surface-container-high transition-colors">
<input type="checkbox" name="{{ $cle }}" value="1" @checked(old($cle, $point?->{$cle})) class="w-5 h-5 accent-primary-container rounded" />
<span class="material-symbols-outlined text-primary text-[20px]">{{ $icone }}</span>
<span class="font-label-md text-label-md text-on-surface">{{ $libelle }}</span>
</label>
@endforeach
</div>
</fieldset>

{{-- Photo --}}
<fieldset class="rounded-xl border border-outline-variant/30 p-4" x-data="{ apercu: null }">
<legend class="px-2 font-title-md text-title-md text-on-surface font-semibold flex items-center gap-2"><span class="material-symbols-outlined text-primary text-[20px]">photo_camera</span>Photo</legend>
<div class="flex flex-col sm:flex-row gap-4 items-start">
<div class="w-full sm:w-48 h-32 rounded-xl overflow-hidden bg-surface-container flex items-center justify-center shrink-0">
<template x-if="apercu"><img :src="apercu" alt="Aperçu de la nouvelle photo" class="w-full h-full object-cover" /></template>
<template x-if="!apercu">
@if ($point?->photo_url)
<img src="{{ $point->photo_url }}" alt="Photo actuelle de {{ $point->nom }}" class="w-full h-full object-cover" />
@else
<span class="material-symbols-outlined text-outline text-[40px]">image</span>
@endif
</template>
</div>
<div class="flex flex-col gap-2 flex-1">
<label for="photo" class="font-label-md text-label-md text-on-surface font-medium">{{ $point?->photo ? 'Remplacer la photo' : 'Ajouter une photo' }} <span class="font-body-sm text-body-sm text-on-surface-variant font-normal">(JPG, PNG, WEBP · 2 Mo max · 300×200 px min)</span></label>
<input type="file" id="photo" name="photo" accept="image/jpeg,image/png,image/webp" @change="apercu = $event.target.files[0] ? URL.createObjectURL($event.target.files[0]) : null" class="block w-full font-body-sm text-body-sm text-on-surface-variant file:mr-3 file:px-4 file:py-2 file:rounded-lg file:border-0 file:bg-primary-container file:text-on-primary-container file:font-semibold" />
@error('photo') <p class="font-body-sm text-body-sm text-error flex items-center gap-1"><span class="material-symbols-outlined text-[16px]">error</span>{{ $message }}</p> @enderror
@if ($point?->photo)
<label class="inline-flex items-center gap-2 font-body-sm text-body-sm text-on-surface-variant cursor-pointer"><input type="checkbox" name="supprimer_photo" value="1" class="w-4 h-4 accent-primary-container" />Retirer la photo actuelle</label>
@endif
</div>
</div>
</fieldset>

{{-- Modération (admin, back office uniquement) --}}
@if ($estAdmin)
<fieldset class="rounded-xl border border-outline-variant/30 p-4">
<legend class="px-2 font-title-md text-title-md text-on-surface font-semibold flex items-center gap-2"><span class="material-symbols-outlined text-primary text-[20px]">verified</span>Modération</legend>
<div class="grid grid-cols-1 sm:grid-cols-3 gap-3">
@foreach (\App\Enums\StatutPointFraicheur::cases() as $statut)
<label class="flex items-center gap-2 p-3 rounded-xl bg-surface-container cursor-pointer hover:bg-surface-container-high">
<input type="radio" name="statut" value="{{ $statut->value }}" x-model="statut" class="w-4 h-4 accent-primary-container" />
<span class="material-symbols-outlined text-[18px]">{{ $statut->icone() }}</span><span class="font-label-md text-label-md text-on-surface">{{ $statut->label() }}</span>
</label>
@endforeach
</div>
@error('statut') <p class="mt-1 font-body-sm text-body-sm text-error flex items-center gap-1"><span class="material-symbols-outlined text-[16px]">error</span>{{ $message }}</p> @enderror
<div class="mt-3 flex flex-col gap-1" x-show="statut === 'refuse'" x-cloak>
<label for="motif_refus" class="font-label-md text-label-md text-on-surface font-medium">Motif du refus <span class="text-error">*</span></label>
<textarea id="motif_refus" name="motif_refus" rows="2" maxlength="500" :disabled="statut !== 'refuse'" placeholder="Visible par l'habitant qui a proposé le point" class="{{ $champ }}{{ $erreur('motif_refus') }}">{{ old('motif_refus', $point?->motif_refus) }}</textarea>
@error('motif_refus') <p class="font-body-sm text-body-sm text-error flex items-center gap-1"><span class="material-symbols-outlined text-[16px]">error</span>{{ $message }}</p> @enderror
</div>
</fieldset>
@endif

<div class="flex flex-col sm:flex-row sm:justify-end gap-3">
<a href="{{ $retour }}" class="px-4 py-2.5 rounded-xl font-label-md text-label-md text-on-surface-variant hover:bg-surface-container-high inline-flex items-center justify-center gap-2"><span class="material-symbols-outlined text-[18px]">arrow_back</span>Annuler</a>
<button type="submit" class="px-6 py-2.5 rounded-xl bg-primary-container text-on-primary-container font-label-md text-label-md font-semibold hover:opacity-95 shadow inline-flex items-center justify-center gap-2"><span class="material-symbols-outlined text-[18px]">save</span>Enregistrer</button>
</div>
</div>

<script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
<script>
(function () {
    const latInput = document.getElementById('latitude');
    const lngInput = document.getElementById('longitude');
    const adresseInput = document.getElementById('adresse');
    const adresseSpinner = document.getElementById('adresse-spinner');
    const adresseUrl = @json(route('geocodage.inverse'));
    const centre = @json($centre ?? [36.8065, 10.1815]);
    const depart = (parseFloat(latInput.value) && parseFloat(lngInput.value))
        ? [parseFloat(latInput.value), parseFloat(lngInput.value)]
        : centre;

    const carte = L.map('carte-point-form').setView(depart, 14);
    L.tileLayer('https://tile.openstreetmap.org/{z}/{x}/{y}.png', { maxZoom: 19, attribution: '&copy; OpenStreetMap' }).addTo(carte);

    let marqueur = null;
    let geoSeq = 0;

    // Adresse du point choisi : remplit le champ pour la proposition comme pour
    // la modification. Un compteur ignore les réponses arrivées en retard.
    const chercherAdresse = async (lat, lng) => {
        if (! adresseInput) return;

        const seq = ++geoSeq;
        if (adresseSpinner) adresseSpinner.classList.remove('hidden');

        try {
            const url = new URL(adresseUrl, window.location.origin);
            url.searchParams.set('latitude', lat);
            url.searchParams.set('longitude', lng);

            const reponse = await fetch(url, { headers: { Accept: 'application/json' } });
            if (! reponse.ok) return;

            const donnees = await reponse.json();
            if (seq !== geoSeq || ! donnees || ! donnees.adresse) return;

            adresseInput.value = donnees.adresse;
        } catch (e) {
            // Silencieux : l'adresse reste saisissable à la main.
        } finally {
            if (seq === geoSeq && adresseSpinner) adresseSpinner.classList.add('hidden');
        }
    };

    const poser = (lat, lng, recentrer, geocoder = false) => {
        latInput.value = lat.toFixed(7);
        lngInput.value = lng.toFixed(7);
        if (marqueur) {
            marqueur.setLatLng([lat, lng]);
        } else {
            marqueur = L.marker([lat, lng], { draggable: true }).addTo(carte);
            marqueur.on('dragend', () => { const p = marqueur.getLatLng(); poser(p.lat, p.lng, false, true); });
        }
        if (recentrer) carte.setView([lat, lng], 16);
        if (geocoder) chercherAdresse(lat, lng);
    };

    if (parseFloat(latInput.value) && parseFloat(lngInput.value)) {
        poser(parseFloat(latInput.value), parseFloat(lngInput.value), false);
    }
    carte.on('click', (e) => poser(e.latlng.lat, e.latlng.lng, false, true));

    // Saisie manuelle des coordonnées → le repère suit.
    [latInput, lngInput].forEach((el) => el.addEventListener('change', () => {
        const lat = parseFloat(latInput.value.replace(',', '.'));
        const lng = parseFloat(lngInput.value.replace(',', '.'));
        if (! isNaN(lat) && ! isNaN(lng)) poser(lat, lng, true, true);
    }));

    const btn = document.getElementById('btn-localiser-point');
    if (btn && navigator.geolocation) {
        btn.addEventListener('click', () => {
            btn.disabled = true;
            navigator.geolocation.getCurrentPosition(
                (p) => { poser(p.coords.latitude, p.coords.longitude, true, true); btn.disabled = false; },
                () => { btn.disabled = false; },
            );
        });
    }
})();
</script>
