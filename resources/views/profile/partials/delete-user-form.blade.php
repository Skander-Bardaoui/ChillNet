<section x-data="{ confirmer: @js($errors->userDeletion->isNotEmpty()) }" class="flex flex-col gap-space-sm">
    <header class="flex items-start gap-3">
        <span class="p-2.5 rounded-xl bg-error/10 text-error shrink-0">
            <span class="material-symbols-outlined text-[22px]">delete_forever</span>
        </span>
        <div>
            <h2 class="font-title-md text-title-md text-on-surface">Supprimer le compte</h2>
            <p class="font-body-sm text-body-sm text-on-surface-variant mt-0.5">
                Une fois le compte supprimé, toutes ses ressources et données seront définitivement effacées. Téléchargez au préalable les informations que vous souhaitez conserver.
            </p>
        </div>
    </header>

    <div>
        <button type="button" @click="confirmer = true"
                class="inline-flex items-center gap-2 px-4 py-2 rounded-lg bg-error text-on-error font-label-md text-label-md font-semibold hover:opacity-95 transition">
            <span class="material-symbols-outlined text-[18px]">delete</span>Supprimer mon compte
        </button>
    </div>

    <div x-show="confirmer" x-cloak class="fixed inset-0 z-50 overflow-y-auto px-4 py-10"
         @keydown.escape.window="confirmer = false" role="dialog" aria-modal="true">
        <div class="fixed inset-0 bg-black/60" @click="confirmer = false"></div>

        <div class="relative mx-auto max-w-lg rounded-2xl bg-surface-container-low border border-outline-variant/20 shadow-xl p-space-md">
            <form method="post" action="{{ route('profile.destroy') }}" class="flex flex-col gap-4">
                @csrf
                @method('delete')

                <div>
                    <h3 class="font-title-md text-title-md text-on-surface">Confirmer la suppression du compte</h3>
                    <p class="font-body-sm text-body-sm text-on-surface-variant mt-1">
                        Cette action est irréversible. Saisissez votre mot de passe pour confirmer la suppression définitive de votre compte.
                    </p>
                </div>

                <div class="flex flex-col gap-1">
                    <label for="delete_account_password" class="sr-only">Mot de passe</label>
                    <input id="delete_account_password" name="password" type="password" placeholder="Mot de passe"
                           class="rounded-xl bg-surface-container border border-outline-variant/40 px-3 py-2.5 text-on-surface focus:border-primary-container focus:outline-none" />
                    <x-input-error class="mt-1" :messages="$errors->userDeletion->get('password')" />
                </div>

                <div class="flex items-center justify-end gap-3">
                    <button type="button" @click="confirmer = false"
                            class="px-4 py-2 rounded-lg bg-surface-container-high text-on-surface font-label-md text-label-md hover:bg-surface-variant transition">
                        Annuler
                    </button>
                    <button type="submit"
                            class="inline-flex items-center gap-2 px-4 py-2 rounded-lg bg-error text-on-error font-label-md text-label-md font-semibold hover:opacity-95 transition">
                        Supprimer définitivement
                    </button>
                </div>
            </form>
        </div>
    </div>
</section>
