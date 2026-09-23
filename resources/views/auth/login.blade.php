<x-guest-layout title="Connexion Résident" subtitle="Accédez à votre tableau de bord de foyer et alertes de palier">
    <x-auth-session-status class="mb-1" :status="session('status')" />

    {{-- Bannière alerte canicule — version compacte de la maquette --}}
    <aside aria-label="Alerte Canicule et Urgences" class="relative overflow-hidden rounded-xl bg-error-container text-on-error-container px-4 py-3 shadow-lg shadow-error-container/20">
        <div class="flex flex-wrap items-center gap-x-3 gap-y-2">
            <span class="flex h-2.5 w-2.5 relative shrink-0">
                <span class="animate-ping absolute inline-flex h-full w-full rounded-full bg-error opacity-75"></span>
                <span class="relative inline-flex rounded-full h-2.5 w-2.5 bg-error"></span>
            </span>
            <p class="font-label-md text-label-md font-bold uppercase tracking-wider">Alerte Canicule Niveau 3 <span class="normal-case font-normal tracking-normal opacity-90">— entraide et vigie thermique activées</span></p>
            <div class="flex flex-wrap items-center gap-2 text-sm">
                <a class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-lg bg-surface-container-lowest/40 hover:bg-surface-container-lowest/70 transition-colors font-semibold" href="tel:15">
                    <span class="material-symbols-outlined text-[16px] text-error">emergency</span>
                    <span>SAMU : 15</span>
                </a>
                <a class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-lg bg-surface-container-lowest/40 hover:bg-surface-container-lowest/70 transition-colors" href="tel:0800066666">
                    <span class="material-symbols-outlined text-[16px] text-tertiary-container">support_agent</span>
                    <span>0800 06 66 66</span>
                </a>
            </div>
        </div>
        <span class="material-symbols-outlined absolute -right-3 -bottom-4 opacity-10 text-[90px] pointer-events-none">warning</span>
    </aside>

    {{-- Carte formulaire glassmorphism — maquette, adaptée au POST Laravel --}}
    <section class="rounded-xl bg-surface-container-low/90 backdrop-blur-2xl p-5 shadow-2xl flex flex-col gap-4">
        <div class="flex items-center justify-between gap-3">
            <h2 class="font-headline-sm text-headline-sm text-on-surface">Connexion Résident</h2>
            <span class="inline-flex items-center gap-1.5 px-3 py-1 rounded-full bg-primary/10 text-primary font-label-sm text-label-sm font-semibold uppercase tracking-wider whitespace-nowrap">
                <span class="w-2 h-2 rounded-full bg-primary-container"></span>
                Accès Sécurisé
            </span>
        </div>

        <form method="POST" action="{{ route('login') }}" class="flex flex-col gap-4">
            @csrf
            <div class="flex flex-col gap-1.5">
                <label class="font-label-md text-label-md text-on-surface" for="email">Identifiant ou Email résident</label>
                <div class="relative">
                    <span class="material-symbols-outlined absolute left-3 top-3 text-on-surface-variant text-[20px]">badge</span>
                    <input id="email" name="email" type="email" value="{{ old('email') }}" required autofocus autocomplete="username" placeholder="ex : nom@domaine.fr"
                        class="w-full bg-surface-container rounded-lg pl-10 pr-4 py-2.5 text-on-surface font-body-md text-body-md placeholder:text-outline-variant focus:outline-none focus:ring-2 focus:ring-primary-container transition-colors" />
                </div>
                <x-input-error :messages="$errors->get('email')" class="mt-1" />
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
                    <input id="password" name="password" :type="show ? 'text' : 'password'" required autocomplete="current-password" placeholder="••••••••••••"
                        class="w-full bg-surface-container rounded-lg pl-10 pr-10 py-2.5 text-on-surface font-body-md text-body-md placeholder:text-outline-variant focus:outline-none focus:ring-2 focus:ring-primary-container transition-colors" />
                    <button type="button" @click="show = !show" aria-label="Afficher ou masquer le mot de passe" class="absolute right-3 top-2.5 text-on-surface-variant hover:text-on-surface">
                        <span class="material-symbols-outlined text-[20px]" x-text="show ? 'visibility_off' : 'visibility'">visibility</span>
                    </button>
                </div>
                <x-input-error :messages="$errors->get('password')" class="mt-1" />
            </div>

            <label class="flex items-center gap-2 cursor-pointer select-none">
                <input type="checkbox" name="remember" value="1" {{ old('remember') ? 'checked' : '' }} class="w-4 h-4 rounded bg-surface-container accent-primary-container" />
                <span class="font-body-sm text-body-sm text-on-surface-variant">Garder ma session active sur cet appareil</span>
            </label>

            <button type="submit" class="w-full py-3 px-6 rounded-lg bg-primary-container text-on-primary-container font-title-md text-title-md font-bold hover:brightness-110 active:brightness-95 transition-all flex items-center justify-center gap-2 shadow-lg shadow-primary-container/20">
                <span>Se connecter à mon espace foyer</span>
                <span class="material-symbols-outlined text-[20px]">arrow_forward</span>
            </button>
        </form>

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

    {{-- Télémétrie compacte — ancien bouton alert() devenu vrai lien --}}
    <section class="rounded-xl bg-surface-container-low/60 backdrop-blur-md p-3.5 flex flex-col gap-2.5">
        <div class="flex items-center justify-between gap-3">
            <div class="flex items-center gap-2">
                <span class="material-symbols-outlined text-primary text-[20px]">sensors</span>
                <p class="font-body-sm text-body-sm text-on-surface-variant"><span class="text-on-surface font-semibold">39.5°C ressentis</span> · pic prévu 16h30</p>
            </div>
            <span class="font-label-sm text-label-sm text-primary font-mono tracking-wider shrink-0">EN DIRECT</span>
        </div>
        <div class="bg-surface-container rounded-lg p-3 flex items-center justify-between gap-3">
            <div class="flex items-center gap-2.5">
                <div class="w-9 h-9 shrink-0 rounded-lg bg-surface-container-high flex items-center justify-center text-primary-container">
                    <span class="material-symbols-outlined">ac_unit</span>
                </div>
                <p class="font-body-sm text-body-sm text-on-surface-variant"><span class="text-on-surface font-semibold">4 îlots de fraîcheur ouverts</span> · Médiathèque, Gymnase Sud (&lt; 500&nbsp;m)</p>
            </div>
            <a href="{{ route('home') }}#ilos-fraicheur" class="shrink-0 px-3 py-1.5 rounded-lg bg-surface-container-highest hover:bg-surface-bright text-primary font-label-md text-label-md transition-colors whitespace-nowrap">Voir la carte</a>
        </div>
    </section>
</x-guest-layout>
