@props(['maxWidth' => false])

{{-- Capture webcam partagée entre la connexion par visage et l'enrôlement
     depuis le profil. Les états (streaming, captured, cameraError…) viennent
     de l'Alpine « faceCapture » déclaré par la vue hôte. --}}
<div @class([
    'relative aspect-[4/3] rounded-xl bg-surface-container border border-outline-variant/30 overflow-hidden flex items-center justify-center',
    'max-w-md' => $maxWidth,
])>
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
