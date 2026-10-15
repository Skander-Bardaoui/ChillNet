@php
    // Modale unique de confirmation de suppression, montée une fois par page
    // (voir les layouts app / back / public). Aucun paramètre : elle réagit à
    // l'état global Alpine `confirmDelete`, alimenté par l'intercepteur de
    // soumission défini dans resources/js/app.js dès qu'un <form data-confirm>
    // est envoyé.
@endphp

<div
    x-data="{
        focusables() {
            return [...$el.querySelectorAll('button')].filter(el => ! el.hasAttribute('disabled'));
        },
        focusSuivant() {
            const f = this.focusables();
            const i = f.indexOf(document.activeElement);
            (f[i + 1] || f[0]).focus();
        },
        focusPrecedent() {
            const f = this.focusables();
            const i = f.indexOf(document.activeElement);
            (f[i - 1] || f[f.length - 1]).focus();
        },
    }"
    x-init="$watch('$store.confirmDelete.ouvert', value => {
        if (value) {
            document.body.classList.add('overflow-y-hidden');
            $nextTick(() => $refs.annuler.focus());
        } else {
            document.body.classList.remove('overflow-y-hidden');
        }
    })"
    x-cloak
    x-show="$store.confirmDelete.ouvert"
    x-transition.opacity
    role="dialog"
    aria-modal="true"
    aria-labelledby="confirm-delete-titre"
    @keydown.escape.window="$store.confirmDelete.annuler()"
    @keydown.tab.prevent="focusSuivant()"
    @keydown.shift.tab.prevent="focusPrecedent()"
    @click.self="$store.confirmDelete.annuler()"
    class="fixed inset-0 z-[100] flex items-center justify-center p-4 bg-black/50"
>
    <div
        x-show="$store.confirmDelete.ouvert"
        x-transition
        class="w-full max-w-sm rounded-2xl bg-surface-container-high p-6 shadow-xl"
    >
        <div class="flex items-start gap-3">
            <span class="material-symbols-outlined text-error text-[24px]" aria-hidden="true">warning</span>
            <div>
                <h2 id="confirm-delete-titre" class="font-title-md text-title-md text-on-surface">Confirmer la suppression</h2>
                <p class="mt-1 font-body-md text-body-md text-on-surface-variant"
                    x-text="$store.confirmDelete.message || 'Cette action est définitive.'"></p>
            </div>
        </div>

        <div class="mt-6 flex justify-end gap-2">
            <button type="button" x-ref="annuler" @click="$store.confirmDelete.annuler()"
                class="px-4 py-2 rounded-lg bg-surface-container text-on-surface font-label-md text-label-md hover:bg-surface-container-lowest transition-colors">
                Annuler
            </button>
            <button type="button" x-ref="confirmer" @click="$store.confirmDelete.confirmer()"
                class="px-4 py-2 rounded-lg bg-error text-on-error font-label-md text-label-md font-semibold hover:opacity-90 transition-opacity">
                Supprimer
            </button>
        </div>
    </div>
</div>
