@php
    // Quartiers géolocalisés : servent à recentrer la carte au choix d'un quartier.
    $quartiersGeo = $quartiers
        ->map(fn ($q) => ['id' => $q->id, 'lat' => $q->latitude, 'lng' => $q->longitude])
        ->filter(fn ($q) => $q['lat'] !== null && $q['lng'] !== null)
        ->values();
@endphp
@csrf
{{-- Section zone : un quartier facultatif OU un point posé librement sur la carte --}}
<fieldset x-data="coupureForm()" class="rounded-xl border border-outline-variant/30 p-4">
<legend class="px-2 font-title-md text-title-md text-on-surface font-semibold flex items-center gap-2"><span class="material-symbols-outlined text-primary text-[20px]">location_on</span>Zone touchée</legend>

<div class="flex flex-col gap-1">
<label for="quartier_id" class="font-label-md text-label-md text-on-surface font-medium">Quartier <span class="font-body-sm text-body-sm text-on-surface-variant font-normal">(optionnel)</span></label>
<select id="quartier_id" name="quartier_id" class="w-full rounded-xl bg-surface-container border border-outline-variant/40 px-3 py-2.5 text-on-surface focus:border-primary-container focus:outline-none @error('quartier_id') border-error @enderror">
<option value="">— Aucun (utilisez le point sur la carte) —</option>
@foreach ($quartiers as $q)
<option value="{{ $q->id }}" @selected(old('quartier_id', $coupure?->quartier_id) == $q->id)>{{ $q->nom }} ({{ $q->ville }})</option>
@endforeach
</select>
@error('quartier_id') <p class="font-body-sm text-body-sm text-error flex items-center gap-1"><span class="material-symbols-outlined text-[16px]">error</span>{{ $message }}</p> @enderror
@if(auth()->user()->isGestionnaire())
<p class="font-body-sm text-body-sm text-on-surface-variant">Votre périmètre : votre zone uniquement — le champ est forcé à votre quartier à l'enregistrement.</p>
@else
<p class="font-body-sm text-body-sm text-on-surface-variant">Facultatif : vous pouvez aussi (et surtout) poser un point n'importe où sur la carte ci-dessous.</p>
@endif
</div>

{{-- Point libre : cible principale, indépendante de tout quartier existant. --}}
<div class="mt-4 rounded-xl border border-outline-variant/20 bg-surface-container/40 p-3">
<div class="flex items-start justify-between gap-3">
<div>
<p class="font-label-md text-label-md text-on-surface font-semibold flex items-center gap-2"><span class="material-symbols-outlined text-primary text-[20px]">map</span>Point sur la carte</p>
<p class="font-body-sm text-body-sm text-on-surface-variant">Cliquez sur la carte ou déplacez le repère pour situer précisément la coupure, où que ce soit.</p>
</div>
<div class="shrink-0 flex flex-wrap gap-2">
<button type="button" id="btn-localiser-coupure" class="px-3 py-2 rounded-lg bg-surface-container text-on-surface font-label-md text-label-md hover:bg-surface-bright inline-flex items-center gap-2"><span class="material-symbols-outlined text-[18px]">my_location</span>Me localiser</button>
<button type="button" id="btn-effacer-coupure" class="px-3 py-2 rounded-lg bg-surface-container text-on-surface-variant font-label-md text-label-md hover:bg-surface-bright inline-flex items-center gap-2"><span class="material-symbols-outlined text-[18px]">close</span>Effacer</button>
</div>
</div>
<link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" />
<div id="carte-coupure-form" class="mt-3 w-full h-64 rounded-xl overflow-hidden z-0 border border-outline-variant/30"></div>
<input type="hidden" id="latitude" name="latitude" value="{{ old('latitude', $coupure?->latitude) }}" />
<input type="hidden" id="longitude" name="longitude" value="{{ old('longitude', $coupure?->longitude) }}" />
@error('latitude') <p class="mt-1 font-body-sm text-body-sm text-error flex items-center gap-1"><span class="material-symbols-outlined text-[16px]">error</span>{{ $message }}</p> @enderror
@error('longitude') <p class="mt-1 font-body-sm text-body-sm text-error flex items-center gap-1"><span class="material-symbols-outlined text-[16px]">error</span>{{ $message }}</p> @enderror
</div>

<div class="mt-3 flex flex-col gap-1">
<label for="lieu" class="font-label-md text-label-md text-on-surface font-medium">Rue / lieu précis <span class="font-body-sm text-body-sm text-on-surface-variant font-normal">(optionnel)</span></label>
<input type="text" id="lieu" name="lieu" value="{{ old('lieu', $coupure?->lieu ?? '') }}" maxlength="255" placeholder="Ex. : rue des Lilas" class="w-full rounded-xl bg-surface-container border border-outline-variant/40 px-3 py-2.5 text-on-surface placeholder:text-outline focus:border-primary-container focus:outline-none" />
@error('lieu') <p class="font-body-sm text-body-sm text-error flex items-center gap-1"><span class="material-symbols-outlined text-[16px]">error</span>{{ $message }}</p> @enderror
<p class="font-body-sm text-body-sm text-on-surface-variant">Deux rues différentes = deux coupures simultanées acceptées sur la même zone.</p>
</div>
</fieldset>

{{-- Section nature --}}
<fieldset class="rounded-xl border border-outline-variant/30 p-4">
<legend class="px-2 font-title-md text-title-md text-on-surface font-semibold flex items-center gap-2"><span class="material-symbols-outlined text-primary text-[20px]">bolt</span>Nature de la coupure</legend>
<div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
<div class="flex flex-col gap-1">
<label for="type" class="font-label-md text-label-md text-on-surface font-medium">Type <span class="text-error">*</span></label>
<select id="type" name="type" required class="w-full rounded-xl bg-surface-container border border-outline-variant/40 px-3 py-2.5 text-on-surface focus:border-primary-container focus:outline-none @error('type') border-error @enderror">
@foreach (\App\Enums\TypeCoupure::cases() as $type)
<option value="{{ $type->value }}" @selected(old('type', $coupure?->type?->value) === $type->value)>{{ $type->label() }}</option>
@endforeach
</select>
@error('type') <p class="font-body-sm text-body-sm text-error flex items-center gap-1"><span class="material-symbols-outlined text-[16px]">error</span>{{ $message }}</p> @enderror
</div>
<div class="flex flex-col gap-1">
<label for="statut" class="font-label-md text-label-md text-on-surface font-medium">Statut <span class="text-error">*</span></label>
<select id="statut" name="statut" required class="w-full rounded-xl bg-surface-container border border-outline-variant/40 px-3 py-2.5 text-on-surface focus:border-primary-container focus:outline-none @error('statut') border-error @enderror">
@foreach (\App\Enums\StatutCoupure::cases() as $statut)
<option value="{{ $statut->value }}" @selected(old('statut', $coupure?->statut?->value) === $statut->value)>{{ $statut->label() }}</option>
@endforeach
</select>
@error('statut') <p class="font-body-sm text-body-sm text-error flex items-center gap-1"><span class="material-symbols-outlined text-[16px]">error</span>{{ $message }}</p> @enderror
<p class="font-body-sm text-body-sm text-on-surface-variant">Passez à <strong>Résolue</strong> pour clôturer (sort de la carte publique).</p>
</div>
</div>
</fieldset>

{{-- Section horaires --}}
<fieldset class="rounded-xl border border-outline-variant/30 p-4">
<legend class="px-2 font-title-md text-title-md text-on-surface font-semibold flex items-center gap-2"><span class="material-symbols-outlined text-primary text-[20px]">schedule</span>Horaires estimés</legend>
<div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
<div class="flex flex-col gap-1">
<label for="debut" class="font-label-md text-label-md text-on-surface font-medium">Début <span class="text-error">*</span></label>
<input type="datetime-local" id="debut" name="debut" value="{{ old('debut', $coupure?->debut?->format('Y-m-d\TH:i') ?? '') }}" required class="w-full rounded-xl bg-surface-container border border-outline-variant/40 px-3 py-2.5 text-on-surface focus:border-primary-container focus:outline-none @error('debut') border-error @enderror" />
@error('debut') <p class="font-body-sm text-body-sm text-error flex items-center gap-1"><span class="material-symbols-outlined text-[16px]">error</span>{{ $message }}</p> @enderror
</div>
<div class="flex flex-col gap-1">
<label for="fin" class="font-label-md text-label-md text-on-surface font-medium">Fin <span class="font-body-sm text-body-sm text-on-surface-variant font-normal">(optionnelle, après le début)</span></label>
<input type="datetime-local" id="fin" name="fin" value="{{ old('fin', $coupure?->fin?->format('Y-m-d\TH:i') ?? '') }}" class="w-full rounded-xl bg-surface-container border border-outline-variant/40 px-3 py-2.5 text-on-surface focus:border-primary-container focus:outline-none @error('fin') border-error @enderror" />
@error('fin') <p class="font-body-sm text-body-sm text-error flex items-center gap-1"><span class="material-symbols-outlined text-[16px]">error</span>{{ $message }}</p> @enderror
</div>
</div>
<p class="mt-2 font-body-sm text-body-sm text-on-surface-variant">Deux coupures non résolues ne peuvent pas se chevaucher au même endroit (même zone, même rue) sur le même créneau.</p>
</fieldset>

{{-- Section message --}}
<fieldset class="rounded-xl border border-outline-variant/30 p-4">
<legend class="px-2 font-title-md text-title-md text-on-surface font-semibold flex items-center gap-2"><span class="material-symbols-outlined text-primary text-[20px]">message</span>Message aux habitants</legend>
<div class="flex flex-col gap-1">
<label for="description" class="sr-only">Message aux habitants</label>
<textarea id="description" name="description" rows="3" maxlength="2000" placeholder="Ex. : maintenance planifiée rue des Lilas de 8h à 12h, prévoyez vos équipements sensibles…" class="w-full rounded-xl bg-surface-container border border-outline-variant/40 px-3 py-2.5 text-on-surface placeholder:text-outline focus:border-primary-container focus:outline-none @error('description') border-error @enderror">{{ old('description', $coupure?->description ?? '') }}</textarea>
@error('description') <p class="font-body-sm text-body-sm text-error flex items-center gap-1"><span class="material-symbols-outlined text-[16px]">error</span>{{ $message }}</p> @enderror
</div>
</fieldset>

<div class="flex flex-col sm:flex-row sm:justify-end gap-3">
<a href="{{ route('back.coupures.index') }}" class="px-4 py-2.5 rounded-xl font-label-md text-label-md text-on-surface-variant hover:bg-surface-container-high inline-flex items-center justify-center gap-2"><span class="material-symbols-outlined text-[18px]">arrow_back</span>Retour à la liste</a>
<button type="submit" class="px-6 py-2.5 rounded-xl bg-primary-container text-on-primary-container font-label-md text-label-md font-semibold hover:opacity-95 shadow">Enregistrer</button>
</div>

<script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
<script>
const QUARTIERS_GEO_COUPURE = @json($quartiersGeo);
const CENTRE_DEFAUT_COUPURE = @json($centre ?? [36.8065, 10.1815]);

function coupureForm() {
    return {
        init() {
            const root = this.$root;
            const latInput = root.querySelector('[name="latitude"]');
            const lngInput = root.querySelector('[name="longitude"]');
            const carteEl = document.getElementById('carte-coupure-form');
            if (! latInput || ! lngInput || ! carteEl || typeof L === 'undefined') {
                return;
            }

            const depart = (latInput.value && lngInput.value)
                ? [parseFloat(latInput.value), parseFloat(lngInput.value)]
                : CENTRE_DEFAUT_COUPURE;

            const carte = L.map('carte-coupure-form').setView(depart, 13);
            L.tileLayer('https://tile.openstreetmap.org/{z}/{x}/{y}.png', {
                maxZoom: 19,
                attribution: '&copy; OpenStreetMap',
            }).addTo(carte);

            let marqueur = null;

            const poser = (lat, lng, recentrer) => {
                latInput.value = lat.toFixed(7);
                lngInput.value = lng.toFixed(7);
                if (marqueur) {
                    marqueur.setLatLng([lat, lng]);
                } else {
                    marqueur = L.marker([lat, lng], { draggable: true }).addTo(carte);
                    marqueur.on('dragend', () => {
                        const p = marqueur.getLatLng();
                        latInput.value = p.lat.toFixed(7);
                        lngInput.value = p.lng.toFixed(7);
                    });
                }
                if (recentrer) {
                    carte.setView([lat, lng]);
                }
            };

            if (latInput.value && lngInput.value) {
                poser(parseFloat(latInput.value), parseFloat(lngInput.value), false);
            }

            carte.on('click', (e) => poser(e.latlng.lat, e.latlng.lng, false));

            // Choisir un quartier géolocalisé recentre la carte (sans écraser un point déjà posé).
            root.querySelectorAll('[name="quartier_id"]').forEach((sel) => {
                sel.addEventListener('change', () => {
                    const q = QUARTIERS_GEO_COUPURE.find((x) => String(x.id) === String(sel.value));
                    if (q) {
                        carte.setView([q.lat, q.lng], 14);
                    }
                });
            });

            const btnLoc = document.getElementById('btn-localiser-coupure');
            if (btnLoc && navigator.geolocation) {
                btnLoc.addEventListener('click', () => {
                    btnLoc.disabled = true;
                    navigator.geolocation.getCurrentPosition((p) => {
                        poser(p.coords.latitude, p.coords.longitude, true);
                        btnLoc.disabled = false;
                    }, () => { btnLoc.disabled = false; });
                });
            }

            const btnClear = document.getElementById('btn-effacer-coupure');
            if (btnClear) {
                btnClear.addEventListener('click', () => {
                    latInput.value = '';
                    lngInput.value = '';
                    if (marqueur) { carte.removeLayer(marqueur); marqueur = null; }
                });
            }
        },
    };
}
</script>
