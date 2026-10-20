@php($user = $user ?? auth()->user())

<section class="flex flex-col gap-space-sm" x-data="{ apercu: null }">
    <header class="flex items-start gap-3">
        <span class="p-2.5 rounded-xl bg-primary/10 text-primary shrink-0">
            <span class="material-symbols-outlined text-[22px]">account_circle</span>
        </span>
        <div>
            <h2 class="font-title-md text-title-md text-on-surface">Photo de profil</h2>
            <p class="font-body-sm text-body-sm text-on-surface-variant mt-0.5">Formats JPG, PNG ou WEBP — 2&nbsp;Mo maximum.</p>
        </div>
    </header>

    <div class="flex flex-col sm:flex-row items-start sm:items-center gap-4">
        <div class="h-20 w-20 rounded-full overflow-hidden bg-primary/10 flex items-center justify-center shrink-0 border border-outline-variant/30">
            <template x-if="apercu">
                <img :src="apercu" alt="Aperçu de la nouvelle photo" class="h-20 w-20 object-cover" />
            </template>
            @if ($user->avatarUrl())
                <img x-show="! apercu" src="{{ $user->avatarUrl() }}" alt="Photo de profil" class="h-20 w-20 object-cover" />
            @else
                <span x-show="! apercu" class="font-headline-sm text-headline-sm text-primary font-semibold">{{ $user->initials() }}</span>
            @endif
        </div>

        <div class="flex flex-col gap-3">
            <form method="post" action="{{ route('profile.avatar.update') }}" enctype="multipart/form-data" class="flex flex-wrap items-center gap-3">
                @csrf
                @method('patch')
                <label for="avatar" class="sr-only">Choisir une photo de profil</label>
                <input id="avatar" type="file" name="avatar" accept="image/png,image/jpeg,image/webp"
                       @change="const f = $event.target.files[0]; if (f) { const r = new FileReader(); r.onload = e => apercu = e.target.result; r.readAsDataURL(f); }"
                       class="block text-body-sm text-on-surface-variant file:mr-3 file:rounded-lg file:border-0 file:bg-primary/10 file:px-3 file:py-2 file:font-label-md file:text-primary hover:file:bg-primary/20 cursor-pointer" />
                <x-primary-button>Enregistrer la photo</x-primary-button>
            </form>

            @if ($user->avatarUrl())
                <form method="post" action="{{ route('profile.avatar.destroy') }}">
                    @csrf
                    @method('delete')
                    <button type="submit" class="font-body-sm text-body-sm text-error hover:underline">Supprimer la photo</button>
                </form>
            @endif
        </div>
    </div>

    <x-input-error :messages="$errors->avatarUpdate->get('avatar')" />

    @if (session('status') === 'avatar-updated')
        <p class="font-body-sm text-body-sm text-green-700">Photo de profil mise à jour.</p>
    @elseif (session('status') === 'avatar-removed')
        <p class="font-body-sm text-body-sm text-green-700">Photo de profil supprimée.</p>
    @endif
</section>