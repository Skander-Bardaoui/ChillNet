<section class="flex flex-col gap-space-sm">
    <header class="flex items-start gap-3">
        <span class="p-2.5 rounded-xl bg-primary/10 text-primary shrink-0">
            <span class="material-symbols-outlined text-[22px]">lock</span>
        </span>
        <div>
            <h2 class="font-title-md text-title-md text-on-surface">Mot de passe</h2>
            <p class="font-body-sm text-body-sm text-on-surface-variant mt-0.5">Utilisez un mot de passe long et aléatoire pour sécuriser votre compte.</p>
        </div>
    </header>

    <form method="post" action="{{ route('password.update') }}" class="flex flex-col gap-4">
        @csrf
        @method('put')

        <div class="flex flex-col gap-1">
            <label for="update_password_current_password" class="font-label-sm text-label-sm text-on-surface-variant">Mot de passe actuel</label>
            <input id="update_password_current_password" name="current_password" type="password" autocomplete="current-password"
                   class="rounded-xl bg-surface-container border border-outline-variant/40 px-3 py-2.5 text-on-surface focus:border-primary-container focus:outline-none" />
            <x-input-error class="mt-1" :messages="$errors->updatePassword->get('current_password')" />
        </div>

        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
            <div class="flex flex-col gap-1">
                <label for="update_password_password" class="font-label-sm text-label-sm text-on-surface-variant">Nouveau mot de passe</label>
                <input id="update_password_password" name="password" type="password" autocomplete="new-password"
                       class="rounded-xl bg-surface-container border border-outline-variant/40 px-3 py-2.5 text-on-surface focus:border-primary-container focus:outline-none" />
                <x-input-error class="mt-1" :messages="$errors->updatePassword->get('password')" />
            </div>

            <div class="flex flex-col gap-1">
                <label for="update_password_password_confirmation" class="font-label-sm text-label-sm text-on-surface-variant">Confirmation</label>
                <input id="update_password_password_confirmation" name="password_confirmation" type="password" autocomplete="new-password"
                       class="rounded-xl bg-surface-container border border-outline-variant/40 px-3 py-2.5 text-on-surface focus:border-primary-container focus:outline-none" />
                <x-input-error class="mt-1" :messages="$errors->updatePassword->get('password_confirmation')" />
            </div>
        </div>

        <div class="flex items-center gap-3">
            <x-primary-button>Enregistrer</x-primary-button>

            @if (session('status') === 'password-updated')
                <p x-data="{ show: true }" x-show="show" x-transition x-init="setTimeout(() => show = false, 2500)"
                   class="font-body-sm text-body-sm text-green-700">Enregistré.</p>
            @endif
        </div>
    </form>
</section>
