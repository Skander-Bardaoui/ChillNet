<x-guest-layout title="Connexion" subtitle="Un seul formulaire pour tous : habitant, gestionnaire ou admin — vous êtes redirigé vers votre espace automatiquement">
    <x-auth-session-status class="mb-1" :status="session('status')" />

    {{-- Bannière d'alerte active (issue de la base) — version compacte de la maquette --}}
    @if ($alerteLogin)
    <aside aria-label="Alerte canicule et urgences" class="relative overflow-hidden rounded-xl bg-error-container text-on-error-container border border-error/20 px-4 py-3 shadow-lg shadow-error-container/20">
        <div class="flex flex-wrap items-center gap-x-3 gap-y-2">
            <span class="flex h-2.5 w-2.5 relative shrink-0">
                <span class="animate-ping absolute inline-flex h-full w-full rounded-full bg-error opacity-75"></span>
                <span class="relative inline-flex rounded-full h-2.5 w-2.5 bg-error"></span>
            </span>
            <p class="font-label-md text-label-md font-bold uppercase tracking-wider">{{ $alerteLogin->niveau?->label() ?? 'Alerte canicule' }} <span class="normal-case font-normal tracking-normal opacity-90">— {{ $alerteLogin->titre }}</span></p>
            <div class="flex flex-wrap items-center gap-2 text-sm">
                <a class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-lg bg-on-error-container/10 hover:bg-on-error-container/20 border border-on-error-container/15 transition-colors font-semibold" href="tel:190">
                    <span class="material-symbols-outlined text-[16px] text-error">emergency</span>
                    <span>SAMU : 190</span>
                </a>
                <a class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-lg bg-on-error-container/10 hover:bg-on-error-container/20 border border-on-error-container/15 transition-colors font-semibold" href="tel:198">
                    <span class="material-symbols-outlined text-[16px] text-error">local_fire_department</span>
                    <span>Protection Civile : 198</span>
                </a>
            </div>
        </div>
    </aside>
    @endif

    {{-- Carte formulaire glassmorphism — maquette, adaptée au POST Laravel --}}
    <section class="rounded-xl bg-surface-container-low/90 backdrop-blur-2xl p-5 shadow-2xl flex flex-col gap-4">
        <div class="flex items-center justify-between gap-3">
            <h2 class="font-headline-sm text-headline-sm text-on-surface">Connexion</h2>
            <span class="inline-flex items-center gap-1.5 px-3 py-1 rounded-full bg-primary/10 text-primary font-label-sm text-label-sm font-semibold uppercase tracking-wider whitespace-nowrap">
                <span class="w-2 h-2 rounded-full bg-primary-container"></span>
                Accès Sécurisé
            </span>
        </div>

        <form id="form-connexion" method="POST" action="{{ route('login') }}" novalidate class="flex flex-col gap-4"
            x-data="connexion()" @submit="soumettre($event)">
            @csrf
            <div class="flex flex-col gap-1.5">
                <label class="font-label-md text-label-md text-on-surface" for="email">Identifiant ou Email résident</label>
                <div class="relative">
                    <span class="material-symbols-outlined absolute left-3 top-3 text-on-surface-variant text-[20px]">badge</span>
                    <input id="email" name="email" type="email" value="{{ old('email') }}" x-model="email" required autocomplete="username" placeholder="ex : nom@domaine.fr"
                        :aria-invalid="erreurs.email ? 'true' : 'false'"
                        :class="erreurs.email ? 'border border-error ring-2 ring-error' : ''"
                        class="w-full bg-surface-container rounded-lg pl-10 pr-4 py-2.5 text-on-surface font-body-md text-body-md placeholder:text-outline-variant focus:outline-none focus:ring-2 focus:ring-primary-container transition-colors" />
                </div>
                <x-input-error :messages="$errors->get('email')" class="mt-1" />
                <x-client-error champ="email" class="mt-1" />
            </div>

            <div class="flex flex-col gap-1.5">
                <div class="flex items-center justify-between gap-2">
                    <label class="font-label-md text-label-md text-on-surface" for="password">Mot de passe</label>
                    @if (Route::has('password.request'))
                        <a class="font-body-sm text-body-sm text-primary hover:underline" href="{{ route('password.request') }}">Mot de passe oublié ?</a>
                    @endif
                </div>
                <div class="relative" x-data="{ show: false }">
                    <span class="material-symbols-outlined absolute left-3 top-3 text-on-surface-variant text-[20px]">key</span>
                    <input id="password" name="password" :type="show ? 'text' : 'password'" x-model="mdp" required autocomplete="current-password" placeholder="••••••••••••"
                        :aria-invalid="erreurs.mdp ? 'true' : 'false'"
                        :class="erreurs.mdp ? 'border border-error ring-2 ring-error' : ''"
                        class="w-full bg-surface-container rounded-lg pl-10 pr-10 py-2.5 text-on-surface font-body-md text-body-md placeholder:text-outline-variant focus:outline-none focus:ring-2 focus:ring-primary-container transition-colors" />
                    <button type="button" @click="show = !show" aria-label="Afficher ou masquer le mot de passe" class="absolute right-3 top-2.5 text-on-surface-variant hover:text-on-surface">
                        <span class="material-symbols-outlined text-[20px]" x-text="show ? 'visibility_off' : 'visibility'">visibility</span>
                    </button>
                </div>
                <x-input-error :messages="$errors->get('password')" class="mt-1" />
                <x-client-error champ="mdp" class="mt-1" />
            </div>

            <label class="flex items-center gap-2 cursor-pointer select-none">
                <input type="checkbox" name="remember" value="1" {{ old('remember') ? 'checked' : '' }} class="w-4 h-4 rounded bg-surface-container accent-primary-container" />
                <span class="font-body-sm text-body-sm text-on-surface-variant">Garder ma session active sur cet appareil</span>
            </label>

            <button type="submit" class="w-full py-3 px-6 rounded-lg bg-primary-container text-on-primary-container font-title-md text-title-md font-bold hover:brightness-110 active:brightness-95 transition-all flex items-center justify-center gap-2 shadow-lg shadow-primary-container/20">
                <span>Se connecter à mon espace</span>
                <span class="material-symbols-outlined text-[20px]">arrow_forward</span>
            </button>
            <p class="font-body-sm text-body-sm text-on-surface-variant text-center">Habitant → tableau de bord foyer · Gestionnaire / Admin → espace de gestion (redirection automatique).</p>
        </form>

        @if (config('services.faceplusplus.enabled') && filled(config('services.faceplusplus.key')))
            <div class="flex items-center gap-3">
                <span class="h-px flex-1 bg-outline-variant/40"></span>
                <span class="font-label-sm text-label-sm text-on-surface-variant uppercase tracking-wider">ou</span>
                <span class="h-px flex-1 bg-outline-variant/40"></span>
            </div>

            <a href="{{ route('login.face') }}"
               class="w-full py-2.5 px-4 rounded-lg bg-surface-container-high hover:bg-surface-variant text-on-surface font-label-md text-label-md font-semibold transition-colors flex items-center justify-center gap-2">
                <span class="material-symbols-outlined text-[20px]">face</span>
                Se connecter avec mon visage
            </a>
        @endif

        {{-- Bloc entraide — ancien bouton alert() devenu vrai lien --}}
        <div class="flex flex-col sm:flex-row items-center justify-between gap-3 bg-surface-container-lowest/50 p-3.5 rounded-lg">
            <div class="flex items-center gap-3">
                <div class="w-8 h-8 shrink-0 rounded-full bg-tertiary-container/20 flex items-center justify-center text-tertiary-container">
                    <span class="material-symbols-outlined text-[20px]">group_add</span>
                </div>
                <div>
                    <p class="font-label-md text-label-md text-on-surface font-semibold">Nouveau dans la résidence ?</p>
                    <p class="font-body-sm text-body-sm text-on-surface-variant">Rejoignez le registre d'entraide et recevez les SMS de crise</p>
                </div>
            </div>
            <a href="{{ route('register') }}" class="px-4 py-2 rounded-lg bg-surface-container-high hover:bg-surface-bright text-primary font-label-md text-label-md font-semibold transition-colors whitespace-nowrap w-full sm:w-auto text-center">Créer un compte</a>
        </div>
    </section>

    {{-- État du réseau — données issues de la base --}}
    <section class="rounded-xl bg-surface-container-low/60 backdrop-blur-md p-3.5 flex flex-col gap-2.5">
        <div class="flex items-center justify-between gap-3">
            <div class="flex items-center gap-2">
                <span class="material-symbols-outlined text-primary text-[20px]">sensors</span>
                @if ($alerteLogin && $alerteLogin->temperature_actuelle !== null)
                    <p class="font-body-sm text-body-sm text-on-surface-variant"><span class="text-on-surface font-semibold">{{ number_format((float) $alerteLogin->temperature_actuelle, 1, ',', '') }}°C</span> · {{ $alerteLogin->niveau?->label() ?? 'Alerte en cours' }}</p>
                @else
                    <p class="font-body-sm text-body-sm text-on-surface-variant"><span class="text-on-surface font-semibold">Aucune alerte active</span> · réseau sous surveillance</p>
                @endif
            </div>
            <span class="font-label-sm text-label-sm text-primary font-mono tracking-wider shrink-0">{{ $alerteLogin ? 'ALERTE ACTIVE' : 'VEILLE' }}</span>
        </div>
        @if ($pointsFraicheurLogin->isNotEmpty())
        <div class="bg-surface-container rounded-lg p-3 flex items-center justify-between gap-3">
            <div class="flex items-center gap-2.5">
                <div class="w-9 h-9 shrink-0 rounded-lg bg-surface-container-high flex items-center justify-center text-primary-container">
                    <span class="material-symbols-outlined">ac_unit</span>
                </div>
                <p class="font-body-sm text-body-sm text-on-surface-variant"><span class="text-on-surface font-semibold">{{ $pointsFraicheurLogin->count() }} point(s) de fraîcheur ouverts</span> · {{ $pointsFraicheurLogin->take(2)->pluck('nom')->implode(', ') }}</p>
            </div>
            <a href="{{ route('refuges.index') }}" class="shrink-0 px-3 py-1.5 rounded-lg bg-surface-container-highest hover:bg-surface-bright text-primary font-label-md text-label-md transition-colors whitespace-nowrap">Voir la carte</a>
        </div>
        @endif
    </section>

    <script>
    function connexion() {
        return {
            email: @json(old('email', '')),
            mdp: '',
            erreurs: {},
            soumettre(evenement) {
                const erreurs = {};
                const email = this.email.trim();

                if (! email) {
                    erreurs.email = 'L\'adresse e-mail est obligatoire.';
                } else if (! /^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email)) {
                    erreurs.email = 'L\'adresse e-mail saisie n\'est pas valide.';
                }

                if (! this.mdp) {
                    erreurs.mdp = 'Le mot de passe est obligatoire.';
                }

                this.erreurs = erreurs;

                if (Object.keys(erreurs).length > 0) {
                    evenement.preventDefault();
                    const premier = document.querySelector('#form-connexion [aria-invalid="true"]');
                    if (premier) {
                        premier.focus();
                    }
                }
            },
        };
    }
    </script>
</x-guest-layout>
