<x-guest-layout title="Nouveau mot de passe" subtitle="Choisissez un mot de passe long et aléatoire pour sécuriser votre compte.">
    <section class="rounded-xl bg-surface-container-low/90 backdrop-blur-2xl p-5 shadow-2xl flex flex-col gap-4">
        <form method="POST" action="{{ route('password.store') }}" class="flex flex-col gap-4">
            @csrf
            <input type="hidden" name="token" value="{{ $request->route('token') }}">

            <div class="flex flex-col gap-1.5">
                <label class="font-label-md text-label-md text-on-surface" for="email">Adresse e-mail</label>
                <div class="relative">
                    <span class="material-symbols-outlined absolute left-3 top-3 text-on-surface-variant text-[20px]">mail</span>
                    <input id="email" name="email" type="email" value="{{ old('email', $request->email) }}" required autofocus autocomplete="username"
                           class="w-full bg-surface-container rounded-lg pl-10 pr-4 py-2.5 text-on-surface font-body-md text-body-md placeholder:text-outline-variant focus:outline-none focus:ring-2 focus:ring-primary-container transition-colors" />
                </div>
                <x-input-error :messages="$errors->get('email')" class="mt-1" />
            </div>

            <div class="flex flex-col gap-1.5">
                <label class="font-label-md text-label-md text-on-surface" for="password">Nouveau mot de passe</label>
                <div class="relative">
                    <span class="material-symbols-outlined absolute left-3 top-3 text-on-surface-variant text-[20px]">key</span>
                    <input id="password" name="password" type="password" required autocomplete="new-password"
                           class="w-full bg-surface-container rounded-lg pl-10 pr-4 py-2.5 text-on-surface font-body-md text-body-md placeholder:text-outline-variant focus:outline-none focus:ring-2 focus:ring-primary-container transition-colors" />
                </div>
                <x-input-error :messages="$errors->get('password')" class="mt-1" />
            </div>

            <div class="flex flex-col gap-1.5">
                <label class="font-label-md text-label-md text-on-surface" for="password_confirmation">Confirmation du mot de passe</label>
                <div class="relative">
                    <span class="material-symbols-outlined absolute left-3 top-3 text-on-surface-variant text-[20px]">key</span>
                    <input id="password_confirmation" name="password_confirmation" type="password" required autocomplete="new-password"
                           class="w-full bg-surface-container rounded-lg pl-10 pr-4 py-2.5 text-on-surface font-body-md text-body-md placeholder:text-outline-variant focus:outline-none focus:ring-2 focus:ring-primary-container transition-colors" />
                </div>
                <x-input-error :messages="$errors->get('password_confirmation')" class="mt-1" />
            </div>

            <button type="submit" class="w-full py-3 px-6 rounded-lg bg-primary-container text-on-primary-container font-title-md text-title-md font-bold hover:brightness-110 active:brightness-95 transition-all flex items-center justify-center gap-2 shadow-lg shadow-primary-container/20">
                <span>Réinitialiser le mot de passe</span>
                <span class="material-symbols-outlined text-[20px]">lock_reset</span>
            </button>
        </form>

        <p class="font-body-sm text-body-sm text-on-surface-variant text-center">
            <a href="{{ route('login') }}" class="text-primary hover:underline">Revenir à la connexion</a>
        </p>
    </section>
</x-guest-layout>