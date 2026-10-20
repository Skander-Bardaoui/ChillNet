<x-guest-layout title="Confirmer votre mot de passe" subtitle="Zone sécurisée : confirmez votre mot de passe pour continuer.">
    <section class="rounded-xl bg-surface-container-low/90 backdrop-blur-2xl p-5 shadow-2xl flex flex-col gap-4">
        <p class="font-body-sm text-body-sm text-on-surface-variant">
            Cette action est sensible. Pour des raisons de sécurité, veuillez confirmer votre mot de passe avant de poursuivre.
        </p>

        <form method="POST" action="{{ route('password.confirm') }}" class="flex flex-col gap-4">
            @csrf

            <div class="flex flex-col gap-1.5">
                <label class="font-label-md text-label-md text-on-surface" for="password">Mot de passe</label>
                <div class="relative">
                    <span class="material-symbols-outlined absolute left-3 top-3 text-on-surface-variant text-[20px]">key</span>
                    <input id="password" name="password" type="password" required autocomplete="current-password"
                           class="w-full bg-surface-container rounded-lg pl-10 pr-4 py-2.5 text-on-surface font-body-md text-body-md placeholder:text-outline-variant focus:outline-none focus:ring-2 focus:ring-primary-container transition-colors" />
                </div>
                <x-input-error :messages="$errors->get('password')" class="mt-1" />
            </div>

            <button type="submit" class="w-full py-3 px-6 rounded-lg bg-primary-container text-on-primary-container font-title-md text-title-md font-bold hover:brightness-110 active:brightness-95 transition-all flex items-center justify-center gap-2 shadow-lg shadow-primary-container/20">
                <span>Confirmer</span>
                <span class="material-symbols-outlined text-[20px]">lock_open</span>
            </button>
        </form>
    </section>
</x-guest-layout>