<x-guest-layout title="Vérification en deux étapes" subtitle="Votre mot de passe est correct. Confirmez avec votre application d'authentification pour terminer la connexion.">
    <section x-data="{ mode: 'code' }" class="rounded-xl bg-surface-container-low/90 backdrop-blur-2xl p-5 shadow-2xl flex flex-col gap-4">
        <div class="flex items-center gap-3">
            <span class="p-2.5 rounded-xl bg-primary/10 text-primary shrink-0">
                <span class="material-symbols-outlined text-[24px]">shield_person</span>
            </span>
            <div>
                <h2 class="font-headline-sm text-headline-sm text-on-surface">Vérification en deux étapes</h2>
                <p class="font-body-sm text-body-sm text-on-surface-variant mt-0.5" x-show="mode === 'code'">
                    Ouvrez votre application d'authentification, puis saisissez le code à 6 chiffres affiché.
                </p>
                <p class="font-body-sm text-body-sm text-on-surface-variant mt-0.5" x-show="mode === 'recovery'" x-cloak>
                    Saisissez l'un de vos codes de récupération (usage unique).
                </p>
            </div>
        </div>

        <form method="POST" action="{{ route('two-factor.challenge') }}" class="flex flex-col gap-4">
            @csrf

            <div class="flex flex-col gap-1.5" x-show="mode === 'code'">
                <label class="font-label-md text-label-md text-on-surface" for="two_factor_challenge_code">Code à 6 chiffres</label>
                <div class="relative">
                    <span class="material-symbols-outlined absolute left-3 top-3 text-on-surface-variant text-[20px]">pin</span>
                    <input id="two_factor_challenge_code" name="code" type="text" inputmode="numeric" autocomplete="one-time-code" maxlength="6" autofocus
                           :disabled="mode !== 'code'" placeholder="123456"
                           class="w-full bg-surface-container rounded-lg pl-10 pr-4 py-2.5 text-on-surface font-body-md text-body-md tracking-[0.35em] placeholder:tracking-normal placeholder:text-outline-variant focus:outline-none focus:ring-2 focus:ring-primary-container transition-colors" />
                </div>
            </div>

            <div class="flex flex-col gap-1.5" x-show="mode === 'recovery'" x-cloak>
                <label class="font-label-md text-label-md text-on-surface" for="two_factor_challenge_recovery">Code de récupération</label>
                <div class="relative">
                    <span class="material-symbols-outlined absolute left-3 top-3 text-on-surface-variant text-[20px]">vpn_key</span>
                    <input id="two_factor_challenge_recovery" name="recovery_code" type="text" autocomplete="off"
                           :disabled="mode !== 'recovery'" placeholder="XXXXX-XXXXX"
                           class="w-full bg-surface-container rounded-lg pl-10 pr-4 py-2.5 text-on-surface font-mono text-body-md placeholder:text-outline-variant focus:outline-none focus:ring-2 focus:ring-primary-container transition-colors" />
                </div>

                @if ($codesRestants > 0)
                    <p class="font-body-sm text-body-sm text-on-surface-variant">
                        Il vous reste <span class="font-semibold text-on-surface">{{ $codesRestants }}</span> code(s) de récupération.
                    </p>
                @else
                    <p class="font-body-sm text-body-sm text-red-700">
                        Vous n'avez plus de code de récupération. Si vous n'avez plus accès à votre appareil, contactez le gestionnaire de la résidence pour réinitialiser votre accès.
                    </p>
                @endif
            </div>

            <x-input-error :messages="$errors->get('code')" />

            <button type="submit" class="w-full py-3 px-6 rounded-lg bg-primary-container text-on-primary-container font-title-md text-title-md font-bold hover:brightness-110 active:brightness-95 transition-all flex items-center justify-center gap-2 shadow-lg shadow-primary-container/20">
                <span>Vérifier et me connecter</span>
                <span class="material-symbols-outlined text-[20px]">arrow_forward</span>
            </button>
        </form>

        <div class="rounded-lg bg-surface-container/60 px-3 py-2.5 flex items-start gap-2">
            <span class="material-symbols-outlined text-[18px] text-primary shrink-0">lightbulb</span>
            <p class="font-body-sm text-body-sm text-on-surface-variant">
                Le code change toutes les 30 secondes. Si votre application affiche un code refusé, vérifiez que l'heure de votre téléphone est réglée automatiquement.
            </p>
        </div>

        <div class="flex flex-col sm:flex-row items-center justify-between gap-2 border-t border-outline-variant/20 pt-3">
            <button type="button" @click="mode = mode === 'code' ? 'recovery' : 'code'"
                    class="font-body-sm text-body-sm text-primary hover:underline"
                    x-text="mode === 'code' ? 'Vous avez perdu votre appareil ? Utiliser un code de récupération' : 'Revenir au code à 6 chiffres'">
                Vous avez perdu votre appareil ? Utiliser un code de récupération
            </button>
            <a href="{{ route('login') }}" class="font-body-sm text-body-sm text-on-surface-variant hover:underline">Revenir à la connexion</a>
        </div>
    </section>
</x-guest-layout>