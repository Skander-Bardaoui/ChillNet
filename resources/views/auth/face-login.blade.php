@php($faceDisponible = config('services.faceplusplus.enabled') && filled(config('services.faceplusplus.key')))

<x-guest-layout title="Connexion par visage" subtitle="Saisissez votre email, puis scannez votre visage : aucun mot de passe n'est nécessaire.">
    <x-auth-session-status class="mb-1" :status="session('status')" />

    <section class="rounded-xl bg-surface-container-low/90 backdrop-blur-2xl p-5 shadow-2xl flex flex-col gap-4">
        <div class="flex items-center justify-between gap-3">
            <h2 class="font-headline-sm text-headline-sm text-on-surface">Connexion par visage</h2>
            <span class="inline-flex items-center gap-1.5 px-3 py-1 rounded-full bg-primary/10 text-primary font-label-sm text-label-sm font-semibold uppercase tracking-wider whitespace-nowrap">
                <span class="w-2 h-2 rounded-full bg-primary-container"></span>
                Face ID
            </span>
        </div>

        @if (! $faceDisponible)
            <div class="rounded-lg bg-amber-50 border border-amber-300/60 text-amber-900 px-3 py-2.5 flex items-start gap-2">
                <span class="material-symbols-outlined text-[20px]">info</span>
                <p class="font-body-sm text-body-sm">La connexion par visage n'est pas disponible sur cette installation. Utilisez votre mot de passe.</p>
            </div>
        @else
            <div class="rounded-lg bg-surface-container border border-outline-variant/30 px-3 py-2.5 flex items-start gap-2">
                <span class="material-symbols-outlined text-[20px] text-primary">info</span>
                <p class="font-body-sm text-body-sm text-on-surface-variant">
                    Première utilisation ? Connectez-vous d'abord avec votre mot de passe, puis activez
                    « Connexion par visage (Face ID) » depuis <strong class="text-on-surface">votre profil</strong>
                    (menu Paramètres du profil). Le scan ne fonctionnera qu'après cet enrôlement.
                </p>
            </div>

            <form method="POST" action="{{ route('login.face.store') }}" x-data="faceCapture"
                  @submit="if (! validate()) { $event.preventDefault(); cameraError = 'Activez la caméra puis capturez votre visage avant de valider.'; }"
                  class="flex flex-col gap-4">
                @csrf

                <div class="flex flex-col gap-1.5">
                    <label class="font-label-md text-label-md text-on-surface" for="email">Email du compte</label>
                    <div class="relative">
                        <span class="material-symbols-outlined absolute left-3 top-3 text-on-surface-variant text-[20px]">badge</span>
                        <input id="email" name="email" type="email" value="{{ old('email') }}" required autofocus autocomplete="username"
                               placeholder="ex : nom@domaine.fr"
                               class="w-full bg-surface-container rounded-lg pl-10 pr-4 py-2.5 text-on-surface font-body-md text-body-md placeholder:text-outline-variant focus:outline-none focus:ring-2 focus:ring-primary-container transition-colors" />
                    </div>
                    <x-input-error :messages="$errors->get('email')" class="mt-1" />
                </div>

                <div class="flex flex-col gap-2">
                    <span class="font-label-md text-label-md text-on-surface">Scannez votre visage</span>

                    <x-face-capture />
                </div>

                <label class="flex items-center gap-2 cursor-pointer select-none">
                    <input type="checkbox" name="remember" value="1" {{ old('remember') ? 'checked' : '' }} class="w-4 h-4 rounded bg-surface-container accent-primary-container" />
                    <span class="font-body-sm text-body-sm text-on-surface-variant">Garder ma session active sur cet appareil</span>
                </label>

                <button type="submit" :disabled="! captured"
                        class="w-full py-3 px-6 rounded-lg bg-primary-container text-on-primary-container font-title-md text-title-md font-bold hover:brightness-110 active:brightness-95 disabled:opacity-50 disabled:cursor-not-allowed transition-all flex items-center justify-center gap-2 shadow-lg shadow-primary-container/20">
                    <span class="material-symbols-outlined text-[20px]">face</span>
                    <span>Me connecter avec mon visage</span>
                </button>

                <p class="font-body-sm text-body-sm text-on-surface-variant text-center">
                    Votre capture est comparée à l'image de référence enregistrée depuis votre profil.
                    La caméra exige une connexion sécurisée (HTTPS ou localhost).
                </p>
            </form>
        @endif

        <div class="flex items-center gap-3">
            <span class="h-px flex-1 bg-outline-variant/40"></span>
            <span class="font-label-sm text-label-sm text-on-surface-variant uppercase tracking-wider">ou</span>
            <span class="h-px flex-1 bg-outline-variant/40"></span>
        </div>

        <a href="{{ route('login') }}"
           class="w-full py-2.5 px-4 rounded-lg bg-surface-container-high hover:bg-surface-variant text-on-surface font-label-md text-label-md font-semibold transition-colors flex items-center justify-center gap-2">
            <span class="material-symbols-outlined text-[20px]">password</span>
            Utiliser mon mot de passe
        </a>
    </section>
</x-guest-layout>
