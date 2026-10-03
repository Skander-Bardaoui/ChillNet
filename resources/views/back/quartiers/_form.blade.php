@csrf
<div x-data="quartierForm()">
<div class="flex flex-col gap-2">
<label for="nom" class="font-label-md text-label-md text-on-surface">Nom du quartier</label>
<input type="text" id="nom" name="nom" value="{{ old('nom', $quartier?->nom ?? '') }}" required class="w-full rounded-lg bg-surface-container border border-outline-variant/40 px-3 py-2.5 text-on-surface focus:border-primary-container focus:outline-none" />
@error('nom') <p class="font-body-sm text-body-sm text-error">{{ $message }}</p> @enderror
</div>
<div class="mt-4 grid grid-cols-2 gap-4">
<div class="flex flex-col gap-2">
<label for="ville" class="font-label-md text-label-md text-on-surface">Ville</label>
<input type="text" id="ville" name="ville" value="{{ old('ville', $quartier?->ville ?? '') }}" required class="w-full rounded-lg bg-surface-container border border-outline-variant/40 px-3 py-2.5 text-on-surface focus:border-primary-container focus:outline-none" />
@error('ville') <p class="font-body-sm text-body-sm text-error">{{ $message }}</p> @enderror
</div>
<div class="flex flex-col gap-2">
<label for="code_postal" class="font-label-md text-label-md text-on-surface">Code postal</label>
<input type="text" id="code_postal" name="code_postal" value="{{ old('code_postal', $quartier?->code_postal ?? '') }}" required class="w-full rounded-lg bg-surface-container border border-outline-variant/40 px-3 py-2.5 text-on-surface focus:border-primary-container focus:outline-none" />
@error('code_postal') <p class="font-body-sm text-body-sm text-error">{{ $message }}</p> @enderror
</div>
</div>

{{-- Section localisation (carte) --}}
<div class="mt-6 rounded-xl border border-outline-variant/30 p-4">
<div class="flex items-start justify-between gap-3">
<div>
<p class="font-title-md text-title-md text-on-surface font-semibold flex items-center gap-2"><span class="material-symbols-outlined text-primary text-[20px]">map</span>Localisation (carte)</p>
<p class="font-body-sm text-body-sm text-on-surface-variant">Cliquez sur la carte ou déplacez le marqueur pour définir les coordonnées du quartier. Les champs Latitude / Longitude se remplissent automatiquement.</p>
</div>
<button type="button" id="btn-localiser-quartier" class="shrink-0 px-3 py-2 rounded-lg bg-surface-container text-on-surface font-label-md text-label-md hover:bg-surface-bright inline-flex items-center gap-2"><span class="material-symbols-outlined text-[18px]">my_location</span>Me localiser</button>
</div>
<link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" />
<div id="carte-quartier-form" class="mt-3 w-full h-72 rounded-xl overflow-hidden z-0 border border-outline-variant/30"></div>
<div class="mt-3 grid grid-cols-2 gap-4">
<div class="flex flex-col gap-2">
<label for="latitude" class="font-label-md text-label-md text-on-surface">Latitude</label>
<input type="number" step="0.0000001" min="-90" max="90" id="latitude" name="latitude" value="{{ old('latitude', $quartier?->latitude ?? '') }}" placeholder="36.8008" class="w-full rounded-lg bg-surface-container border border-outline-variant/40 px-3 py-2.5 text-on-surface focus:border-primary-container focus:outline-none" />
@error('latitude') <p class="font-body-sm text-body-sm text-error">{{ $message }}</p> @enderror
</div>
<div class="flex flex-col gap-2">
<label for="longitude" class="font-label-md text-label-md text-on-surface">Longitude</label>
<input type="number" step="0.0000001" min="-180" max="180" id="longitude" name="longitude" value="{{ old('longitude', $quartier?->longitude ?? '') }}" placeholder="10.1800" class="w-full rounded-lg bg-surface-container border border-outline-variant/40 px-3 py-2.5 text-on-surface focus:border-primary-container focus:outline-none" />
@error('longitude') <p class="font-body-sm text-body-sm text-error">{{ $message }}</p> @enderror
</div>
</div>
</div>

<div class="mt-4 flex flex-col gap-2">
<label for="description" class="font-label-md text-label-md text-on-surface">Description (optionnel)</label>
<textarea id="description" name="description" rows="4" class="w-full rounded-lg bg-surface-container border border-outline-variant/40 px-3 py-2.5 text-on-surface focus:border-primary-container focus:outline-none">{{ old('description', $quartier?->description ?? '') }}</textarea>
@error('description') <p class="font-body-sm text-body-sm text-error">{{ $message }}</p> @enderror
</div>
<div class="mt-6 flex justify-end gap-3">
<a href="{{ route('back.quartiers.index') }}" class="px-4 py-2 rounded-lg font-label-md text-label-md text-on-surface-variant hover:bg-surface-container-high">Annuler</a>
<button type="submit" class="px-4 py-2 rounded-lg bg-primary-container text-on-primary-container font-label-md text-label-md font-semibold hover:opacity-95">Enregistrer</button>
</div>
</div>

<script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
<script>
const CENTRE_DEFAUT_QUARTIER = [36.8065, 10.1815];

function quartierForm() {
    return {
        init() {
            const latInput = document.getElementById('latitude');
            const lngInput = document.getElementById('longitude');
            const carteEl = document.getElementById('carte-quartier-form');
            if (! latInput || ! lngInput || ! carteEl || typeof L === 'undefined') {
                return;
            }

            const depart = (latInput.value && lngInput.value)
                ? [parseFloat(latInput.value), parseFloat(lngInput.value)]
                : CENTRE_DEFAUT_QUARTIER;

            const carte = L.map('carte-quartier-form').setView(depart, 12);
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

            const sync = () => {
                if (latInput.value === '' || lngInput.value === '') {
                    if (marqueur) { carte.removeLayer(marqueur); marqueur = null; }
                    return;
                }
                poser(parseFloat(latInput.value), parseFloat(lngInput.value), false);
            };
            latInput.addEventListener('change', sync);
            lngInput.addEventListener('change', sync);

            const btnLoc = document.getElementById('btn-localiser-quartier');
            if (btnLoc && navigator.geolocation) {
                btnLoc.addEventListener('click', () => {
                    btnLoc.disabled = true;
                    navigator.geolocation.getCurrentPosition((p) => {
                        carte.setView([p.coords.latitude, p.coords.longitude], 15);
                        poser(p.coords.latitude, p.coords.longitude, false);
                        btnLoc.disabled = false;
                    }, () => { btnLoc.disabled = false; });
                });
            }
        },
    };
}
</script>
