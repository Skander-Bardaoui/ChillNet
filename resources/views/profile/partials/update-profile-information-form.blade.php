<section>
    <header>
        <h2 class="text-lg font-medium text-gray-900 dark:text-gray-100">
            {{ __('Profile Information') }}
        </h2>

        <p class="mt-1 text-sm text-gray-600 dark:text-gray-400">
            {{ __("Update your account's profile information and email address.") }}
        </p>
    </header>

    <form id="send-verification" method="post" action="{{ route('verification.send') }}">
        @csrf
    </form>

    <form method="post" action="{{ route('profile.update') }}" class="mt-6 space-y-6">
        @csrf
        @method('patch')

        <div>
            <x-input-label for="name" :value="__('Name')" />
            <x-text-input id="name" name="name" type="text" class="mt-1 block w-full" :value="old('name', $user->name)" required autofocus autocomplete="name" />
            <x-input-error class="mt-2" :messages="$errors->get('name')" />
        </div>

        <div>
            <x-input-label for="email" :value="__('Email')" />
            <x-text-input id="email" name="email" type="email" class="mt-1 block w-full" :value="old('email', $user->email)" required autocomplete="username" />
            <x-input-error class="mt-2" :messages="$errors->get('email')" />

            @if ($user instanceof \Illuminate\Contracts\Auth\MustVerifyEmail && ! $user->hasVerifiedEmail())
                <div>
                    <p class="text-sm mt-2 text-gray-800 dark:text-gray-200">
                        {{ __('Your email address is unverified.') }}

                        <button form="send-verification" class="underline text-sm text-gray-600 dark:text-gray-400 hover:text-gray-900 dark:text-gray-100 rounded-md focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500">
                            {{ __('Click here to re-send the verification email.') }}
                        </button>
                    </p>

                    @if (session('status') === 'verification-link-sent')
                        <p class="mt-2 font-medium text-sm text-green-600">
                            {{ __('A new verification link has been sent to your email address.') }}
                        </p>
                    @endif
                </div>
            @endif
        </div>

        <div>
            <x-input-label :value="__('Profil de vulnérabilité du foyer')" />
            <p class="mt-1 text-sm text-gray-600 dark:text-gray-400">
                {{ __("Cochez ce qui s'applique à votre foyer : les messages de vigilance canicule seront adaptés (hydratation, appels, équipements médicaux). Laissez vide pour un profil standard.") }}
            </p>

            @php
                $profilsSelectionnes = old('profil_vulnerabilites', $user->profilsVulnerabilite());
                $profilsSelectionnes = array_map(fn ($p) => $p instanceof \App\Enums\ProfilVulnerabilite ? $p->value : (string) $p, $profilsSelectionnes);
            @endphp

            <div class="mt-3 grid grid-cols-1 sm:grid-cols-3 gap-3">
                @foreach (\App\Enums\ProfilVulnerabilite::cases() as $profil)
                    @continue($profil === \App\Enums\ProfilVulnerabilite::Standard)
                    <label class="flex items-start gap-2 rounded-xl border border-gray-200 dark:border-gray-700 px-3 py-2.5 cursor-pointer hover:bg-gray-50 dark:hover:bg-gray-800">
                        <input
                            type="checkbox"
                            name="profil_vulnerabilites[]"
                            value="{{ $profil->value }}"
                            @checked(in_array($profil->value, $profilsSelectionnes, true))
                            class="mt-0.5 h-5 w-5 rounded border-gray-300 text-indigo-600 focus:ring-indigo-500"
                        />
                        <span class="flex items-center gap-1.5 text-sm text-gray-700 dark:text-gray-300">
                            <span class="material-symbols-outlined text-[18px]">{{ $profil->icone() }}</span>
                            {{ $profil->label() }}
                        </span>
                    </label>
                @endforeach
            </div>

            <x-input-error class="mt-2" :messages="$errors->get('profil_vulnerabilites')" />
        </div>

        <div class="flex items-center gap-4">
            <x-primary-button>{{ __('Save') }}</x-primary-button>

            @if (session('status') === 'profile-updated')
                <p
                    x-data="{ show: true }"
                    x-show="show"
                    x-transition
                    x-init="setTimeout(() => show = false, 2000)"
                    class="text-sm text-gray-600 dark:text-gray-400"
                >{{ __('Saved.') }}</p>
            @endif
        </div>
    </form>
</section>
