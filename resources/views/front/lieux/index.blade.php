<x-app-layout>
    <x-slot name="header">
        <div class="flex flex-col gap-1">
            <p class="font-label-sm text-label-sm uppercase tracking-[0.18em] text-primary font-semibold">Mon foyer</p>
            <h1 class="font-headline-lg text-headline-lg text-on-surface">Mes lieux</h1>
            <p class="mt-1 font-body-sm text-body-sm text-on-surface-variant">Domicile, travail, famille… Posez chaque lieu sur la carte : ils servent à vous alerter selon votre position réelle.</p>
        </div>
    </x-slot>

    <div class="grid grid-cols-1 lg:grid-cols-5 gap-space-lg" x-data="mesLieux(@js($config))">
        {{-- Colonne carte + formulaire : un seul repère « actif » (ajout ou modification). --}}
        <div class="lg:col-span-3">
            <div class="rounded-xl border border-outline-variant/30 bg-surface-container-low/60 p-4 flex flex-col gap-3">
                <div class="flex items-start justify-between gap-3">
                    <div>
                        <p class="font-title-md text-title-md text-on-surface font-semibold flex items-center gap-2">
                            <span class="material-symbols-outlined text-primary text-[20px]" x-text="mode === 'edit' ? 'edit_location_alt' : 'add_location_alt'"></span>
                            <span x-text="mode === 'edit' ? 'Repositionner le lieu' : 'Ajouter un lieu'"></span>
                        </p>
                        <p class="font-body-sm text-body-sm text-on-surface-variant">Cliquez sur la carte pour poser le point, ou déplacez le marqueur.</p>
                    </div>
                    <button type="button" id="btn-localiser-lieu" class="shrink-0 px-3 py-2 rounded-lg bg-surface-container text-on-surface font-label-md text-label-md hover:bg-surface-bright inline-flex items-center gap-2">
                        <span class="material-symbols-outlined text-[18px]">my_location</span>Me localiser
                    </button>
                </div>

                <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" />
                <div id="carte-lieux" class="w-full h-80 rounded-xl overflow-hidden z-0 border border-outline-variant/30"></div>

                <form method="POST" :action="action" class="flex flex-col gap-3">
                    @csrf
                    <template x-if="mode === 'edit'">
                        <input type="hidden" name="_method" value="PATCH" />
                    </template>

                    @if ($errors->any())
                        <div class="rounded-lg bg-error-container text-on-error-container px-3 py-2.5 font-body-sm text-body-sm flex items-start gap-2">
                            <span class="material-symbols-outlined text-[18px] shrink-0 mt-0.5">warning</span>
                            <span>Veuillez corriger les champs du lieu ci-dessous.</span>
                        </div>
                    @endif

                    <div class="grid grid-cols-1 sm:grid-cols-5 gap-3">
                        <div class="sm:col-span-3 flex flex-col gap-1.5">
                            <label for="lieu-nom" class="font-label-md text-label-md text-on-surface">Nom du lieu</label>
                            <input id="lieu-nom" type="text" name="nom" x-model="form.nom" placeholder="Ex : Domicile, Travail" required
                                class="w-full rounded-lg bg-surface-container border border-outline-variant/40 px-3 py-2.5 text-on-surface focus:border-primary-container focus:outline-none" />
                            @error('nom') <p class="font-body-sm text-body-sm text-error">{{ $message }}</p> @enderror
                        </div>
                        <div class="sm:col-span-2 flex flex-col gap-1.5">
                            <label for="lieu-type" class="font-label-md text-label-md text-on-surface">Type</label>
                            <select id="lieu-type" name="type" x-model="form.type"
                                class="w-full rounded-lg bg-surface-container border border-outline-variant/40 px-3 py-2.5 text-on-surface focus:border-primary-container focus:outline-none">
                                @foreach ($types as $type)
                                    <option value="{{ $type->value }}">{{ $type->label() }}</option>
                                @endforeach
                            </select>
                            @error('type') <p class="font-body-sm text-body-sm text-error">{{ $message }}</p> @enderror
                        </div>
                    </div>

                    <div class="flex flex-col gap-1.5">
                        <label for="lieu-adresse" class="font-label-md text-label-md text-on-surface">Adresse <span class="text-on-surface-variant font-normal">(remplie d'après le point)</span></label>
                        <div class="relative">
                            <input id="lieu-adresse" type="text" name="adresse" x-model="form.adresse" placeholder="Ex : 12 rue des Tilleuls"
                                class="w-full rounded-lg bg-surface-container border border-outline-variant/40 px-3 py-2.5 pr-10 text-on-surface focus:border-primary-container focus:outline-none" />
                            <span x-show="adresseEnCours" x-cloak class="absolute right-3 top-1/2 -translate-y-1/2 flex items-center pointer-events-none">
                                <span class="material-symbols-outlined text-[18px] text-primary animate-spin">progress_activity</span>
                            </span>
                        </div>
                        <p class="font-body-sm text-body-sm text-on-surface-variant flex items-center gap-1">
                            <span class="material-symbols-outlined text-[14px]">location_on</span>Se met à jour automatiquement quand vous posez le point — modifiable à tout moment.
                        </p>
                        @error('adresse') <p class="font-body-sm text-body-sm text-error">{{ $message }}</p> @enderror
                    </div>

                    <input type="hidden" name="latitude" x-model="form.latitude" />
                    <input type="hidden" name="longitude" x-model="form.longitude" />
                    @error('latitude') <p class="font-body-sm text-body-sm text-error">{{ $message }}</p> @enderror
                    @error('longitude') <p class="font-body-sm text-body-sm text-error">{{ $message }}</p> @enderror

                    <div class="flex items-center justify-end gap-3">
                        <button type="button" x-show="mode === 'edit'" x-cloak @click="startCreate()" class="px-4 py-2 rounded-lg font-label-md text-label-md text-on-surface-variant hover:bg-surface-container-high">Annuler</button>
                        <button type="submit" class="px-4 py-2 rounded-lg bg-primary-container text-on-primary-container font-label-md text-label-md font-semibold hover:opacity-95 inline-flex items-center gap-2">
                            <span class="material-symbols-outlined text-[18px]" x-text="mode === 'edit' ? 'save' : 'add'"></span>
                            <span x-text="mode === 'edit' ? 'Enregistrer' : 'Ajouter ce lieu'"></span>
                        </button>
                    </div>
                </form>
            </div>
        </div>

        {{-- Colonne liste des lieux du foyer. --}}
        <div class="lg:col-span-2 flex flex-col gap-space-sm">
            <p class="font-title-md text-title-md text-on-surface font-semibold">Mes lieux ({{ $lieux->count() }})</p>

            @forelse ($lieuxJson as $item)
                <div class="rounded-xl border {{ $item['principal'] ? 'border-primary-container/50 bg-primary-container/10' : 'border-outline-variant/30 bg-surface-container-low/60' }} p-4 flex flex-col gap-3">
                    <div class="flex items-start gap-3">
                        <span class="material-symbols-outlined text-primary text-[22px] shrink-0">{{ $item['icone'] }}</span>
                        <div class="flex flex-col min-w-0">
                            <span class="font-title-md text-title-md text-on-surface font-semibold flex items-center gap-2">
                                {{ $item['nom'] }}
                                @if ($item['principal'])
                                    <span class="inline-flex items-center gap-1 rounded-full bg-primary-container text-on-primary-container px-2 py-0.5 font-label-sm text-label-sm">
                                        <span class="material-symbols-outlined text-[14px]">star</span>Principal
                                    </span>
                                @endif
                            </span>
                            <span class="font-body-sm text-body-sm text-on-surface-variant">{{ $item['typeLabel'] }}@if ($item['adresse']) · {{ $item['adresse'] }}@endif</span>
                        </div>
                    </div>

                    <div class="flex flex-wrap items-center gap-2">
                        <button type="button" @click='startEdit(@json($item))' class="px-3 py-1.5 rounded-lg bg-surface-container text-on-surface font-label-md text-label-md hover:bg-surface-bright inline-flex items-center gap-1.5">
                            <span class="material-symbols-outlined text-[16px]">edit</span>Modifier
                        </button>

                        @unless ($item['principal'])
                            <form method="POST" action="{{ $item['principalUrl'] }}">
                                @csrf
                                @method('PATCH')
                                <button type="submit" class="px-3 py-1.5 rounded-lg bg-surface-container text-on-surface font-label-md text-label-md hover:bg-surface-bright inline-flex items-center gap-1.5">
                                    <span class="material-symbols-outlined text-[16px]">star</span>Principal
                                </button>
                            </form>
                        @endunless

                        <form method="POST" action="{{ $item['deleteUrl'] }}" onsubmit="return confirm('Supprimer ce lieu ?');">
                            @csrf
                            @method('DELETE')
                            <button type="submit" @disabled($lieux->count() <= 1) title="{{ $lieux->count() <= 1 ? 'Au moins un lieu est requis.' : 'Supprimer' }}"
                                class="px-3 py-1.5 rounded-lg bg-error-container/60 text-on-error-container font-label-md text-label-md hover:bg-error-container inline-flex items-center gap-1.5 disabled:opacity-40 disabled:cursor-not-allowed">
                                <span class="material-symbols-outlined text-[16px]">delete</span>Supprimer
                            </button>
                        </form>
                    </div>

                    @php $apercu = $apercus[$item['id']] ?? null; @endphp
                    @if ($apercu)
                        <div class="rounded-lg border border-outline-variant/20 bg-surface-container/70 p-3 flex flex-col gap-2">
                            <div class="flex items-center justify-between gap-2">
                                <span class="flex items-center gap-1.5 font-label-sm text-label-sm text-on-surface-variant">
                                    <span class="material-symbols-outlined text-[16px] text-primary">thermostat</span>
                                    {{ $apercu['condition'] ?? 'Météo locale' }}@if ($apercu['ville']) · {{ $apercu['ville'] }}@endif
                                </span>
                                <span class="inline-flex items-center gap-1 rounded-full px-2 py-0.5 font-label-sm text-label-sm font-semibold {{ $apercu['badgeClasses'] }}">
                                    <span class="material-symbols-outlined text-[14px]">{{ $apercu['icone'] }}</span>{{ $apercu['niveauLabel'] }}
                                </span>
                            </div>
                            <div class="flex items-baseline gap-3 flex-wrap">
                                <span class="font-headline-sm text-headline-sm text-on-surface font-semibold">{{ number_format((float) $apercu['temperature'], 1, ',', '') }}°C</span>
                                @if ($apercu['ressentie'] !== null)
                                    <span class="font-body-sm text-body-sm text-on-surface-variant">Ressenti {{ number_format((float) $apercu['ressentie'], 0, ',', '') }}°C</span>
                                @endif
                                @if ($apercu['humidite'] !== null)
                                    <span class="font-body-sm text-body-sm text-on-surface-variant">Humidité {{ $apercu['humidite'] }}%</span>
                                @endif
                            </div>
                            <div class="flex items-start gap-2 rounded-lg bg-surface-container-low/60 p-2">
                                <span class="material-symbols-outlined text-primary text-[16px] shrink-0 mt-0.5">auto_awesome</span>
                                <div class="flex flex-col gap-0.5">
                                    <span class="font-label-sm text-label-sm uppercase tracking-wider text-primary font-semibold">Conseil IA</span>
                                    <p class="font-body-sm text-body-sm text-on-surface-variant">{{ $apercu['conseil'] }}</p>
                                </div>
                            </div>
                        </div>
                    @else
                        <p class="flex items-center gap-1.5 font-body-sm text-body-sm text-on-surface-variant">
                            <span class="material-symbols-outlined text-[16px]">cloud_off</span>Météo indisponible pour ce lieu.
                        </p>
                    @endif
                </div>
            @empty
                <p class="rounded-xl border border-outline-variant/30 bg-surface-container-low/60 p-4 font-body-sm text-body-sm text-on-surface-variant">Aucun lieu pour l'instant : posez votre premier point sur la carte.</p>
            @endforelse
        </div>
    </div>

    <script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
    <script>
    function mesLieux(config) {
        return {
            mode: 'create',
            action: config.urls.store,
            form: config.form,
            lieux: config.lieux,
            carte: null,
            marqueurs: {},
            actif: null,
            adresseEnCours: false,
            geoSeq: 0,

            init() {
                const el = document.getElementById('carte-lieux');
                if (! el || typeof L === 'undefined') {
                    return;
                }

                const depart = (this.form.latitude && this.form.longitude)
                    ? [parseFloat(this.form.latitude), parseFloat(this.form.longitude)]
                    : (this.lieux.length ? [this.lieux[0].lat, this.lieux[0].lng] : config.defaut);

                this.carte = L.map('carte-lieux').setView(depart, 12);
                L.tileLayer('https://tile.openstreetmap.org/{z}/{x}/{y}.png', {
                    maxZoom: 19,
                    attribution: '&copy; OpenStreetMap',
                }).addTo(this.carte);

                this.lieux.forEach((lieu) => this.poserRepere(lieu));

                if (this.lieux.length > 1) {
                    this.carte.fitBounds(this.lieux.map((l) => [l.lat, l.lng]), { maxZoom: 14, padding: [24, 24] });
                }

                this.carte.on('click', (e) => this.poserActif(e.latlng.lat, e.latlng.lng, false, true));

                // Reprise après une erreur de validation : le point saisi est replacé.
                if (this.form.latitude && this.form.longitude) {
                    this.poserActif(parseFloat(this.form.latitude), parseFloat(this.form.longitude), false);
                }

                const btn = document.getElementById('btn-localiser-lieu');
                if (btn && navigator.geolocation) {
                    btn.addEventListener('click', () => {
                        btn.disabled = true;
                        navigator.geolocation.getCurrentPosition((p) => {
                            this.poserActif(p.coords.latitude, p.coords.longitude, true, true);
                            btn.disabled = false;
                        }, () => { btn.disabled = false; });
                    });
                }
            },

            poserRepere(lieu) {
                const couleur = lieu.principal ? '#1b77ba' : '#7a8a99';
                const marqueur = L.circleMarker([lieu.lat, lieu.lng], {
                    radius: 8, color: couleur, fillColor: couleur, fillOpacity: 0.85, weight: 2,
                }).addTo(this.carte);

                const contenu = document.createElement('div');
                const titre = document.createElement('strong');
                titre.textContent = lieu.nom;
                contenu.appendChild(titre);
                const meta = document.createElement('div');
                meta.textContent = lieu.typeLabel + (lieu.adresse ? ' · ' + lieu.adresse : '');
                contenu.appendChild(meta);
                if (lieu.temp !== null && lieu.temp !== undefined) {
                    const temp = document.createElement('div');
                    temp.textContent = lieu.temp + ' °C';
                    contenu.appendChild(temp);
                }
                marqueur.bindPopup(contenu);

                this.marqueurs[lieu.id] = marqueur;
            },

            poserActif(lat, lng, recentrer, geocoder = false) {
                this.form.latitude = lat.toFixed(7);
                this.form.longitude = lng.toFixed(7);

                if (this.actif) {
                    this.actif.setLatLng([lat, lng]);
                } else {
                    this.actif = L.marker([lat, lng], { draggable: true }).addTo(this.carte);
                    this.actif.on('dragend', () => {
                        const p = this.actif.getLatLng();
                        this.form.latitude = p.lat.toFixed(7);
                        this.form.longitude = p.lng.toFixed(7);
                        this.chercherAdresse(p.lat, p.lng);
                    });
                }

                if (recentrer) {
                    this.carte.setView([lat, lng], 15);
                }

                if (geocoder) {
                    this.chercherAdresse(lat, lng);
                }
            },

            // Adresse du point choisi : remplit le champ pour l'ajout comme la
            // modification. Un compteur ignore les réponses arrivées en retard.
            async chercherAdresse(lat, lng) {
                const seq = ++this.geoSeq;
                this.adresseEnCours = true;

                try {
                    const url = new URL(config.urls.adresse, window.location.origin);
                    url.searchParams.set('latitude', lat);
                    url.searchParams.set('longitude', lng);

                    const reponse = await fetch(url, { headers: { Accept: 'application/json' } });
                    if (! reponse.ok) {
                        return;
                    }

                    const donnees = await reponse.json();
                    if (seq !== this.geoSeq || ! donnees || ! donnees.adresse) {
                        return;
                    }

                    this.form.adresse = donnees.adresse;
                } catch (e) {
                    // Silencieux : l'adresse reste saisissable à la main.
                } finally {
                    if (seq === this.geoSeq) {
                        this.adresseEnCours = false;
                    }
                }
            },

            startCreate() {
                this.mode = 'create';
                this.action = config.urls.store;
                this.form = { nom: '', type: 'domicile', adresse: '', latitude: '', longitude: '' };
                // Invalide une éventuelle recherche d'adresse encore en vol.
                this.geoSeq++;
                this.adresseEnCours = false;
                if (this.actif) {
                    this.carte.removeLayer(this.actif);
                    this.actif = null;
                }
            },

            startEdit(lieu) {
                this.mode = 'edit';
                this.action = lieu.updateUrl;
                this.form = {
                    nom: lieu.nom,
                    type: lieu.type,
                    adresse: lieu.adresse || '',
                    latitude: String(lieu.lat),
                    longitude: String(lieu.lng),
                };
                this.poserActif(lieu.lat, lieu.lng, true);
            },
        };
    }
    </script>
</x-app-layout>
