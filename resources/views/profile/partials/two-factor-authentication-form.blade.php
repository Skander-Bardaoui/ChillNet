@php
    $user = $user ?? auth()->user();
    $secret = $user->two_factor_secret;
    $active = $user->hasEnabledTwoFactorAuthentication();
    $enAttente = ! $active && ! empty($secret);
    $codes = $user->recoveryCodes();
    $qrUri = $secret
        ? \App\Support\Totp::provisioningUri($secret, (string) $user->email, config('app.name', 'ChillNet'))
        : null;
@endphp

<section
    class="flex flex-col gap-space-sm"
    x-data="{
        voirCodes: {{ $active ? 'false' : 'true' }},
        confirmerDesactivation: false,
        copieSecret: false,
        copieCodes: false,
        copier(texte, cle) {
            const fait = () => {
                this[cle] = true;
                setTimeout(() => { this[cle] = false }, 2000);
            };
            if (navigator.clipboard && window.isSecureContext) {
                navigator.clipboard.writeText(texte).then(fait).catch(() => this.copierRepli(texte, fait));
            } else {
                this.copierRepli(texte, fait);
            }
        },
        copierRepli(texte, fait) {
            const zone = document.createElement('textarea');
            zone.value = texte;
            zone.style.position = 'fixed';
            zone.style.opacity = '0';
            document.body.appendChild(zone);
            zone.select();
            try { document.execCommand('copy'); fait(); } catch (e) {}
            document.body.removeChild(zone);
        }
    }"
>
    <header class="flex items-start gap-3">
        <span class="p-2.5 rounded-xl bg-primary/10 text-primary shrink-0">
            <span class="material-symbols-outlined text-[22px]">shield_person</span>
        </span>
        <div class="flex-1">
            <div class="flex items-center gap-2 flex-wrap">
                <h2 class="font-title-md text-title-md text-on-surface">Double authentification (2FA)</h2>
                @if ($active)
                    <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full bg-green-100 text-green-800 font-label-sm text-label-sm">
                        <span class="material-symbols-outlined text-[14px]">check_circle</span>Activée
                    </span>
                @elseif ($enAttente)
                    <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full bg-amber-100 text-amber-800 font-label-sm text-label-sm">
                        <span class="material-symbols-outlined text-[14px]">pending</span>Configuration en cours
                    </span>
                @else
                    <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full bg-surface-container-high text-on-surface-variant font-label-sm text-label-sm">Désactivée</span>
                @endif
            </div>
            <p class="font-body-sm text-body-sm text-on-surface-variant mt-0.5">
                En plus de votre mot de passe, un code temporaire sera demandé à chaque connexion. Même si votre mot de passe est compromis, votre compte reste protégé.
            </p>
        </div>
    </header>

    @if (session('status') === 'two-factor-enabled')
        <p class="font-body-sm text-body-sm text-green-700">Configuration initialisée : scannez le QR code puis validez un code pour activer la 2FA.</p>
    @elseif (session('status') === 'two-factor-confirmed')
        <p class="font-body-sm text-body-sm text-green-700">Double authentification activée. Conservez vos codes de récupération en lieu sûr.</p>
    @elseif (session('status') === 'two-factor-disabled')
        <p class="font-body-sm text-body-sm text-green-700">Double authentification désactivée.</p>
    @elseif (session('status') === 'two-factor-recovery-codes-regenerated')
        <p class="font-body-sm text-body-sm text-green-700">Nouveaux codes de récupération générés. Les anciens ne sont plus valables.</p>
    @endif

    {{-- État 1 : aucune configuration — présentation en 3 étapes --}}
    @if (! $secret)
        <div class="grid grid-cols-1 sm:grid-cols-3 gap-3">
            <div class="rounded-xl bg-surface-container border border-outline-variant/30 p-3 flex flex-col gap-1">
                <span class="material-symbols-outlined text-primary">install_mobile</span>
                <p class="font-label-md text-label-md text-on-surface">1. Installez une application</p>
                <p class="font-body-sm text-body-sm text-on-surface-variant">Google Authenticator, Authy, Microsoft Authenticator…</p>
            </div>
            <div class="rounded-xl bg-surface-container border border-outline-variant/30 p-3 flex flex-col gap-1">
                <span class="material-symbols-outlined text-primary">qr_code_scanner</span>
                <p class="font-label-md text-label-md text-on-surface">2. Scannez le QR code</p>
                <p class="font-body-sm text-body-sm text-on-surface-variant">Il s'affiche immédiatement après l'activation.</p>
            </div>
            <div class="rounded-xl bg-surface-container border border-outline-variant/30 p-3 flex flex-col gap-1">
                <span class="material-symbols-outlined text-primary">pin</span>
                <p class="font-label-md text-label-md text-on-surface">3. Validez un code</p>
                <p class="font-body-sm text-body-sm text-on-surface-variant">Saisissez les 6 chiffres pour confirmer l'activation.</p>
            </div>
        </div>

        <form method="post" action="{{ route('two-factor.enable') }}">
            @csrf
            <x-primary-button>Activer la double authentification</x-primary-button>
        </form>
    @endif

    {{-- État 2 : secret généré, en attente de confirmation --}}
    @if ($enAttente)
        <div class="rounded-xl border border-amber-300/60 bg-amber-50 text-amber-900 px-3 py-2.5 flex items-start gap-2">
            <span class="material-symbols-outlined text-[20px]">info</span>
            <p class="font-body-sm text-body-sm">
                La 2FA n'est pas encore active. Scannez le QR code puis validez un code à 6 chiffres ci-dessous : tant que ce n'est pas fait, la connexion reste protégée par le mot de passe seul.
            </p>
        </div>

        <div class="grid grid-cols-1 lg:grid-cols-[auto,1fr] gap-5 items-start">
            <div class="flex flex-col items-center gap-2">
                <div id="totp-qr" role="img" aria-label="QR code de configuration de la double authentification"
                     class="w-[196px] h-[196px] rounded-xl bg-white p-2 shadow-sm flex items-center justify-center"></div>
                <p class="font-body-sm text-body-sm text-on-surface-variant text-center max-w-[220px]">Scannez ce QR code avec votre application d'authentification.</p>
                <noscript>
                    <p class="font-body-sm text-body-sm text-red-700 text-center max-w-[220px]">Le QR code nécessite JavaScript : utilisez la clé manuelle ci-contre.</p>
                </noscript>
            </div>

            <div class="flex flex-col gap-3">
                <div class="flex flex-col gap-1">
                    <span class="font-label-sm text-label-sm text-on-surface-variant">Clé de configuration manuelle</span>
                    <div class="flex items-center gap-2">
                        <code class="flex-1 break-all rounded-lg bg-surface-container border border-outline-variant/40 px-3 py-2 font-mono text-body-sm text-on-surface">{{ $secret }}</code>
                        <button type="button"
                                @click="copier(@js($secret), 'copieSecret')"
                                aria-label="Copier la clé de configuration"
                                class="inline-flex items-center gap-1 px-3 py-2 rounded-lg bg-surface-container-high hover:bg-surface-variant text-on-surface font-label-sm text-label-sm transition">
                            <span class="material-symbols-outlined text-[18px]" x-text="copieSecret ? 'check' : 'content_copy'">content_copy</span>
                            <span x-text="copieSecret ? 'Copié' : 'Copier'">Copier</span>
                        </button>
                    </div>
                </div>

                <form method="post" action="{{ route('two-factor.confirm') }}" class="flex flex-col gap-2">
                    @csrf
                    <label for="two_factor_code" class="font-label-sm text-label-sm text-on-surface-variant">Code à 6 chiffres fourni par l'application</label>
                    <div class="flex items-center gap-3">
                        <input id="two_factor_code" name="code" type="text" inputmode="numeric" autocomplete="one-time-code" maxlength="6" autofocus
                               class="w-40 rounded-xl bg-surface-container border border-outline-variant/40 px-3 py-2.5 text-center tracking-widest text-on-surface focus:border-primary-container focus:outline-none" />
                        <x-primary-button>Valider et activer</x-primary-button>
                    </div>
                    <x-input-error :messages="$errors->get('code')" />
                </form>

                <form method="post" action="{{ route('two-factor.disable') }}">
                    @csrf
                    @method('delete')
                    <button type="submit" class="font-body-sm text-body-sm text-error hover:underline">Annuler la configuration</button>
                </form>
            </div>
        </div>
    @endif

    {{-- Codes de récupération --}}
    @if ($secret && $codes !== [])
        <div class="rounded-xl bg-surface-container border border-outline-variant/30 p-3 flex flex-col gap-2">
            <div class="flex items-center justify-between gap-3 flex-wrap">
                <h3 class="font-label-md text-label-md text-on-surface flex items-center gap-1.5">
                    <span class="material-symbols-outlined text-[18px] text-primary">vpn_key</span>Codes de récupération
                </h3>
                <div class="flex items-center gap-3">
                    <button type="button" @click="copier(@js(implode("\n", $codes)), 'copieCodes')"
                            class="inline-flex items-center gap-1 font-body-sm text-body-sm text-primary hover:underline">
                        <span class="material-symbols-outlined text-[16px]" x-text="copieCodes ? 'check' : 'content_copy'">content_copy</span>
                        <span x-text="copieCodes ? 'Copiés' : 'Tout copier'">Tout copier</span>
                    </button>
                    <button type="button" @click="voirCodes = ! voirCodes"
                            class="font-body-sm text-body-sm text-primary hover:underline"
                            x-text="voirCodes ? 'Masquer' : 'Afficher'">Masquer</button>
                </div>
            </div>
            <p class="font-body-sm text-body-sm text-on-surface-variant">Chaque code vous connecte une seule fois si vous perdez votre appareil. Conservez-les hors de votre téléphone (gestionnaire de mots de passe, papier…).</p>
            <ul x-show="voirCodes" x-cloak class="grid grid-cols-2 sm:grid-cols-4 gap-2">
                @foreach ($codes as $codeRecuperation)
                    <li class="rounded-lg bg-surface-container-high px-2 py-1.5 text-center font-mono text-body-sm text-on-surface">{{ $codeRecuperation }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    {{-- État 3 : 2FA active — actions --}}
    @if ($active)
        <div class="rounded-xl bg-green-50 border border-green-200 px-3 py-2.5 flex items-start gap-2 text-green-900">
            <span class="material-symbols-outlined text-[20px]">verified_user</span>
            <p class="font-body-sm text-body-sm">
                Votre compte est protégé par un second facteur{{ $user->two_factor_confirmed_at ? ' depuis le '.$user->two_factor_confirmed_at->format('d/m/Y') : '' }}.
                À chaque connexion, un code vous sera demandé après votre mot de passe.
            </p>
        </div>

        <div class="flex flex-wrap items-center gap-3">
            <form method="post" action="{{ route('two-factor.regenerate-recovery-codes') }}">
                @csrf
                <x-secondary-button>Régénérer les codes de récupération</x-secondary-button>
            </form>
            <button type="button" @click="confirmerDesactivation = true"
                    class="inline-flex items-center gap-2 px-4 py-2 rounded-lg bg-error/10 text-error font-label-md text-label-md font-semibold hover:bg-error/20 transition">
                <span class="material-symbols-outlined text-[18px]">shield_off</span>Désactiver la 2FA
            </button>
        </div>

        {{-- Confirmation avant désactivation --}}
        <div x-show="confirmerDesactivation" x-cloak class="fixed inset-0 z-50 overflow-y-auto px-4 py-10"
             @keydown.escape.window="confirmerDesactivation = false" role="dialog" aria-modal="true" aria-labelledby="titre-desactivation-2fa">
            <div class="fixed inset-0 bg-black/60" @click="confirmerDesactivation = false"></div>

            <div class="relative mx-auto max-w-lg rounded-2xl bg-surface-container-low border border-outline-variant/20 shadow-xl p-space-md flex flex-col gap-4">
                <div class="flex items-start gap-3">
                    <span class="p-2 rounded-xl bg-error/10 text-error shrink-0">
                        <span class="material-symbols-outlined text-[22px]">warning</span>
                    </span>
                    <div>
                        <h3 id="titre-desactivation-2fa" class="font-title-md text-title-md text-on-surface">Désactiver la double authentification ?</h3>
                        <p class="font-body-sm text-body-sm text-on-surface-variant mt-1">
                            Votre compte ne sera plus protégé par un second facteur et vos codes de récupération seront supprimés. Vous pourrez réactiver la 2FA à tout moment.
                        </p>
                    </div>
                </div>

                <div class="flex items-center justify-end gap-3">
                    <button type="button" @click="confirmerDesactivation = false"
                            class="px-4 py-2 rounded-lg bg-surface-container-high text-on-surface font-label-md text-label-md hover:bg-surface-variant transition">
                        Annuler
                    </button>
                    <form method="post" action="{{ route('two-factor.disable') }}">
                        @csrf
                        @method('delete')
                        <button type="submit"
                                class="inline-flex items-center gap-2 px-4 py-2 rounded-lg bg-error text-on-error font-label-md text-label-md font-semibold hover:opacity-95 transition">
                            <span class="material-symbols-outlined text-[18px]">shield_off</span>Désactiver
                        </button>
                    </form>
                </div>
            </div>
        </div>
    @endif

    @if ($enAttente && $qrUri)
        <script src="{{ asset('vendor/qrcode-generator/qrcode.js') }}"></script>
        <script>
            (function () {
                var el = document.getElementById('totp-qr');
                if (!el || typeof qrcode === 'undefined') { return; }
                var qr = qrcode(0, 'M');
                qr.addData(@json($qrUri));
                qr.make();
                el.innerHTML = qr.createSvgTag({
                    cellSize: 4,
                    margin: 2,
                    title: 'QR code de configuration de la double authentification ChillNet'
                });
                var svg = el.querySelector('svg');
                if (svg) {
                    svg.setAttribute('class', 'w-44 h-44');
                    svg.removeAttribute('width');
                    svg.removeAttribute('height');
                }
            })();
        </script>
    @endif
</section>