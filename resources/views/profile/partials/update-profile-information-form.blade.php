@php
    $user = $user ?? auth()->user();
    $profilsSelectionnes = old('profil_vulnerabilites', $user->profilsVulnerabilite());
    $profilsSelectionnes = array_map(fn ($p) => $p instanceof \App\Enums\ProfilVulnerabilite ? $p->value : (string) $p, $profilsSelectionnes);
@endphp

<section class="flex flex-col gap-space-sm">
    <header class="flex items-start gap-3">
        <span class="p-2.5 rounded-xl bg-primary/10 text-primary shrink-0">
            <span class="material-symbols-outlined text-[22px]">badge</span>
        </span>
        <div>
            <h2 class="font-title-md text-title-md text-on-surface">Informations personnelles</h2>
            <p class="font-body-sm text-body-sm text-on-surface-variant mt-0.5">Mettez à jour votre nom et votre adresse e-mail.</p>
        </div>
    </header>

    {{-- Contexte du compte : rôle applicatif (et résidence pour les foyers rattachés). --}}
    <div class="rounded-xl bg-surface-container border border-outline-variant/30 px-3 py-2.5 flex items-center gap-2 flex-wrap">
        <span class="material-symbols-outlined text-primary text-[20px]">verified_user</span>
        <span class="font-label-sm text-label-sm text-on-surface-variant">Rôle</span>
        <span class="font-label-md text-label-md text-on-surface">{{ $user->role?->label() ?? '—' }}</span>
        @if ($user->residence)
            <span class="text-outline-variant">·</span>
            <span class="material-symbols-outlined text-primary text-[20px]">home_work</span>
            <span class="font-label-sm text-label-sm text-on-surface-variant">Résidence</span>
            <span class="font-label-md text-label-md text-on-surface">{{ $user->residence->nom }}</span>
        @endif
    </div>

    <form id="send-verification" method="post" action="{{ route('verification.send') }}">
        @csrf
    </form>

    <form method="post" action="{{ route('profile.update') }}" class="flex flex-col gap-4">
        @csrf
        @method('patch')

        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
            <div class="flex flex-col gap-1">
                <label for="name" class="font-label-sm text-label-sm text-on-surface-variant">Nom</label>
                <input id="name" name="name" type="text" value="{{ old('name', $user->name) }}" required autofocus autocomplete="name"
                       class="rounded-xl bg-surface-container border border-outline-variant/40 px-3 py-2.5 text-on-surface focus:border-primary-container focus:outline-none" />
                <x-input-error class="mt-1" :messages="$errors->get('name')" />
            </div>

            <div class="flex flex-col gap-1">
                <label for="email" class="font-label-sm text-label-sm text-on-surface-variant">Adresse e-mail</label>
                <input id="email" name="email" type="email" value="{{ old('email', $user->email) }}" required autocomplete="username"
                       class="rounded-xl bg-surface-container border border-outline-variant/40 px-3 py-2.5 text-on-surface focus:border-primary-container focus:outline-none" />
                <x-input-error class="mt-1" :messages="$errors->get('email')" />

                @if ($user instanceof \Illuminate\Contracts\Auth\MustVerifyEmail && ! $user->hasVerifiedEmail())
                    <p class="font-body-sm text-body-sm text-on-surface-variant mt-1">
                        Votre adresse e-mail n'est pas vérifiée.
                        <button form="send-verification" class="underline text-primary hover:opacity-80 focus:outline-none">
                            Renvoyer l'e-mail de vérification.
                        </button>
                    </p>

                    @if (session('status') === 'verification-link-sent')
                        <p class="font-body-sm text-body-sm text-green-700 mt-1">Un nouveau lien de vérification a été envoyé.</p>
                    @endif
                @endif
            </div>
        </div>

        @if ($user->isHabitant())
            <div class="flex flex-col gap-1">
                <span class="font-label-sm text-label-sm text-on-surface-variant">Profil de vulnérabilité du foyer</span>
                <p class="font-body-sm text-body-sm text-on-surface-variant">
                    Cochez ce qui s'applique à votre foyer : les messages de vigilance canicule seront adaptés (hydratation, appels, équipements médicaux). Laissez vide pour un profil standard.
                </p>

                <div class="mt-2 grid grid-cols-1 sm:grid-cols-3 gap-3">
                    @foreach (\App\Enums\ProfilVulnerabilite::cases() as $profil)
                        @continue($profil === \App\Enums\ProfilVulnerabilite::Standard)
                        <label class="flex items-start gap-2 rounded-xl border border-outline-variant/40 px-3 py-2.5 cursor-pointer hover:bg-surface-container-high transition-colors">
                            <input type="checkbox" name="profil_vulnerabilites[]" value="{{ $profil->value }}"
                                   @checked(in_array($profil->value, $profilsSelectionnes, true))
                                   class="mt-0.5 h-5 w-5 rounded border-outline-variant text-primary-container focus:ring-primary-container" />
                            <span class="flex items-center gap-1.5 font-body-sm text-body-sm text-on-surface">
                                <span class="material-symbols-outlined text-[18px] text-primary">{{ $profil->icone() }}</span>
                                {{ $profil->label() }}
                            </span>
                        </label>
                    @endforeach
                </div>

                <x-input-error class="mt-1" :messages="$errors->get('profil_vulnerabilites')" />
            </div>
        @endif

        <div class="flex items-center gap-3">
            <x-primary-button>Enregistrer</x-primary-button>

            @if (session('status') === 'profile-updated')
                <p x-data="{ show: true }" x-show="show" x-transition x-init="setTimeout(() => show = false, 2500)"
                   class="font-body-sm text-body-sm text-green-700">Enregistré.</p>
            @endif
        </div>
    </form>
</section>
