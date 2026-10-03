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

<div x-data="alerteForm()" class="flex flex-col gap-4">

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
</div>

{{-- Section identification --}}
<fieldset class="rounded-xl border border-outline-variant/30 p-4">
<legend class="px-2 font-title-md text-title-md text-on-surface font-semibold flex items-center gap-2"><span class="material-symbols-outlined text-primary text-[20px]">warning</span>Identification</legend>
<div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
<div class="flex flex-col gap-1 sm:col-span-2">
<label for="titre" class="font-label-md text-label-md text-on-surface font-medium">Titre de l'alerte <span class="text-error">*</span></label>
<input type="text" id="titre" name="titre" value="{{ old('titre', $alerte?->titre ?? '') }}" required maxlength="150" placeholder="Ex. : Vigilance forte chaleur" class="w-full rounded-xl bg-surface-container border border-outline-variant/40 px-3 py-2.5 text-on-surface placeholder:text-outline focus:border-primary-container focus:outline-none @error('titre') border-error @enderror" />
@error('titre') <p class="font-body-sm text-body-sm text-error flex items-center gap-1"><span class="material-symbols-outlined text-[16px]">error</span>{{ $message }}</p> @enderror
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
</div>
</div>
</fieldset>

{{-- Section quartiers (N---N) — désormais facultative --}}
<fieldset class="rounded-xl border border-outline-variant/30 p-4">
<legend class="px-2 font-title-md text-title-md text-on-surface font-semibold flex items-center gap-2"><span class="material-symbols-outlined text-primary text-[20px]">location_on</span>Rattachement aux quartiers <span class="font-body-sm text-body-sm text-on-surface-variant font-normal">(facultatif)</span></legend>
@error('quartier_ids') <p class="mb-2 font-body-sm text-body-sm text-error flex items-center gap-1"><span class="material-symbols-outlined text-[16px]">error</span>{{ $message }}</p> @enderror
@error('quartier_ids.*') <p class="mb-2 font-body-sm text-body-sm text-error flex items-center gap-1"><span class="material-symbols-outlined text-[16px]">error</span>{{ $message }}</p> @enderror
<div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-2">
@forelse ($quartiers as $q)
<label class="flex items-center gap-2 rounded-lg bg-surface-container border border-outline-variant/30 px-3 py-2 cursor-pointer hover:bg-surface-container-high transition-colors">
<input type="checkbox" name="quartier_ids[]" value="{{ $q->id }}" @checked(in_array($q->id, $selectedQ, true)) class="h-4 w-4 rounded accent-primary-container" />
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
@endif
</fieldset>

{{-- Section zone géographique (carte : point + rayon) --}}
<fieldset class="rounded-xl border border-outline-variant/30 p-4">
<legend class="px-2 font-title-md text-title-md text-on-surface font-semibold flex items-center gap-2"><span class="material-symbols-outlined text-primary text-[20px]">map</span>Zone géographique (carte)</legend>
<p class="mb-2 font-body-sm text-body-sm text-on-surface-variant">Cliquez sur la carte pour poser le centre de la zone (ou cochez un quartier pour le centrer), puis ajustez le rayon. Une alerte peut cibler un ou plusieurs quartiers, un cercle, ou les deux.</p>
<link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" />
<div id="carte-alerte-form" class="w-full h-72 rounded-xl overflow-hidden z-0 border border-outline-variant/30"></div>
<input type="hidden" id="latitude" name="latitude" value="{{ old('latitude', $alerte?->latitude) }}" />
<input type="hidden" id="longitude" name="longitude" value="{{ old('longitude', $alerte?->longitude) }}" />
@error('latitude') <p class="mt-1 font-body-sm text-body-sm text-error flex items-center gap-1"><span class="material-symbols-outlined text-[16px]">error</span>{{ $message }}</p> @enderror
@error('longitude') <p class="mt-1 font-body-sm text-body-sm text-error flex items-center gap-1"><span class="material-symbols-outlined text-[16px]">error</span>{{ $message }}</p> @enderror
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
</div>
<div class="flex flex-col gap-1">
<label for="fin" class="font-label-md text-label-md text-on-surface font-medium">Fin <span class="text-error">*</span></label>
<input type="datetime-local" id="fin" name="fin" value="{{ old('fin', $alerte?->fin?->format('Y-m-d\TH:i') ?? now()->addDay()->format('Y-m-d\TH:i')) }}" required class="w-full rounded-xl bg-surface-container border border-outline-variant/40 px-3 py-2.5 text-on-surface focus:border-primary-container focus:outline-none @error('fin') border-error @enderror" />
@error('fin') <p class="font-body-sm text-body-sm text-error flex items-center gap-1"><span class="material-symbols-outlined text-[16px]">error</span>{{ $message }}</p> @enderror
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
</div>
<div class="flex flex-col gap-1">
<label for="temperature_ressentie" class="font-label-md text-label-md text-on-surface font-medium">Ressentie (°C)</label>
<input type="number" step="0.1" min="-10" max="70" id="temperature_ressentie" name="temperature_ressentie" value="{{ old('temperature_ressentie', $alerte?->temperature_ressentie) }}" class="w-full rounded-xl bg-surface-container border border-outline-variant/40 px-3 py-2.5 text-on-surface focus:border-primary-container focus:outline-none @error('temperature_ressentie') border-error @enderror" />
@error('temperature_ressentie') <p class="font-body-sm text-body-sm text-error flex items-center gap-1"><span class="material-symbols-outlined text-[16px]">error</span>{{ $message }}</p> @enderror
</div>
<div class="flex flex-col gap-1">
<label for="humidite" class="font-label-md text-label-md text-on-surface font-medium">Humidité (%)</label>
<input type="number" step="1" min="0" max="100" id="humidite" name="humidite" value="{{ old('humidite', $alerte?->humidite) }}" class="w-full rounded-xl bg-surface-container border border-outline-variant/40 px-3 py-2.5 text-on-surface focus:border-primary-container focus:outline-none @error('humidite') border-error @enderror" />
@error('humidite') <p class="font-body-sm text-body-sm text-error flex items-center gap-1"><span class="material-symbols-outlined text-[16px]">error</span>{{ $message }}</p> @enderror
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
const QUARTIERS_GEO = @json($quartiersGeo);
const CENTRE_DEFAUT_ALERTE = [36.8065, 10.1815];

function alerteForm() {
    return {
        chargement: false,
        retour: null,
        carte: null,
        init() {
            const form = this.$root;
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
                    marqueur = L.circleMarker(pos, { radius: 7, color: '#1b77ba', fillColor: '#1b77ba', fillOpacity: 1, weight: 3 }).addTo(this.carte);
                }
            };

            const poser = (lat, lng) => {
                latInput.value = lat.toFixed(7);
                lngInput.value = lng.toFixed(7);
                majCercle();
            };

            this.carte.on('click', (e) => poser(e.latlng.lat, e.latlng.lng));
            rayonInput.addEventListener('input', majCercle);
            majCercle();

            const btnLoc = document.getElementById('btn-localiser-alerte');
            if (btnLoc && navigator.geolocation) {
                btnLoc.addEventListener('click', () => {
                    btnLoc.disabled = true;
                    navigator.geolocation.getCurrentPosition((p) => {
                        this.carte.setView([p.coords.latitude, p.coords.longitude], 15);
                        poser(p.coords.latitude, p.coords.longitude);
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
                });
            }

            // Cocher un quartier géolocalisé recentre la carte et pose le point.
            form.querySelectorAll('input[name="quartier_ids[]"]').forEach((cb) => {
                cb.addEventListener('change', () => {
                    if (! cb.checked) {
                        return;
                    }
                    const q = QUARTIERS_GEO.find((x) => String(x.id) === String(cb.value));
                    if (! q) {
                        return;
                    }
                    this.carte.setView([q.lat, q.lng], 14);
                    poser(q.lat, q.lng);
                });
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
                const res = await fetch('{{ route('back.alertes.prefill') }}', {
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
                        temperature_actuelle: form.querySelector('[name="temperature_actuelle"]').value || null,
                        humidite: form.querySelector('[name="humidite"]').value || null,
                        avec_message: avecMessage,
                    }),
                });
                const data = await res.json();
                this.retour = data;
                if (data.ok) {
                    form.querySelector('[name="niveau"]').value = data.niveau;
                    form.querySelector('[name="temperature_actuelle"]').value = data.temperature;
                    form.querySelector('[name="source_meteo"]').value = data.source;
                    if (data.humidite !== null && data.humidite !== undefined) {
                        form.querySelector('[name="humidite"]').value = data.humidite;
                    }
                    if (data.message) {
                        form.querySelector('[name="message"]').value = data.message;
                    }
                }
            } catch (e) {
                this.retour = { ok: false, raison: 'Erreur réseau. Réessayez ou saisissez la température manuellement.' };
            } finally {
                this.chargement = false;
            }
        },
    };
}
</script>
