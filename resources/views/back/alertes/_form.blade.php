@php
    $selectedQ = array_map('intval', (array) old('quartier_ids', $alerte?->quartiers->pluck('id')->all() ?? []));
    // Quartiers géolocalisés : servent à recentrer la carte au clic sur une case.
    $quartiersGeo = $quartiers
        ->map(fn ($q) => ['id' => $q->id, 'nom' => $q->nom, 'lat' => $q->latitude, 'lng' => $q->longitude])
        ->filter(fn ($q) => $q['lat'] !== null && $q['lng'] !== null)
        ->values();
@endphp
@csrf
@if ($alerte)
    @method('PUT')
@endif

<div x-data="alerteForm()" data-prefill-url="{{ route('back.alertes.prefill') }}" class="flex flex-col gap-4">

{{-- Trace IA : identifiant de la prédiction à rattacher à l'enregistrement. --}}
<input type="hidden" name="ai_prediction_id" :value="predictionId" />

{{-- Récapitulatif des erreurs détectées avant l'envoi : garantit un retour
     visible même quand la validation native du navigateur est désactivée. --}}
<template x-if="Object.keys(erreurs).length">
<div role="alert" class="rounded-xl bg-error-container text-on-error-container px-space-md py-space-sm flex flex-col gap-1">
<p class="font-title-md text-title-md font-semibold flex items-center gap-2"><span class="material-symbols-outlined">report</span>Corrigez les champs suivants</p>
<ul class="list-disc pl-8 font-body-sm text-body-sm">
<template x-for="(message, champ) in erreurs" :key="champ">
<li x-text="message"></li>
</template>
</ul>
</div>
</template>

{{-- Bandeau IA : pré-remplit la météo + classe le niveau + rédige le message --}}
<div class="rounded-xl border border-primary-container/30 bg-primary-container/5 p-4 flex flex-col gap-3">
<div class="flex items-start gap-3">
<span class="material-symbols-outlined text-primary text-[24px] shrink-0">auto_awesome</span>
<div class="flex-1">
<p class="font-title-md text-title-md text-on-surface font-semibold">Assistant IA canicule</p>
<p class="font-body-sm text-body-sm text-on-surface-variant">Récupère la météo du quartier, classe le niveau de vigilance par règles puis rédige un message. Laissez la température vide pour utiliser la météo live (WeatherAPI).</p>
</div>
</div>
<div class="flex flex-wrap gap-2">
<button type="button" @click="prefill(false)" :disabled="chargement" class="px-3 py-2 rounded-lg bg-surface-container text-on-surface font-label-md text-label-md hover:bg-surface-bright inline-flex items-center gap-2 disabled:opacity-50"><span class="material-symbols-outlined text-[18px]">cloud_sync</span><span x-text="chargement ? 'Analyse…' : 'Météo + niveau'"></span></button>
<button type="button" @click="prefill(true)" :disabled="chargement" class="px-3 py-2 rounded-lg bg-primary-container text-on-primary-container font-label-md text-label-md font-semibold hover:opacity-95 inline-flex items-center gap-2 disabled:opacity-50"><span class="material-symbols-outlined text-[18px]">edit_note</span>Météo + niveau + message</button>
</div>
<template x-if="retour">
<div class="text-body-sm font-body-sm rounded-lg px-3 py-2" :class="retour.ok ? 'bg-surface-container text-on-surface' : 'bg-error-container text-on-error-container'">
<template x-if="retour.ok">
<p><strong x-text="retour.niveau_label"></strong> — <span x-text="retour.raison"></span></p>
</template>
<template x-if="!retour.ok"><p x-text="retour.raison"></p></template>
</div>
</template>

{{-- Explicabilité du moteur hybride : confiance, probabilités, facteurs. --}}
<template x-if="retour && retour.ok">
<div class="flex flex-col gap-3 rounded-lg bg-surface-container px-3 py-3">
<div class="flex flex-wrap items-center gap-2">
<span class="font-label-sm text-label-sm text-on-surface-variant">Confiance du modèle</span>
<span class="font-title-md text-title-md text-on-surface" x-text="Math.round((retour.confiance || 0) * 100) + ' %'"></span>
<template x-if="retour.desaccord">
<span class="px-2 py-0.5 rounded-full bg-amber-100 text-amber-800 font-label-sm text-label-sm font-semibold">Désaccord modèle / règles</span>
</template>
<template x-if="!retour.modele_disponible">
<span class="px-2 py-0.5 rounded-full bg-surface-variant text-on-surface-variant font-label-sm text-label-sm">Modèle indisponible — règles seules</span>
</template>
</div>
<template x-if="retour.probabilites && Object.keys(retour.probabilites).length">
<div class="flex flex-col gap-1">
<template x-for="(p, cls) in retour.probabilites" :key="cls">
<div class="flex items-center gap-2">
<span class="w-32 font-label-sm text-label-sm text-on-surface-variant" x-text="libelleClasse(cls)"></span>
<div class="flex-1 h-2 rounded-full bg-surface-variant overflow-hidden"><div class="h-full" :class="couleurClasse(cls)" :style="'width:' + Math.round((p || 0) * 100) + '%'"></div></div>
<span class="w-12 text-right font-label-sm text-label-sm text-on-surface" x-text="Math.round((p || 0) * 100) + '%'"></span>
</div>
</template>
</div>
</template>
<template x-if="retour.facteurs && retour.facteurs.length">
<div>
<p class="font-label-sm text-label-sm text-on-surface-variant mb-1">Principaux facteurs</p>
<div class="flex flex-wrap gap-2">
<template x-for="f in retour.facteurs" :key="f.feature">
<span class="px-2 py-1 rounded-md bg-surface-container-high text-on-surface font-label-sm text-label-sm"><strong x-text="f.label"></strong> : <span x-text="f.valeur_affichee"></span></span>
</template>
</div>
</div>
</template>
</div>
</template>
</div>

{{-- Section identification --}}
<fieldset class="rounded-xl border border-outline-variant/30 p-4">
<legend class="px-2 font-title-md text-title-md text-on-surface font-semibold flex items-center gap-2"><span class="material-symbols-outlined text-primary text-[20px]">warning</span>Identification</legend>
<div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
<div class="flex flex-col gap-1 sm:col-span-2">
<label for="titre" class="font-label-md text-label-md text-on-surface font-medium">Titre de l'alerte <span class="text-error">*</span></label>
<input type="text" id="titre" name="titre" value="{{ old('titre', $alerte?->titre ?? '') }}" required maxlength="150" placeholder="Ex. : Vigilance forte chaleur" class="w-full rounded-xl bg-surface-container border border-outline-variant/40 px-3 py-2.5 text-on-surface placeholder:text-outline focus:border-primary-container focus:outline-none @error('titre') border-error @enderror" />
@error('titre') <p class="font-body-sm text-body-sm text-error flex items-center gap-1"><span class="material-symbols-outlined text-[16px]">error</span>{{ $message }}</p> @enderror
<p x-show="erreurs.titre" class="font-body-sm text-body-sm text-error flex items-center gap-1"><span class="material-symbols-outlined text-[16px]">error</span><span x-text="erreurs.titre"></span></p>
</div>
<div class="flex flex-col gap-1">
<label for="niveau" class="font-label-md text-label-md text-on-surface font-medium">Niveau de vigilance <span class="text-error">*</span></label>
<select id="niveau" name="niveau" required class="w-full rounded-xl bg-surface-container border border-outline-variant/40 px-3 py-2.5 text-on-surface focus:border-primary-container focus:outline-none @error('niveau') border-error @enderror">
@foreach ($niveaux as $niveau)
<option value="{{ $niveau->value }}" @selected(old('niveau', $alerte?->niveau?->value ?? 'jaune') === $niveau->value)>{{ $niveau->label() }}</option>
@endforeach
</select>
@error('niveau') <p class="font-body-sm text-body-sm text-error flex items-center gap-1"><span class="material-symbols-outlined text-[16px]">error</span>{{ $message }}</p> @enderror
</div>
<div class="flex flex-col gap-1">
<label for="seuil_temperature" class="font-label-md text-label-md text-on-surface font-medium">Seuil de température (°C) <span class="text-error">*</span></label>
<input type="number" step="0.5" min="30" max="55" id="seuil_temperature" name="seuil_temperature" value="{{ old('seuil_temperature', $alerte?->seuil_temperature ?? 40) }}" required class="w-full rounded-xl bg-surface-container border border-outline-variant/40 px-3 py-2.5 text-on-surface focus:border-primary-container focus:outline-none @error('seuil_temperature') border-error @enderror" />
@error('seuil_temperature') <p class="font-body-sm text-body-sm text-error flex items-center gap-1"><span class="material-symbols-outlined text-[16px]">error</span>{{ $message }}</p> @enderror
<p x-show="erreurs.seuil_temperature" class="font-body-sm text-body-sm text-error flex items-center gap-1"><span class="material-symbols-outlined text-[16px]">error</span><span x-text="erreurs.seuil_temperature"></span></p>
</div>
</div>
</fieldset>

{{-- Section quartiers (N---N) — désormais facultative --}}
<fieldset class="rounded-xl border border-outline-variant/30 p-4">
<legend class="px-2 font-title-md text-title-md text-on-surface font-semibold flex items-center gap-2"><span class="material-symbols-outlined text-primary text-[20px]">location_on</span>Rattachement aux quartiers <span class="font-body-sm text-body-sm text-on-surface-variant font-normal">(facultatif)</span></legend>
@error('quartier_ids') <p class="mb-2 font-body-sm text-body-sm text-error flex items-center gap-1"><span class="material-symbols-outlined text-[16px]">error</span>{{ $message }}</p> @enderror
@error('quartier_ids.*') <p class="mb-2 font-body-sm text-body-sm text-error flex items-center gap-1"><span class="material-symbols-outlined text-[16px]">error</span>{{ $message }}</p> @enderror
<p id="quartier-erreur-alerte" x-show="erreurs.quartier_ids" class="mb-2 font-body-sm text-body-sm text-error flex items-center gap-1"><span class="material-symbols-outlined text-[16px]">error</span><span x-text="erreurs.quartier_ids"></span></p>
<div id="liste-quartiers-alerte" class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-2">
@forelse ($quartiers as $q)
<label class="flex items-center gap-2 rounded-lg bg-surface-container border border-outline-variant/30 px-3 py-2 cursor-pointer hover:bg-surface-container-high transition-colors">
<input type="checkbox" name="quartier_ids[]" value="{{ $q->id }}" @checked(in_array($q->id, $selectedQ, true)) class="peer sr-only" />
<span class="flex h-5 w-5 shrink-0 items-center justify-center rounded border-2 border-outline-variant/60 bg-surface-container text-transparent peer-checked:border-primary peer-checked:text-primary peer-focus-visible:ring-2 peer-focus-visible:ring-primary/40 transition-colors">
<span class="material-symbols-outlined text-[16px] leading-none">check</span>
</span>
<span class="font-body-md text-body-md text-on-surface">{{ $q->nom }} <span class="font-body-sm text-body-sm text-on-surface-variant">({{ $q->ville }})</span></span>
</label>
@empty
<p class="font-body-sm text-body-sm text-on-surface-variant">Aucun quartier disponible.</p>
@endforelse
</div>
@if (auth()->user()->isGestionnaire())
<p class="mt-2 font-body-sm text-body-sm text-on-surface-variant">Votre périmètre : votre zone uniquement — l'enregistrement force votre quartier.</p>
@else
<p class="mt-2 font-body-sm text-body-sm text-on-surface-variant">Optionnel : un ou plusieurs quartiers. La cible principale est le cercle ci-dessous.</p>

{{-- Quartier proposé automatiquement quand le point cliqué n'est proche d'aucun
     quartier connu : il rejoint la liste des cases (cochée) et sera créé en base
     à l'enregistrement de l'alerte. --}}
<div id="bloc-nouveau-quartier-alerte" class="hidden mt-3 rounded-xl border border-primary/30 bg-primary-container/10 p-3 flex flex-col gap-3">
<p class="font-body-sm text-body-sm text-on-surface-variant flex items-center gap-1"><span class="material-symbols-outlined text-primary text-[18px]">add_location_alt</span>Aucun quartier connu à proximité : ce nouveau quartier sera ajouté à la liste et créé à l'enregistrement.</p>
<div class="grid grid-cols-1 sm:grid-cols-3 gap-3">
<div class="flex flex-col gap-1">
<label for="nouveau_quartier_nom" class="font-label-md text-label-md text-on-surface font-medium">Nom du quartier</label>
<input type="text" id="nouveau_quartier_nom" name="nouveau_quartier_nom" value="{{ old('nouveau_quartier_nom') }}" maxlength="120" class="w-full rounded-xl bg-surface-container border border-outline-variant/40 px-3 py-2.5 text-on-surface focus:border-primary-container focus:outline-none" />
@error('nouveau_quartier_nom') <p class="font-body-sm text-body-sm text-error flex items-center gap-1"><span class="material-symbols-outlined text-[16px]">error</span>{{ $message }}</p> @enderror
<p x-show="erreurs.nouveau_quartier_nom" class="font-body-sm text-body-sm text-error flex items-center gap-1"><span class="material-symbols-outlined text-[16px]">error</span><span x-text="erreurs.nouveau_quartier_nom"></span></p>
</div>
<div class="flex flex-col gap-1">
<label for="nouveau_quartier_ville" class="font-label-md text-label-md text-on-surface font-medium">Ville</label>
<input type="text" id="nouveau_quartier_ville" name="nouveau_quartier_ville" value="{{ old('nouveau_quartier_ville') }}" maxlength="120" class="w-full rounded-xl bg-surface-container border border-outline-variant/40 px-3 py-2.5 text-on-surface focus:border-primary-container focus:outline-none" />
@error('nouveau_quartier_ville') <p class="font-body-sm text-body-sm text-error flex items-center gap-1"><span class="material-symbols-outlined text-[16px]">error</span>{{ $message }}</p> @enderror
<p x-show="erreurs.nouveau_quartier_ville" class="font-body-sm text-body-sm text-error flex items-center gap-1"><span class="material-symbols-outlined text-[16px]">error</span><span x-text="erreurs.nouveau_quartier_ville"></span></p>
</div>
<div class="flex flex-col gap-1">
<label for="nouveau_quartier_code_postal" class="font-label-md text-label-md text-on-surface font-medium">Code postal</label>
<input type="text" id="nouveau_quartier_code_postal" name="nouveau_quartier_code_postal" value="{{ old('nouveau_quartier_code_postal') }}" maxlength="10" class="w-full rounded-xl bg-surface-container border border-outline-variant/40 px-3 py-2.5 text-on-surface focus:border-primary-container focus:outline-none" />
@error('nouveau_quartier_code_postal') <p class="font-body-sm text-body-sm text-error flex items-center gap-1"><span class="material-symbols-outlined text-[16px]">error</span>{{ $message }}</p> @enderror
<p x-show="erreurs.nouveau_quartier_code_postal" class="font-body-sm text-body-sm text-error flex items-center gap-1"><span class="material-symbols-outlined text-[16px]">error</span><span x-text="erreurs.nouveau_quartier_code_postal"></span></p>
</div>
</div>
<p class="font-body-sm text-body-sm text-on-surface-variant">Champs pré-remplis par le géocodage — vous pouvez les corriger avant d'enregistrer.</p>
</div>
@endif
</fieldset>

{{-- Section zone géographique (carte : point + rayon) --}}
<fieldset class="rounded-xl border border-outline-variant/30 p-4">
<legend class="px-2 font-title-md text-title-md text-on-surface font-semibold flex items-center gap-2"><span class="material-symbols-outlined text-primary text-[20px]">map</span>Zone géographique (carte)</legend>
<p class="mb-2 font-body-sm text-body-sm text-on-surface-variant">Cliquez sur la carte pour poser le centre de la zone (ou cochez un quartier pour le centrer), puis ajustez le rayon. Une alerte peut cibler un ou plusieurs quartiers, un cercle, ou les deux.</p>
<link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" />
<div id="carte-alerte-form" data-quartiers-geo="{{ json_encode($quartiersGeo) }}" class="w-full h-72 rounded-xl overflow-hidden z-0 border border-outline-variant/30"></div>
<input type="hidden" id="latitude" name="latitude" value="{{ old('latitude', $alerte?->latitude) }}" />
<input type="hidden" id="longitude" name="longitude" value="{{ old('longitude', $alerte?->longitude) }}" />
@error('latitude') <p class="mt-1 font-body-sm text-body-sm text-error flex items-center gap-1"><span class="material-symbols-outlined text-[16px]">error</span>{{ $message }}</p> @enderror
@error('longitude') <p class="mt-1 font-body-sm text-body-sm text-error flex items-center gap-1"><span class="material-symbols-outlined text-[16px]">error</span>{{ $message }}</p> @enderror
<p x-show="erreurs.latitude" class="mt-1 font-body-sm text-body-sm text-error flex items-center gap-1"><span class="material-symbols-outlined text-[16px]">error</span><span x-text="erreurs.latitude"></span></p>
<p x-show="erreurs.longitude" class="mt-1 font-body-sm text-body-sm text-error flex items-center gap-1"><span class="material-symbols-outlined text-[16px]">error</span><span x-text="erreurs.longitude"></span></p>

<div class="mt-3 flex flex-col gap-1">
<label for="lieu" class="font-label-md text-label-md text-on-surface font-medium">Rue / lieu précis <span class="font-body-sm text-body-sm text-on-surface-variant font-normal">(optionnel — rempli automatiquement au clic sur la carte)</span></label>
<input type="text" id="lieu" name="lieu" value="{{ old('lieu', $alerte?->lieu ?? '') }}" maxlength="255" placeholder="Ex. : avenue Habib Bourguiba" class="w-full rounded-xl bg-surface-container border border-outline-variant/40 px-3 py-2.5 text-on-surface placeholder:text-outline focus:border-primary-container focus:outline-none @error('lieu') border-error @enderror" />
<p id="lieu-spinner-alerte" class="hidden flex font-body-sm text-body-sm text-on-surface-variant items-center gap-1"><span class="material-symbols-outlined text-[16px] animate-spin">progress_activity</span>Recherche de l'adresse…</p>
@error('lieu') <p class="font-body-sm text-body-sm text-error flex items-center gap-1"><span class="material-symbols-outlined text-[16px]">error</span>{{ $message }}</p> @enderror
<p x-show="erreurs.lieu" class="font-body-sm text-body-sm text-error flex items-center gap-1"><span class="material-symbols-outlined text-[16px]">error</span><span x-text="erreurs.lieu"></span></p>
<p id="info-localisation-alerte" class="hidden font-body-sm text-body-sm text-on-surface-variant"></p>
</div>

<div class="mt-3 flex flex-wrap items-end gap-3">
<div class="flex flex-col gap-1">
<label for="rayon_metres" class="font-label-md text-label-md text-on-surface font-medium">Rayon (mètres)</label>
<input type="number" id="rayon_metres" name="rayon_metres" min="100" max="50000" step="50" value="{{ old('rayon_metres', $alerte?->rayonMetres() ?? 1000) }}" class="w-40 rounded-xl bg-surface-container border border-outline-variant/40 px-3 py-2.5 text-on-surface focus:border-primary-container focus:outline-none @error('rayon_metres') border-error @enderror" />
@error('rayon_metres') <p class="font-body-sm text-body-sm text-error flex items-center gap-1"><span class="material-symbols-outlined text-[16px]">error</span>{{ $message }}</p> @enderror
</div>
<button type="button" id="btn-localiser-alerte" class="px-3 py-2.5 rounded-lg bg-surface-container text-on-surface font-label-md text-label-md hover:bg-surface-bright inline-flex items-center gap-2"><span class="material-symbols-outlined text-[18px]">my_location</span>Me localiser</button>
<button type="button" id="btn-effacer-alerte" class="px-3 py-2.5 rounded-lg bg-surface-container text-on-surface-variant font-label-md text-label-md hover:bg-surface-bright inline-flex items-center gap-2"><span class="material-symbols-outlined text-[18px]">close</span>Effacer le point</button>
</div>
</fieldset>

{{-- Section créneau --}}
<fieldset class="rounded-xl border border-outline-variant/30 p-4">
<legend class="px-2 font-title-md text-title-md text-on-surface font-semibold flex items-center gap-2"><span class="material-symbols-outlined text-primary text-[20px]">schedule</span>Créneau de vigilance</legend>
<div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
<div class="flex flex-col gap-1">
<label for="debut" class="font-label-md text-label-md text-on-surface font-medium">Début <span class="text-error">*</span></label>
<input type="datetime-local" id="debut" name="debut" value="{{ old('debut', $alerte?->debut?->format('Y-m-d\TH:i') ?? now()->format('Y-m-d\TH:i')) }}" required class="w-full rounded-xl bg-surface-container border border-outline-variant/40 px-3 py-2.5 text-on-surface focus:border-primary-container focus:outline-none @error('debut') border-error @enderror" />
@error('debut') <p class="font-body-sm text-body-sm text-error flex items-center gap-1"><span class="material-symbols-outlined text-[16px]">error</span>{{ $message }}</p> @enderror
<p x-show="erreurs.debut" class="font-body-sm text-body-sm text-error flex items-center gap-1"><span class="material-symbols-outlined text-[16px]">error</span><span x-text="erreurs.debut"></span></p>
</div>
<div class="flex flex-col gap-1">
<label for="fin" class="font-label-md text-label-md text-on-surface font-medium">Fin <span class="text-error">*</span></label>
<input type="datetime-local" id="fin" name="fin" value="{{ old('fin', $alerte?->fin?->format('Y-m-d\TH:i') ?? now()->addDay()->format('Y-m-d\TH:i')) }}" required class="w-full rounded-xl bg-surface-container border border-outline-variant/40 px-3 py-2.5 text-on-surface focus:border-primary-container focus:outline-none @error('fin') border-error @enderror" />
@error('fin') <p class="font-body-sm text-body-sm text-error flex items-center gap-1"><span class="material-symbols-outlined text-[16px]">error</span>{{ $message }}</p> @enderror
<p x-show="erreurs.fin" class="font-body-sm text-body-sm text-error flex items-center gap-1"><span class="material-symbols-outlined text-[16px]">error</span><span x-text="erreurs.fin"></span></p>
</div>
</div>
<p class="mt-2 font-body-sm text-body-sm text-on-surface-variant">La fin doit être postérieure au début. Le statut (Programmée / Active / Terminée) est calculé automatiquement.</p>
</fieldset>

{{-- Section météo mesurée --}}
<fieldset class="rounded-xl border border-outline-variant/30 p-4">
<legend class="px-2 font-title-md text-title-md text-on-surface font-semibold flex items-center gap-2"><span class="material-symbols-outlined text-primary text-[20px]">thermostat</span>Mesure météo</legend>
<div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
<div class="flex flex-col gap-1">
<label for="temperature_actuelle" class="font-label-md text-label-md text-on-surface font-medium">Température (°C)</label>
<input type="number" step="0.1" min="-10" max="60" id="temperature_actuelle" name="temperature_actuelle" value="{{ old('temperature_actuelle', $alerte?->temperature_actuelle) }}" placeholder="Météo live si vide" class="w-full rounded-xl bg-surface-container border border-outline-variant/40 px-3 py-2.5 text-on-surface placeholder:text-outline focus:border-primary-container focus:outline-none @error('temperature_actuelle') border-error @enderror" />
@error('temperature_actuelle') <p class="font-body-sm text-body-sm text-error flex items-center gap-1"><span class="material-symbols-outlined text-[16px]">error</span>{{ $message }}</p> @enderror
<p x-show="erreurs.temperature_actuelle" class="font-body-sm text-body-sm text-error flex items-center gap-1"><span class="material-symbols-outlined text-[16px]">error</span><span x-text="erreurs.temperature_actuelle"></span></p>
</div>
<div class="flex flex-col gap-1">
<label for="temperature_ressentie" class="font-label-md text-label-md text-on-surface font-medium">Ressentie (°C)</label>
<input type="number" step="0.1" min="-10" max="70" id="temperature_ressentie" name="temperature_ressentie" value="{{ old('temperature_ressentie', $alerte?->temperature_ressentie) }}" class="w-full rounded-xl bg-surface-container border border-outline-variant/40 px-3 py-2.5 text-on-surface focus:border-primary-container focus:outline-none @error('temperature_ressentie') border-error @enderror" />
@error('temperature_ressentie') <p class="font-body-sm text-body-sm text-error flex items-center gap-1"><span class="material-symbols-outlined text-[16px]">error</span>{{ $message }}</p> @enderror
<p x-show="erreurs.temperature_ressentie" class="font-body-sm text-body-sm text-error flex items-center gap-1"><span class="material-symbols-outlined text-[16px]">error</span><span x-text="erreurs.temperature_ressentie"></span></p>
</div>
<div class="flex flex-col gap-1">
<label for="humidite" class="font-label-md text-label-md text-on-surface font-medium">Humidité (%)</label>
<input type="number" step="1" min="0" max="100" id="humidite" name="humidite" value="{{ old('humidite', $alerte?->humidite) }}" class="w-full rounded-xl bg-surface-container border border-outline-variant/40 px-3 py-2.5 text-on-surface focus:border-primary-container focus:outline-none @error('humidite') border-error @enderror" />
@error('humidite') <p class="font-body-sm text-body-sm text-error flex items-center gap-1"><span class="material-symbols-outlined text-[16px]">error</span>{{ $message }}</p> @enderror
<p x-show="erreurs.humidite" class="font-body-sm text-body-sm text-error flex items-center gap-1"><span class="material-symbols-outlined text-[16px]">error</span><span x-text="erreurs.humidite"></span></p>
</div>
</div>
<input type="hidden" name="source_meteo" value="{{ old('source_meteo', $alerte?->source_meteo ?? 'manuel') }}" />
</fieldset>

{{-- Section message --}}
<fieldset class="rounded-xl border border-outline-variant/30 p-4">
<legend class="px-2 font-title-md text-title-md text-on-surface font-semibold flex items-center gap-2"><span class="material-symbols-outlined text-primary text-[20px]">message</span>Message aux habitants</legend>
<div class="flex flex-col gap-1">
<label for="message" class="sr-only">Message aux habitants</label>
<textarea id="message" name="message" rows="4" maxlength="2000" placeholder="Ex. : restez hydratés, évitez les sorties entre 11h et 17h…" class="w-full rounded-xl bg-surface-container border border-outline-variant/40 px-3 py-2.5 text-on-surface placeholder:text-outline focus:border-primary-container focus:outline-none @error('message') border-error @enderror">{{ old('message', $alerte?->message ?? '') }}</textarea>
@error('message') <p class="font-body-sm text-body-sm text-error flex items-center gap-1"><span class="material-symbols-outlined text-[16px]">error</span>{{ $message }}</p> @enderror
<p class="font-body-sm text-body-sm text-on-surface-variant">Ce message est le texte de référence ; côté habitant, l'IA peut en générer une variante personnalisée selon son profil.</p>
</div>
</fieldset>

<div class="flex flex-col sm:flex-row sm:justify-end gap-3">
<a href="{{ route('back.alertes.index') }}" class="px-4 py-2.5 rounded-xl font-label-md text-label-md text-on-surface-variant hover:bg-surface-container-high inline-flex items-center justify-center gap-2"><span class="material-symbols-outlined text-[18px]">arrow_back</span>Retour à la liste</a>
<button type="submit" class="px-6 py-2.5 rounded-xl bg-primary-container text-on-primary-container font-label-md text-label-md font-semibold hover:opacity-95 shadow">Enregistrer</button>
</div>
</div>

<script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
<script>
const QUARTIERS_GEO = JSON.parse(document.getElementById('carte-alerte-form').dataset.quartiersGeo);
const CENTRE_DEFAUT_ALERTE = [36.8065, 10.1815];

// Même comportement que le formulaire de coupure : un point posé sur la carte
// sélectionne le quartier le plus proche (rayon 3 km) et pré-remplit la rue.
const RAYON_QUARTIER_ALERTE_KM = 3;
const ADRESSE_URL_ALERTE = @json(route('geocodage.inverse'));

// Distance orthodromique (Haversine) en km — même formule que App\Support\Geo.
function distanceKmAlerte(lat1, lng1, lat2, lng2) {
    const rayon = 6371;
    const dLat = (lat2 - lat1) * Math.PI / 180;
    const dLng = (lng2 - lng1) * Math.PI / 180;
    const a = Math.sin(dLat / 2) ** 2
        + Math.cos(lat1 * Math.PI / 180) * Math.cos(lat2 * Math.PI / 180) * Math.sin(dLng / 2) ** 2;
    return 2 * rayon * Math.asin(Math.min(1, Math.sqrt(a)));
}

function alerteForm() {
    return {
        chargement: false,
        retour: null,
        predictionId: '',
        carte: null,
        // Erreurs détectées côté navigateur : affichées en rouge avant l'envoi,
        // la validation Laravel restant la référence (chevauchements, etc.).
        erreurs: {},
        // Drapeau : la température n'est envoyée à l'IA que si l'utilisateur l'a
        // saisie à la main. Une valeur auto-remplie par un pré-remplissage
        // précédent ne doit pas se transformer en override permanent (sinon la
        // météo n'est plus jamais recalculée pour un nouveau point).
        temperatureManuelle: false,
        libelleClasse(cls) {
            return { normal: 'Aucune alerte', jaune: 'Vigilance jaune', orange: 'Vigilance orange', rouge: 'Vigilance rouge' }[cls] || cls;
        },
        couleurClasse(cls) {
            return { normal: 'bg-outline', jaune: 'bg-amber-500', orange: 'bg-orange-500', rouge: 'bg-red-600' }[cls] || 'bg-outline';
        },
        init() {
            const form = this.$root;
            form.closest('form')?.addEventListener('submit', (event) => this.valider(event));

            const latInput = form.querySelector('[name="latitude"]');
            const lngInput = form.querySelector('[name="longitude"]');
            const rayonInput = form.querySelector('[name="rayon_metres"]');
            if (! latInput || ! lngInput || ! rayonInput || typeof L === 'undefined') {
                return;
            }

            const depart = (latInput.value && lngInput.value)
                ? [parseFloat(latInput.value), parseFloat(lngInput.value)]
                : (QUARTIERS_GEO.length ? [QUARTIERS_GEO[0].lat, QUARTIERS_GEO[0].lng] : CENTRE_DEFAUT_ALERTE);

            this.carte = L.map('carte-alerte-form').setView(depart, 13);
            L.tileLayer('https://tile.openstreetmap.org/{z}/{x}/{y}.png', {
                maxZoom: 19,
                attribution: '&copy; OpenStreetMap',
            }).addTo(this.carte);

            let cercle = null;
            let marqueur = null;
            const rayon = () => Math.max(100, parseInt(rayonInput.value || '1000', 10) || 1000);

            const lieuInput = form.querySelector('[name="lieu"]');
            const spinnerAdresse = document.getElementById('lieu-spinner-alerte');
            const infoLocalisation = document.getElementById('info-localisation-alerte');
            const casesQuartiers = () => Array.from(form.querySelectorAll('input[name="quartier_ids[]"]'));
            const grilleQuartiers = document.getElementById('liste-quartiers-alerte');
            const blocNouveauQuartier = document.getElementById('bloc-nouveau-quartier-alerte');
            const nomNouveauQuartier = form.querySelector('[name="nouveau_quartier_nom"]');
            const villeNouveauQuartier = form.querySelector('[name="nouveau_quartier_ville"]');
            const cpNouveauQuartier = form.querySelector('[name="nouveau_quartier_code_postal"]');
            let requeteAdresse = 0;

            const afficherInfo = (texte) => {
                if (! infoLocalisation) return;
                infoLocalisation.textContent = texte;
                infoLocalisation.classList.remove('hidden');
            };

            const masquerInfo = () => {
                if (! infoLocalisation) return;
                infoLocalisation.textContent = '';
                infoLocalisation.classList.add('hidden');
            };

            const quartierLePlusProche = (lat, lng) => {
                let meilleur = null;
                let distance = Infinity;

                QUARTIERS_GEO.forEach((q) => {
                    const d = distanceKmAlerte(lat, lng, parseFloat(q.lat), parseFloat(q.lng));
                    if (d < distance) {
                        distance = d;
                        meilleur = q;
                    }
                });

                return distance <= RAYON_QUARTIER_ALERTE_KM ? meilleur : null;
            };

            const masquerNouveauQuartier = () => {
                if (blocNouveauQuartier) {
                    blocNouveauQuartier.classList.add('hidden');
                }
                [nomNouveauQuartier, villeNouveauQuartier, cpNouveauQuartier].forEach((el) => {
                    if (el) el.value = '';
                });
                document.getElementById('case-nouveau-quartier-alerte')?.remove();
            };

            // Point loin de tout quartier connu : le géocodage propose un nom et
            // une ville, la case correspondante est ajoutée cochée à la liste.
            const proposerNouveauQuartier = (donnees) => {
                const nom = (donnees?.quartier ?? '').trim();
                const ville = (donnees?.ville ?? '').trim();

                if (! grilleQuartiers || ! blocNouveauQuartier || nom === '' || ville === '') {
                    return false;
                }

                if (nomNouveauQuartier) nomNouveauQuartier.value = nom;
                if (villeNouveauQuartier) villeNouveauQuartier.value = ville;
                if (cpNouveauQuartier) cpNouveauQuartier.value = (donnees?.code_postal ?? '').trim();

                let caseEl = document.getElementById('case-nouveau-quartier-alerte');
                const libelle = nom + ' (' + ville + ') — nouveau';

                if (! caseEl) {
                    caseEl = document.createElement('label');
                    caseEl.id = 'case-nouveau-quartier-alerte';
                    caseEl.className = 'flex items-center gap-2 rounded-lg bg-primary-container/10 border border-primary/40 px-3 py-2 cursor-pointer hover:bg-primary-container/20 transition-colors';

                    const cb = document.createElement('input');
                    cb.type = 'checkbox';
                    cb.name = 'quartier_ids[]';
                    cb.value = 'nouveau';
                    cb.checked = true;
                    cb.className = 'peer sr-only';
                    cb.addEventListener('change', () => {
                        if (! cb.checked) masquerNouveauQuartier();
                    });

                    const boite = document.createElement('span');
                    boite.className = 'flex h-5 w-5 shrink-0 items-center justify-center rounded border-2 border-outline-variant/60 bg-surface-container text-transparent peer-checked:border-primary peer-checked:text-primary peer-focus-visible:ring-2 peer-focus-visible:ring-primary/40 transition-colors';
                    const icone = document.createElement('span');
                    icone.className = 'material-symbols-outlined text-[16px] leading-none';
                    icone.textContent = 'check';
                    boite.appendChild(icone);

                    const texte = document.createElement('span');
                    texte.className = 'font-body-md text-body-md text-on-surface';
                    texte.dataset.libelle = 'nouveau-quartier';
                    texte.textContent = libelle;

                    caseEl.append(cb, boite, texte);
                    grilleQuartiers.appendChild(caseEl);
                } else {
                    const cb = caseEl.querySelector('input');
                    if (cb) cb.checked = true;
                    const texte = caseEl.querySelector('[data-libelle="nouveau-quartier"]');
                    if (texte) texte.textContent = libelle;
                }

                blocNouveauQuartier.classList.remove('hidden');
                this.oublierErreur('quartier_ids');

                return true;
            };

            // Point posé (clic, repère déplacé, géolocalisation) → quartier le plus
            // proche coché automatiquement + rue pré-remplie par géocodage inverse.
            const remplirDepuisPoint = async (lat, lng) => {
                const proche = quartierLePlusProche(lat, lng);

                if (proche) {
                    const caseQuartier = casesQuartiers().find((c) => String(c.value) === String(proche.id));

                    if (caseQuartier) {
                        caseQuartier.checked = true;
                        this.oublierErreur('quartier_ids');
                        masquerNouveauQuartier();
                        afficherInfo('Quartier « ' + proche.nom + ' » sélectionné automatiquement.');
                    } else {
                        afficherInfo('Le quartier le plus proche n\'est pas dans votre périmètre.');
                    }
                } else {
                    afficherInfo('Aucun quartier connu à moins de 3 km : recherche d\'un quartier à créer…');
                }

                if (! lieuInput) {
                    return;
                }

                const sequence = ++requeteAdresse;

                if (spinnerAdresse) {
                    spinnerAdresse.classList.remove('hidden');
                }

                try {
                    const url = new URL(ADRESSE_URL_ALERTE, window.location.origin);
                    url.searchParams.set('latitude', lat);
                    url.searchParams.set('longitude', lng);

                    const reponse = await fetch(url, { headers: { Accept: 'application/json' } });
                    if (! reponse.ok) {
                        if (! proche) {
                            afficherInfo('Aucun quartier connu à moins de 3 km : le cercle reste la cible principale.');
                        }
                        return;
                    }

                    const donnees = await reponse.json();

                    // Un clic plus récent a déjà lancé une autre recherche : on ignore.
                    if (sequence !== requeteAdresse) return;
                    if (donnees && donnees.adresse) {
                        lieuInput.value = donnees.adresse;
                    }

                    if (proche) {
                        return;
                    }

                    if (donnees && proposerNouveauQuartier(donnees)) {
                        afficherInfo('Nouveau quartier « ' + donnees.quartier + ' » (' + donnees.ville + ') ajouté à la liste et coché.');
                    } else {
                        afficherInfo('Aucun quartier connu à moins de 3 km : le cercle reste la cible principale.');
                    }
                } catch (e) {
                    // Silencieux : la rue reste saisissable à la main.
                    if (sequence === requeteAdresse && ! proche) {
                        afficherInfo('Aucun quartier connu à moins de 3 km : le cercle reste la cible principale.');
                    }
                } finally {
                    if (sequence === requeteAdresse && spinnerAdresse) {
                        spinnerAdresse.classList.add('hidden');
                    }
                }
            };

            const majCercle = () => {
                if (latInput.value === '' || lngInput.value === '') {
                    return;
                }
                const pos = [parseFloat(latInput.value), parseFloat(lngInput.value)];
                if (cercle) {
                    cercle.setLatLng(pos).setRadius(rayon());
                } else {
                    cercle = L.circle(pos, { radius: rayon(), color: '#1b77ba', fillColor: '#1b77ba', fillOpacity: 0.15, weight: 2 }).addTo(this.carte);
                }
                if (marqueur) {
                    marqueur.setLatLng(pos);
                } else {
                    marqueur = L.marker(pos, { draggable: true }).addTo(this.carte);
                    marqueur.on('dragend', () => {
                        const p = marqueur.getLatLng();
                        poser(p.lat, p.lng, true);
                    });
                }
            };

            const poser = (lat, lng, auto = false) => {
                latInput.value = lat.toFixed(7);
                lngInput.value = lng.toFixed(7);
                this.oublierErreur('latitude');
                this.oublierErreur('longitude');
                majCercle();

                if (auto) {
                    remplirDepuisPoint(lat, lng);
                }
            };

            this.carte.on('click', (e) => poser(e.latlng.lat, e.latlng.lng, true));
            rayonInput.addEventListener('input', majCercle);
            majCercle();

            // Toute saisie manuelle de la température arme l'override.
            const tempInput = form.querySelector('[name="temperature_actuelle"]');
            if (tempInput) {
                tempInput.addEventListener('input', () => { this.temperatureManuelle = true; });
            }

            const btnLoc = document.getElementById('btn-localiser-alerte');
            if (btnLoc && navigator.geolocation) {
                btnLoc.addEventListener('click', () => {
                    btnLoc.disabled = true;
                    navigator.geolocation.getCurrentPosition((p) => {
                        this.carte.setView([p.coords.latitude, p.coords.longitude], 15);
                        poser(p.coords.latitude, p.coords.longitude, true);
                        btnLoc.disabled = false;
                    }, () => { btnLoc.disabled = false; });
                });
            }

            const btnClear = document.getElementById('btn-effacer-alerte');
            if (btnClear) {
                btnClear.addEventListener('click', () => {
                    latInput.value = '';
                    lngInput.value = '';
                    if (cercle) { this.carte.removeLayer(cercle); cercle = null; }
                    if (marqueur) { this.carte.removeLayer(marqueur); marqueur = null; }
                    masquerNouveauQuartier();
                    masquerInfo();
                });
            }

            // Cocher un quartier géolocalisé recentre la carte et pose le point.
            form.querySelectorAll('input[name="quartier_ids[]"]').forEach((cb) => {
                cb.addEventListener('change', () => {
                    if (! cb.checked) {
                        return;
                    }
                    this.oublierErreur('quartier_ids');
                    const q = QUARTIERS_GEO.find((x) => String(x.id) === String(cb.value));
                    if (! q) {
                        return;
                    }
                    this.carte.setView([q.lat, q.lng], 14);
                    poser(q.lat, q.lng);
                    afficherInfo('Quartier « ' + q.nom + ' » sélectionné.');
                });
            });

            // Une correction efface immédiatement le message rouge correspondant.
            ['titre', 'seuil_temperature', 'debut', 'fin', 'lieu', 'temperature_actuelle', 'temperature_ressentie', 'humidite', 'nouveau_quartier_nom', 'nouveau_quartier_ville', 'nouveau_quartier_code_postal'].forEach((name) => {
                form.querySelectorAll('[name="' + name + '"]').forEach((el) => {
                    el.addEventListener('input', () => this.oublierErreur(name));
                });
            });
        },
        oublierErreur(name) {
            if (! this.erreurs[name]) {
                return;
            }
            const copie = { ...this.erreurs };
            delete copie[name];
            this.erreurs = copie;
        },
        valider(event) {
            const form = this.$root;
            const valeur = (name) => (form.querySelector('[name="' + name + '"]')?.value ?? '').trim();
            const nombre = (name) => {
                const brut = valeur(name);
                return brut === '' ? null : Number(brut);
            };
            const erreurs = {};

            const titre = valeur('titre');
            if (titre === '') {
                erreurs.titre = 'Le titre de l\'alerte est obligatoire.';
            } else if (titre.length < 3) {
                erreurs.titre = 'Le titre est trop court (3 caractères minimum).';
            }

            const seuil = nombre('seuil_temperature');
            if (seuil === null || Number.isNaN(seuil)) {
                erreurs.seuil_temperature = 'Le seuil de température est obligatoire.';
            } else if (seuil < 30 || seuil > 55) {
                erreurs.seuil_temperature = 'Le seuil de température doit être compris entre 30 et 55 °C.';
            }

            const debut = valeur('debut');
            const fin = valeur('fin');
            if (debut === '') {
                erreurs.debut = 'La date de début est obligatoire.';
            }
            if (fin === '') {
                erreurs.fin = 'La date de fin est obligatoire.';
            } else if (debut !== '' && new Date(fin) <= new Date(debut)) {
                erreurs.fin = 'La date de fin doit être postérieure à la date de début.';
            }

            const latitude = valeur('latitude');
            const longitude = valeur('longitude');
            const quartiers = form.querySelectorAll('input[name="quartier_ids[]"]:checked');

            if ((latitude === '') !== (longitude === '')) {
                const manquant = latitude === '' ? 'latitude' : 'longitude';
                erreurs[manquant] = 'Un point sur la carte exige une latitude ET une longitude.';
            } else if (latitude === '' && quartiers.length === 0) {
                erreurs.quartier_ids = 'Ciblez l\'alerte : cochez au moins un quartier ou posez un point sur la carte.';
            }

            const temperature = nombre('temperature_actuelle');
            if (temperature !== null && (Number.isNaN(temperature) || temperature < -10 || temperature > 60)) {
                erreurs.temperature_actuelle = 'La température doit être comprise entre -10 et 60 °C.';
            }

            const ressentie = nombre('temperature_ressentie');
            if (ressentie !== null && (Number.isNaN(ressentie) || ressentie < -10 || ressentie > 70)) {
                erreurs.temperature_ressentie = 'La température ressentie doit être comprise entre -10 et 70 °C.';
            }

            const humidite = nombre('humidite');
            if (humidite !== null && (! Number.isInteger(humidite) || humidite < 0 || humidite > 100)) {
                erreurs.humidite = 'L\'humidité doit être comprise entre 0 et 100 %.';
            }

            if (valeur('lieu').length > 255) {
                erreurs.lieu = 'La rue / le lieu précis ne doit pas dépasser 255 caractères.';
            }

            const blocNouveau = document.getElementById('bloc-nouveau-quartier-alerte');
            const nouveauVisible = blocNouveau !== null && ! blocNouveau.classList.contains('hidden');

            if (nouveauVisible) {
                if (valeur('nouveau_quartier_nom').length < 2) {
                    erreurs.nouveau_quartier_nom = 'Le nom du nouveau quartier est requis (2 caractères minimum).';
                }
                if (valeur('nouveau_quartier_ville').length < 2) {
                    erreurs.nouveau_quartier_ville = 'La ville du nouveau quartier est requise (2 caractères minimum).';
                }
                const codePostal = valeur('nouveau_quartier_code_postal');
                if (codePostal !== '' && ! /^[0-9A-Za-z\- ]{3,10}$/.test(codePostal)) {
                    erreurs.nouveau_quartier_code_postal = 'Le code postal du nouveau quartier n\'est pas valide.';
                }
            }

            this.erreurs = erreurs;

            if (Object.keys(erreurs).length === 0) {
                return;
            }

            event.preventDefault();

            this.$nextTick(() => {
                const premier = Object.keys(erreurs)[0];
                const cible = premier === 'quartier_ids'
                    ? form.querySelector('#quartier-erreur-alerte')
                    : form.querySelector('[name="' + premier + '"]');

                (cible ?? this.$root).scrollIntoView({ behavior: 'smooth', block: 'center' });

                if (cible && typeof cible.focus === 'function') {
                    cible.focus({ preventScroll: true });
                }
            });
        },
        async prefill(avecMessage) {
            const form = this.$root;
            const ids = Array.from(form.querySelectorAll('input[name="quartier_ids[]"]:checked')).map((i) => i.value);
            const lat = form.querySelector('[name="latitude"]').value || null;
            const lng = form.querySelector('[name="longitude"]').value || null;
            if (ids.length === 0 && (! lat || ! lng)) {
                this.retour = { ok: false, raison: 'Sélectionnez un quartier ou posez un point sur la carte avant de lancer l\'IA.' };
                return;
            }
            this.chargement = true;
            this.retour = null;
            try {
                const res = await fetch(form.dataset.prefillUrl, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'Accept': 'application/json',
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                    },
                    body: JSON.stringify({
                        quartier_ids: ids,
                        latitude: lat,
                        longitude: lng,
                        seuil_temperature: form.querySelector('[name="seuil_temperature"]').value || null,
                        temperature_actuelle: this.temperatureManuelle ? (form.querySelector('[name="temperature_actuelle"]').value || null) : null,
                        humidite: form.querySelector('[name="humidite"]').value || null,
                        avec_message: avecMessage,
                    }),
                });
                const data = await res.json();
                if (! res.ok) {
                    this.retour = {
                        ok: false,
                        raison: data.message || 'Requête invalide : vérifiez les champs du formulaire.',
                    };
                    return;
                }
                this.retour = data;
                if (data.ok) {
                    form.querySelector('[name="niveau"]').value = data.niveau;
                    // Valeur auto-remplie : ne pas la considérer comme une saisie
                    // manuelle, sinon l'override se figerait pour les clics suivants.
                    this.temperatureManuelle = false;
                    form.querySelector('[name="temperature_actuelle"]').value = data.temperature;
                    form.querySelector('[name="source_meteo"]').value = data.source;
                    if (data.humidite !== null && data.humidite !== undefined) {
                        form.querySelector('[name="humidite"]').value = data.humidite;
                    }
                    if (data.message) {
                        form.querySelector('[name="message"]').value = data.message;
                    }
                    // Rattache la trace IA au formulaire (boucle de retour).
                    this.predictionId = data.prediction_id || '';
                }
                // Le panneau météo (aside) reflète la même analyse.
                window.dispatchEvent(new CustomEvent('alerte-ia', { detail: data }));
            } catch (e) {
                this.retour = { ok: false, raison: 'Erreur réseau. Réessayez ou saisissez la température manuellement.' };
            } finally {
                this.chargement = false;
            }
        },
    };
}
</script>
