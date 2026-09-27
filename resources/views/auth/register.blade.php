<x-guest-layout title="Configuration du Foyer Résilient" subtitle="Paramétrez votre profil territorial pour coordonner la veille thermique et protéger vos proches.">
    @php
        $roleParDefaut = old('role', 'habitant');
        $modeParDefaut = old('residence_mode', ($residencesDisponibles ?? 0) > 0 ? 'existante' : 'nouvelle');
        // Un gestionnaire ne peut pas s'inscrire « plus tard » : il doit être
        // rattaché d'emblée à la résidence qu'il gère.
        if ($roleParDefaut === 'gestionnaire' && $modeParDefaut === 'aucune') {
            $modeParDefaut = ($residencesDisponibles ?? 0) > 0 ? 'existante' : 'nouvelle';
        }
        $quartierSourceParDefaut = (($quartiers ?? collect())->isNotEmpty() && ! old('nouveau_quartier_nom')) ? 'existante' : 'nouvelle_entree';
        $quartiersAvecResidences = $quartiersAvecResidences ?? ($quartiers ?? collect())->filter(fn ($quartier) => $quartier->residences->isNotEmpty());
    @endphp

    {{-- Bandeau mission — version compacte de la maquette --}}
    <div class="flex items-center gap-3 rounded-xl bg-surface-container px-4 py-3">
        <div class="w-10 h-10 shrink-0 rounded-xl bg-surface-container-high flex items-center justify-center">
            <span class="material-symbols-outlined text-primary-container text-[22px]">shield</span>
        </div>
        <div class="flex flex-col gap-1">
            <span class="inline-flex items-center gap-1.5 font-label-sm text-label-sm uppercase tracking-wider text-primary font-semibold">
                <span class="w-1.5 h-1.5 rounded-full bg-primary-container animate-pulse"></span>
                Réseau d'entraide &amp; protection climatique
            </span>
            <p class="font-body-sm text-body-sm text-on-surface-variant">Données confidentielles · utilisées uniquement en cas d'alerte canicule ou de délestage.</p>
        </div>
    </div>

    {{-- Carte formulaire glassmorphism — maquette adaptée au POST Laravel --}}
    <form method="POST" action="{{ route('register') }}" class="rounded-xl bg-surface-container-low/90 backdrop-blur-2xl p-5 shadow-2xl flex flex-col gap-4" x-data="{ role: '{{ $roleParDefaut }}', mode: '{{ $modeParDefaut }}' }">
        @csrf

        @if ($errors->any())
            <div class="rounded-lg bg-error-container text-on-error-container px-3 py-2.5 flex items-start gap-2 font-body-sm text-body-sm">
                <span class="material-symbols-outlined text-[18px] shrink-0 mt-0.5">warning</span>
                <span>Veuillez corriger les champs indiqués ci-dessous pour activer la protection de votre foyer.</span>
            </div>
        @endif

        {{-- Choix du profil : habitant ou gestionnaire de résidence.
             L'admin est créé par l'équipe ChillNet (seeder / back-office), jamais ici. --}}
        <section class="flex flex-col gap-2">
            <div class="flex items-center justify-between">
                <label class="font-label-md text-label-md text-on-surface">Je m'inscris en tant que</label>
                <span class="font-label-sm text-label-sm text-primary uppercase font-semibold">Profil</span>
            </div>
            <div class="grid grid-cols-2 gap-2" role="radiogroup" aria-label="Rôle du compte">
                <label class="flex cursor-pointer items-center gap-2 rounded-lg border p-3 font-body-sm text-body-sm transition-colors" :class="role === 'habitant' ? 'border-primary-container bg-primary-container/10 text-on-surface' : 'border-outline-variant/40 text-on-surface-variant'">
                    <span class="material-symbols-outlined text-[20px]">group</span>
                    <span class="flex flex-col">
                        <span class="font-semibold">Habitant</span>
                        <span class="text-xs opacity-80">Mon foyer, alertes & entraide</span>
                    </span>
                    <input type="radio" name="role" value="habitant" x-model="role" class="sr-only" @change="if (role === 'habitant') { /* tous les modes restent possibles */ }" />
                </label>
                <label class="flex cursor-pointer items-center gap-2 rounded-lg border p-3 font-body-sm text-body-sm transition-colors" :class="role === 'gestionnaire' ? 'border-primary-container bg-primary-container/10 text-on-surface' : 'border-outline-variant/40 text-on-surface-variant'">
                    <span class="material-symbols-outlined text-[20px]">apartment</span>
                    <span class="flex flex-col">
                        <span class="font-semibold">Gestionnaire</span>
                        <span class="text-xs opacity-80">Je gère une résidence</span>
                    </span>
                    <input type="radio" name="role" value="gestionnaire" x-model="role" class="sr-only" @change="if (role === 'gestionnaire' && mode === 'aucune') { mode = '{{ ($residencesDisponibles ?? 0) > 0 ? 'existante' : 'nouvelle' }}' }" />
                </label>
            </div>
            <x-input-error :messages="$errors->get('role')" class="mt-1" />
            <p class="font-body-sm text-body-sm text-on-surface-variant flex items-center gap-1.5">
                <span class="material-symbols-outlined text-[16px] text-primary">info</span>
                <span>Compte administrateur ? Il est créé par l'équipe ChillNet, pas depuis ce formulaire.</span>
            </p>
        </section>

        {{-- Étape 1 maquette : identité du foyer --}}
        <div class="grid grid-cols-1 gap-3">
            <div class="flex flex-col gap-1.5">
                <label for="name" class="font-label-md text-label-md text-on-surface">Nom du foyer</label>
                <div class="relative">
                    <span class="material-symbols-outlined absolute left-3 top-3 text-on-surface-variant text-[20px]">group</span>
                    <input id="name" type="text" name="name" value="{{ old('name') }}" required autofocus autocomplete="name" placeholder="Ex : Famille Dupont"
                        class="w-full bg-surface-container rounded-lg pl-10 pr-4 py-2.5 text-on-surface font-body-md text-body-md placeholder:text-outline-variant focus:outline-none focus:ring-2 focus:ring-primary-container transition-colors" />
                </div>
                <x-input-error :messages="$errors->get('name')" class="mt-1" />
            </div>
            <div class="flex flex-col gap-1.5">
                <label for="email" class="font-label-md text-label-md text-on-surface">Email</label>
                <div class="relative">
                    <span class="material-symbols-outlined absolute left-3 top-3 text-on-surface-variant text-[20px]">mail</span>
                    <input id="email" type="email" name="email" value="{{ old('email') }}" required autocomplete="username" placeholder="vous@exemple.fr"
                        class="w-full bg-surface-container rounded-lg pl-10 pr-4 py-2.5 text-on-surface font-body-md text-body-md placeholder:text-outline-variant focus:outline-none focus:ring-2 focus:ring-primary-container transition-colors" />
                </div>
                <x-input-error :messages="$errors->get('email')" class="mt-1" />
            </div>
        </div>

        {{-- Localisation : logique 3 modes + source quartier (Alpine, équivalente à l'actuelle) --}}
        <section class="flex flex-col gap-3">
            <div class="flex items-center justify-between">
                <label class="font-label-md text-label-md text-on-surface">Localisation &amp; résidence</label>
                <span class="font-label-sm text-label-sm text-primary uppercase font-semibold">Étape 1 — Mon foyer</span>
            </div>
            <p x-show="role === 'gestionnaire'" x-cloak class="rounded-lg bg-primary-container/10 border border-primary-container/30 px-3 py-2.5 font-body-sm text-body-sm text-on-surface flex items-center gap-2">
                <span class="material-symbols-outlined text-[18px] text-primary">apartment</span>
                <span>En tant que gestionnaire, rattachez obligatoirement la résidence que vous gérez (liste ou nouvelle déclaration).</span>
            </p>
            <div class="grid grid-cols-3 gap-2" role="radiogroup" aria-label="Mode de résidence">
                <label class="flex cursor-pointer items-center justify-center gap-1.5 rounded-lg border p-2.5 font-body-sm text-body-sm transition-colors" :class="mode === 'existante' ? 'border-primary-container bg-primary-container/10 text-on-surface' : 'border-outline-variant/40 text-on-surface-variant'">
                    <span class="material-symbols-outlined text-[18px]">location_city</span>
                    <input type="radio" name="residence_mode" value="existante" x-model="mode" class="sr-only" @disabled(($residencesDisponibles ?? 0) === 0) />
                    <span>Liste</span>
                </label>
                <label class="flex cursor-pointer items-center justify-center gap-1.5 rounded-lg border p-2.5 font-body-sm text-body-sm transition-colors" :class="mode === 'nouvelle' ? 'border-primary-container bg-primary-container/10 text-on-surface' : 'border-outline-variant/40 text-on-surface-variant'">
                    <span class="material-symbols-outlined text-[18px]">add_home</span>
                    <input type="radio" name="residence_mode" value="nouvelle" x-model="mode" class="sr-only" />
                    <span>Nouvelle</span>
                </label>
                <label class="flex cursor-pointer items-center justify-center gap-1.5 rounded-lg border p-2.5 font-body-sm text-body-sm transition-colors" :class="mode === 'aucune' ? 'border-primary-container bg-primary-container/10 text-on-surface' : 'border-outline-variant/40 text-on-surface-variant'" x-show="role === 'habitant'">
                    <span class="material-symbols-outlined text-[18px]">schedule</span>
                    <input type="radio" name="residence_mode" value="aucune" x-model="mode" class="sr-only" :disabled="role !== 'habitant'" />
                    <span>Plus tard</span>
                </label>
            </div>
            <x-input-error :messages="$errors->get('residence_mode')" class="mt-1" />

            <div x-show="mode === 'existante'" x-cloak class="flex flex-col gap-1.5">
                @if (($residencesDisponibles ?? 0) > 0)
                    <label for="residence_id" class="font-label-md text-label-md text-on-surface-variant">Sélectionnez votre ensemble résidentiel</label>
                    <div class="relative">
                        <span class="material-symbols-outlined absolute left-3 top-3 text-on-surface-variant text-[20px]">home</span>
                        <select id="residence_id" name="residence_id" :disabled="mode !== 'existante'"
                            class="w-full appearance-none bg-surface-container rounded-lg pl-10 pr-10 py-2.5 text-on-surface font-body-md text-body-md focus:outline-none focus:ring-2 focus:ring-primary-container transition-colors disabled:opacity-50">
                            <option value="">— Choisir ma résidence —</option>
                            @foreach ($quartiersAvecResidences as $quartier)
                                <optgroup label="{{ $quartier->nom }} — {{ $quartier->ville }}">
                                    @foreach ($quartier->residences as $residence)
                                        <option value="{{ $residence->id }}" @selected(old('residence_id') == $residence->id)>{{ $residence->nom }}</option>
                                    @endforeach
                                </optgroup>
                            @endforeach
                        </select>
                        <span class="material-symbols-outlined pointer-events-none absolute right-3 top-2.5 text-on-surface-variant">expand_more</span>
                    </div>
                    <x-input-error :messages="$errors->get('residence_id')" class="mt-1" />
                @else
                    <p class="rounded-lg bg-surface-container px-3 py-2.5 font-body-sm text-body-sm text-on-surface-variant">Aucune résidence listée pour le moment : choisissez « Nouvelle » ou « Plus tard ».</p>
                @endif
            </div>

            <div x-show="mode === 'nouvelle'" x-cloak class="flex flex-col gap-2.5 rounded-lg border border-outline-variant/40 bg-surface-container p-3.5" x-data="{ source: '{{ $quartierSourceParDefaut }}' }">
                <div class="flex flex-col gap-1.5">
                    <label for="nouvelle_residence_nom" class="font-label-md text-label-md text-on-surface">Nom de résidence / voie</label>
                    <div class="relative">
                        <span class="material-symbols-outlined absolute left-3 top-3 text-on-surface-variant text-[20px]">home</span>
                        <input id="nouvelle_residence_nom" type="text" name="nouvelle_residence_nom" value="{{ old('nouvelle_residence_nom') }}" placeholder="Ex : Résidence Les Platanes, Bâtiment C" :disabled="mode !== 'nouvelle'"
                            class="w-full bg-surface-container-low rounded-lg pl-10 pr-4 py-2.5 text-on-surface font-body-md text-body-md placeholder:text-outline-variant focus:outline-none focus:ring-2 focus:ring-primary-container transition-colors disabled:opacity-50" />
                    </div>
                    <x-input-error :messages="$errors->get('nouvelle_residence_nom')" class="mt-1" />
                </div>
                <input type="text" name="nouvelle_residence_adresse" aria-label="Adresse de la nouvelle résidence (optionnel)" value="{{ old('nouvelle_residence_adresse') }}" placeholder="Adresse (optionnel)" :disabled="mode !== 'nouvelle'"
                    class="w-full rounded-lg bg-surface-container-low border border-outline-variant/40 px-3 py-2.5 text-on-surface font-body-md text-body-md placeholder:text-outline-variant focus:outline-none focus:ring-2 focus:ring-primary-container transition-colors disabled:opacity-50" />
                <x-input-error :messages="$errors->get('nouvelle_residence_adresse')" class="mt-1" />

                <div class="flex gap-2 font-body-sm text-body-sm" role="radiogroup" aria-label="Source du quartier">
                    <label class="flex flex-1 cursor-pointer items-center justify-center gap-1.5 rounded-lg border px-2 py-2 transition-colors" :class="source === 'existante' ? 'border-primary-container bg-primary-container/10 text-on-surface' : 'border-outline-variant/40 text-on-surface-variant'">
                        <input type="radio" x-model="source" value="existante" class="sr-only" @disabled(($quartiers ?? collect())->isEmpty()) />
                        <span>Quartier listé</span>
                    </label>
                    <label class="flex flex-1 cursor-pointer items-center justify-center gap-1.5 rounded-lg border px-2 py-2 transition-colors" :class="source === 'nouvelle_entree' ? 'border-primary-container bg-primary-container/10 text-on-surface' : 'border-outline-variant/40 text-on-surface-variant'">
                        <input type="radio" x-model="source" value="nouvelle_entree" class="sr-only" />
                        <span>Nouveau quartier</span>
                    </label>
                </div>

                <div x-show="source === 'existante'" x-cloak class="flex flex-col gap-1.5">
                    <label for="quartier_id" class="font-label-md text-label-md text-on-surface-variant">Commune &amp; quartier</label>
                    <div class="relative">
                        <span class="material-symbols-outlined absolute left-3 top-3 text-on-surface-variant text-[20px]">map</span>
                        <select id="quartier_id" name="quartier_id" :disabled="mode !== 'nouvelle' || source !== 'existante'"
                            class="w-full appearance-none bg-surface-container-low rounded-lg pl-10 pr-10 py-2.5 text-on-surface font-body-md text-body-md focus:outline-none focus:ring-2 focus:ring-primary-container transition-colors disabled:opacity-50">
                            <option value="">— Choisir mon quartier —</option>
                            @foreach (($quartiers ?? collect()) as $quartier)
                                <option value="{{ $quartier->id }}" @selected(old('quartier_id') == $quartier->id)>{{ $quartier->nom }} — {{ $quartier->ville }}</option>
                            @endforeach
                        </select>
                        <span class="material-symbols-outlined pointer-events-none absolute right-3 top-2.5 text-on-surface-variant">expand_more</span>
                    </div>
                    <x-input-error :messages="$errors->get('quartier_id')" class="mt-1" />
                </div>

                <div x-show="source === 'nouvelle_entree'" x-cloak class="grid grid-cols-2 gap-2">
                    <div class="flex flex-col gap-1.5">
                        <input type="text" name="nouveau_quartier_nom" aria-label="Nom du nouveau quartier" value="{{ old('nouveau_quartier_nom') }}" placeholder="Quartier" :disabled="mode !== 'nouvelle' || source !== 'nouvelle_entree'"
                            class="rounded-lg bg-surface-container-low border border-outline-variant/40 px-3 py-2.5 text-on-surface font-body-md text-body-md placeholder:text-outline-variant focus:outline-none focus:ring-2 focus:ring-primary-container transition-colors disabled:opacity-50" />
                        <x-input-error :messages="$errors->get('nouveau_quartier_nom')" class="mt-1" />
                    </div>
                    <div class="flex flex-col gap-1.5">
                        <input type="text" name="nouveau_quartier_ville" aria-label="Ville du nouveau quartier" value="{{ old('nouveau_quartier_ville') }}" placeholder="Ville" :disabled="mode !== 'nouvelle' || source !== 'nouvelle_entree'"
                            class="rounded-lg bg-surface-container-low border border-outline-variant/40 px-3 py-2.5 text-on-surface font-body-md text-body-md placeholder:text-outline-variant focus:outline-none focus:ring-2 focus:ring-primary-container transition-colors disabled:opacity-50" />
                        <x-input-error :messages="$errors->get('nouveau_quartier_ville')" class="mt-1" />
                    </div>
                    <div class="flex flex-col gap-1.5 col-span-2">
                        <input type="text" name="nouveau_quartier_code_postal" aria-label="Code postal du nouveau quartier" value="{{ old('nouveau_quartier_code_postal') }}" placeholder="Code postal" :disabled="mode !== 'nouvelle' || source !== 'nouvelle_entree'"
                            class="rounded-lg bg-surface-container-low border border-outline-variant/40 px-3 py-2.5 text-on-surface font-body-md text-body-md placeholder:text-outline-variant focus:outline-none focus:ring-2 focus:ring-primary-container transition-colors disabled:opacity-50" />
                        <x-input-error :messages="$errors->get('nouveau_quartier_code_postal')" class="mt-1" />
                    </div>
                </div>
            </div>

            <p x-show="mode === 'aucune'" x-cloak class="rounded-lg bg-surface-container px-3 py-2.5 font-body-sm text-body-sm text-on-surface-variant flex items-center gap-2">
                <span class="material-symbols-outlined text-[18px] text-primary">schedule</span>
                <span>Vous pourrez rattacher votre résidence plus tard depuis votre profil.</span>
            </p>
        </section>

        {{-- Mots de passe --}}
        <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
            <div class="flex flex-col gap-1.5">
                <label for="password" class="font-label-md text-label-md text-on-surface">Mot de passe</label>
                <div class="relative">
                    <span class="material-symbols-outlined absolute left-3 top-3 text-on-surface-variant text-[20px]">key</span>
                    <input id="password" type="password" name="password" required autocomplete="new-password" placeholder="••••••••"
                        class="w-full bg-surface-container rounded-lg pl-10 pr-4 py-2.5 text-on-surface font-body-md text-body-md placeholder:text-outline-variant focus:outline-none focus:ring-2 focus:ring-primary-container transition-colors" />
                </div>
                <x-input-error :messages="$errors->get('password')" class="mt-1" />
            </div>
            <div class="flex flex-col gap-1.5">
                <label for="password_confirmation" class="font-label-md text-label-md text-on-surface">Confirmation</label>
                <input id="password_confirmation" type="password" name="password_confirmation" required autocomplete="new-password" placeholder="••••••••"
                    class="w-full bg-surface-container rounded-lg px-3 py-2.5 text-on-surface font-body-md text-body-md placeholder:text-outline-variant focus:outline-none focus:ring-2 focus:ring-primary-container transition-colors" />
                <x-input-error :messages="$errors->get('password_confirmation')" class="mt-1" />
            </div>
        </div>

        <button type="submit" class="w-full px-6 py-3.5 rounded-xl bg-primary-container text-on-primary-container font-title-md text-title-md font-bold flex items-center justify-center gap-2.5 shadow-xl hover:brightness-110 active:scale-[.99] transition-all">
            <span class="material-symbols-outlined text-[22px]">shield</span>
            <span>Activer la protection de mon foyer</span>
        </button>

        <p class="font-body-sm text-body-sm text-on-surface-variant text-center">Vous avez déjà configuré votre logement ? <a href="{{ route('login') }}" class="text-primary-container hover:underline font-semibold inline-flex items-center gap-1">Se connecter <span class="material-symbols-outlined text-sm">open_in_new</span></a></p>
    </form>

    {{-- Télémétrie réseau — version compacte du footer maquette --}}
    <div class="grid grid-cols-2 gap-2.5">
        <div class="p-3 rounded-xl bg-surface-container flex items-center gap-2.5">
            <span class="material-symbols-outlined text-primary text-[20px]">sensors</span>
            <div>
                <p class="font-label-sm text-label-sm text-on-surface-variant">Capteurs actifs</p>
                <p class="font-title-md text-title-md text-on-surface font-bold">1 248 nœuds</p>
            </div>
        </div>
        <div class="p-3 rounded-xl bg-surface-container flex items-center gap-2.5">
            <span class="material-symbols-outlined text-tertiary-container text-[20px]">bolt</span>
            <div>
                <p class="font-label-sm text-label-sm text-on-surface-variant">Tension réseau</p>
                <p class="font-title-md text-title-md text-tertiary-container font-bold">Normal (98.4 %)</p>
            </div>
        </div>
    </div>
</x-guest-layout>
