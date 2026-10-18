<x-guest-layout title="Vérifiez votre adresse e-mail" subtitle="Une dernière étape avant d'accéder à votre espace.">
    <section class="rounded-xl bg-surface-container-low/90 backdrop-blur-2xl p-5 shadow-2xl flex flex-col gap-4">
        <p class="font-body-sm text-body-sm text-on-surface-variant">
            Merci pour votre inscription ! Avant de commencer, confirmez votre adresse e-mail en cliquant sur le lien que nous venons de vous envoyer.
        </p>

        @if (session('status') == 'verification-link-sent')
            <div class="rounded-lg bg-surface-container border border-primary-container/30 px-3 py-2 flex items-center gap-2 font-body-sm text-body-sm text-green-700">
                <span class="material-symbols-outlined text-[18px] text-primary">mark_email_read</span>
                Un nouveau lien de vérification a été envoyé à l'adresse utilisée lors de l'inscription.
            </div>
        @endif

        <div class="flex flex-col sm:flex-row gap-3">
            <form method="POST" action="{{ route('verification.send') }}" class="flex-1">
                @csrf
                <button type="submit" class="w-full py-2.5 px-4 rounded-lg bg-primary-container text-on-primary-container font-label-md text-label-md font-bold hover:brightness-110 transition">
                    Renvoyer l'e-mail de vérification
                </button>
            </form>

            <form method="POST" action="{{ route('logout') }}">
                @csrf
                <button type="submit" class="w-full px-4 py-2.5 rounded-lg bg-surface-container-high text-on-surface font-label-md text-label-md hover:bg-surface-variant transition">
                    Se déconnecter
                </button>
            </form>
        </div>

        <p class="font-body-sm text-body-sm text-on-surface-variant">
            Vous ne trouvez pas l'e-mail ? Pensez à vérifier vos courriers indésirables, puis utilisez le bouton ci-dessus pour en recevoir un nouveau.
        </p>
    </section>
</x-guest-layout>