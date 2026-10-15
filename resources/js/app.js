import './bootstrap';

import Alpine from 'alpinejs';

window.Alpine = Alpine;

// Confirmation de suppression unifiée : un seul état partagé pour toute la page.
// N'importe quel formulaire portant `data-confirm="…"` déclenche la modale
// (voir resources/views/components/confirm-delete.blade.php) au lieu du
// confirm() natif du navigateur.
Alpine.store('confirmDelete', {
    ouvert: false,
    message: '',
    formulaire: null,

    ouvrir(form) {
        this.message = form.dataset.confirm || '';
        this.formulaire = form;
        this.ouvert = true;
    },

    confirmer() {
        const form = this.formulaire;

        this.fermer();

        // form.submit() court-circuite l'écouteur ci-dessous (pas de boucle),
        // contrairement à requestSubmit() qui re-déclencherait l'événement.
        if (form) {
            form.submit();
        }
    },

    annuler() {
        this.fermer();
    },

    fermer() {
        this.ouvert = false;
        this.formulaire = null;
        this.message = '';
    },
});

// Capture phase : on intercepte avant tout handler inline éventuel.
document.addEventListener('submit', (event) => {
    const form = event.target.closest?.('form[data-confirm]');

    if (! form) {
        return;
    }

    event.preventDefault();
    event.stopImmediatePropagation();

    Alpine.store('confirmDelete').ouvrir(form);
}, true);

Alpine.start();
