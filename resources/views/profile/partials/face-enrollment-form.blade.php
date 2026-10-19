@php($user = $user ?? auth()->user())
@php($faceActif = $user->hasFaceEnrollment())

<section class="flex flex-col gap-space-sm" x-data="faceCapture">
    <header class="flex items-start gap-3">
        <span class="p-2.5 rounded-xl bg-primary/10 text-primary shrink-0">
            <span class="material-symbols-outlined text-[22px]">face</span>
        </span>
        <div class="flex-1">
            <div class="flex items-center gap-2 flex-wrap">
                <h2 class="font-title-md text-title-md text-on-surface">Connexion par visage (Face ID)</h2>
                @if ($faceActif)
                    <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full bg-green-100 text-green-800 font-label-sm text-label-sm">
                        <span class="material-symbols-outlined text-[14px]">check_circle</span>Activée
                    </span>
                @else
                    <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full bg-surface-container-high text-on-surface-variant font-label-sm text-label-sm">Désactivée</span>
                @endif
            </div>
            <p class="font-body-sm text-body-sm text-on-surface-variant mt-0.5">
                Scannez votre visage pour vous connecter sans mot de passe. Votre mot de passe reste utilisable à tout moment.
            </p>
        </div>
    </header>

    @if (session('status') === 'face-enrolled')
        <p class="font-body-sm text-body-sm text-green-700">Connexion par visage activée. Vous pouvez désormais vous connecter avec votre visage.</p>
    @elseif (session('status') === 'face-removed')
        <p class="font-body-sm text-body-sm text-green-700">Image de référence supprimée : la connexion par visage est désactivée.</p>
    @endif

    <div class="rounded-xl border border-amber-300/60 bg-amber-50 text-amber-900 px-3 py-2.5 flex items-start gap-2">
        <span class="material-symbols-outlined text-[20px]">privacy_tip</span>
        <p class="font-body-sm text-body-sm">
            Donnée biométrique : la photo capturée est transmise à Face++ (Megvii, serveurs aux États-Unis) pour la comparaison,
            puis seule l'image de référence est conservée, chiffrée, sur ChillNet jusqu'à sa suppression. Le mot de passe reste
            le moyen de connexion de secours.
        </p>
    </div>

    <form method="post" action="{{ route('profile.face.update') }}" class="flex flex-col gap-3"
          @submit="if (! validate()) { $event.preventDefault(); cameraError = 'Activez la caméra puis capturez votre visage avant de valider.'; }">
        @csrf
        @method('patch')

        <div class="flex flex-col gap-2">
            <span class="font-label-md text-label-md text-on-surface">{{ $faceActif ? 'Remplacer mon visage' : 'Enregistrer mon visage' }}</span>

            <div class="relative aspect-[4/3] max-w-md rounded-xl bg-surface-container border border-outline-variant/30 overflow-hidden flex items-center justify-center">
                <video x-ref="video" x-show="streaming" autoplay playsinline muted
                       class="h-full w-full object-cover -scale-x-100"></video>

                <template x-if="captured">
                    <img :src="captured" alt="Capture de votre visage" class="h-full w-full object-cover -scale-x-100" />
                </template>

                <div x-show="! streaming && ! captured" class="flex flex-col items-center gap-2 p-4 text-center text-on-surface-variant">
                    <span class="material-symbols-outlined text-[42px] text-primary">face</span>
                    <p class="font-body-sm text-body-sm">Activez la caméra, centrez votre visage et capturez.</p>
                </div>
            </div>

            <canvas x-ref="canvas" class="hidden" aria-hidden="true"></canvas>
            <input type="hidden" name="image" :value="captured" />

            <p x-show="cameraError" x-text="cameraError" class="font-body-sm text-body-sm text-error"></p>

            <div class="flex flex-wrap gap-2">
                <button type="button" x-show="! streaming && ! captured" @click="start()" :disabled="starting"
                        class="inline-flex items-center gap-2 px-4 py-2 rounded-lg bg-secondary-container text-on-secondary-container font-label-md text-label-md font-semibold hover:brightness-105 disabled:opacity-60 transition">
                    <span class="material-symbols-outlined text-[20px]">videocam</span>
                    <span x-text="starting ? 'Ouverture…' : 'Activer la caméra'">Activer la caméra</span>
                </button>

                <button type="button" x-show="streaming" @click="capture()"
                        class="inline-flex items-center gap-2 px-4 py-2 rounded-lg bg-primary-container text-on-primary-container font-label-md text-label-md font-semibold hover:brightness-110 transition">
                    <span class="material-symbols-outlined text-[20px]">photo_camera</span>
                    Capturer mon visage
                </button>

                <button type="button" x-show="captured" @click="retake()"
                        class="inline-flex items-center gap-2 px-4 py-2 rounded-lg bg-surface-container-high text-on-surface font-label-md text-label-md hover:bg-surface-variant transition">
                    <span class="material-symbols-outlined text-[20px]">refresh</span>
                    Reprendre
                </button>
            </div>
        </div>

        <label class="flex items-start gap-2 cursor-pointer select-none">
            <input type="checkbox" name="consent" value="1" required class="mt-0.5 w-4 h-4 rounded bg-surface-container accent-primary-container" />
            <span class="font-body-sm text-body-sm text-on-surface-variant">
                J'accepte que ma photo soit transmise à Face++ aux fins de comparaison faciale et conservée chiffrée sur ChillNet
                jusqu'à sa suppression. Je sais que je peux la supprimer à tout moment et continuer à me connecter par mot de passe.
            </span>
        </label>
        <x-input-error :messages="$errors->faceEnrollment->get('consent')" />
        <x-input-error :messages="$errors->faceEnrollment->get('image')" />

        <div class="flex flex-col gap-1.5 max-w-sm">
            <label for="face_enroll_password" class="font-label-sm text-label-sm text-on-surface-variant">Confirmez avec votre mot de passe</label>
            <input id="face_enroll_password" name="password" type="password" required autocomplete="current-password"
                   class="w-full bg-surface-container rounded-lg px-3 py-2.5 text-on-surface font-body-md text-body-md focus:outline-none focus:ring-2 focus:ring-primary-container transition-colors" />
        </div>

        <div>
            <button type="submit" :disabled="! captured"
                    class="inline-flex items-center gap-2 px-4 py-2.5 rounded-lg bg-primary-container text-on-primary-container font-label-md text-label-md font-semibold hover:brightness-110 disabled:opacity-50 disabled:cursor-not-allowed transition">
                <span class="material-symbols-outlined text-[20px]">face</span>
                {{ $faceActif ? 'Remplacer mon visage' : 'Activer la connexion par visage' }}
            </button>
        </div>
    </form>

    @if ($faceActif)
        <form method="post" action="{{ route('profile.face.destroy') }}" class="flex flex-col gap-2 border-t border-outline-variant/30 pt-3"
              onsubmit="return confirm('Supprimer votre image de référence et désactiver la connexion par visage ?');">
            @csrf
            @method('delete')
            <p class="font-label-md text-label-md text-on-surface">Supprimer mon visage</p>
            <p class="font-body-sm text-body-sm text-on-surface-variant">L'image de référence, la date d'enrôlement et le consentement seront effacés. Votre mot de passe reste actif.</p>
            <div class="flex flex-wrap items-end gap-3">
                <div class="flex flex-col gap-1.5 max-w-sm flex-1">
                    <label for="face_remove_password" class="font-label-sm text-label-sm text-on-surface-variant">Mot de passe</label>
                    <input id="face_remove_password" name="password" type="password" required autocomplete="current-password"
                           class="w-full bg-surface-container rounded-lg px-3 py-2.5 text-on-surface font-body-md text-body-md focus:outline-none focus:ring-2 focus:ring-primary-container transition-colors" />
                </div>
                <button type="submit"
                        class="inline-flex items-center gap-2 px-4 py-2.5 rounded-lg bg-error/10 text-error font-label-md text-label-md font-semibold hover:bg-error/20 transition">
                    <span class="material-symbols-outlined text-[20px]">delete</span>
                    Supprimer et désactiver
                </button>
            </div>
        </form>
    @endif

    {{-- Erreur mot de passe commune aux deux formulaires (même sac faceEnrollment). --}}
    <x-input-error :messages="$errors->faceEnrollment->get('password')" />
</section>
