@php
// Tokens du design system ChillNet.
// Sombre = navy existant adouci (cyan néon remplacé par un bleu acier).
// Clair = "blanc sale" chaud professionnel : fond sale, cartes quasi-blanches qui ressortent.
@endphp
<link rel="preconnect" href="https://fonts.googleapis.com" />
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin />
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet" />
<link href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined:opsz,wght,FILL,GRAD@20..48,100..700,0..1,-50..200" rel="stylesheet" />
<script>
(function () {
    var root = document.documentElement;
    // Thème global : défaut = clair (white mode blanc sale). Persisté en localStorage.
    // Plus de restriction à la landing : toutes les pages peuvent basculer.
    try {
        var stored = localStorage.getItem('chillnet-theme');
        if (stored === 'dark') {
            root.classList.remove('light');
            root.classList.add('dark');
        } else {
            root.classList.remove('dark');
            root.classList.add('light');
        }
    } catch (e) {
        root.classList.remove('dark');
        root.classList.add('light');
    }
})();
function chillnetToggleTheme(btn) {
    var root = document.documentElement;
    var toLight = root.classList.contains('dark');
    root.classList.toggle('light', toLight);
    root.classList.toggle('dark', !toLight);
    try { localStorage.setItem('chillnet-theme', toLight ? 'light' : 'dark'); } catch (e) {}
    // Met à jour toutes les icônes de toggle présentes sur la page
    document.querySelectorAll('[data-theme-icon]').forEach(function (el) {
        el.textContent = toLight ? 'dark_mode' : 'light_mode';
    });
}
// WCAG : les icônes Material Symbols sont décoratives (boutons déjà labellisés,
// texte adjacent redondant) → masquées aux technologies d'assistance (SC 1.1.1).
document.addEventListener('DOMContentLoaded', function () {
    document.querySelectorAll('.material-symbols-outlined').forEach(function (el) {
        if (!el.hasAttribute('aria-hidden')) el.setAttribute('aria-hidden', 'true');
    });
});
</script>
<style>
:root, html.dark {
    --md-surface: 15 19 29;
    --md-background: 15 19 29;
    --md-surface-container-lowest: 10 14 24;
    --md-nav: 17 26 44;
    --md-surface-container-low: 23 27 38;
    --md-surface-container: 28 31 42;
    --md-surface-container-high: 38 42 53;
    --md-surface-container-highest: 49 53 64;
    --md-on-surface: 223 226 241;
    --md-on-surface-variant: 186 201 204;
    --md-on-background: 223 226 241;
    --md-outline: 132 147 150;
    --md-outline-variant: 59 73 76;
    --md-primary: 150 210 235;
    --md-primary-container: 56 165 220;
    --md-on-primary-container: 255 255 255;
    --md-on-primary: 8 30 42;
    --md-surface-tint: 56 165 220;
    --md-tertiary-fixed: 255 222 172;
    --md-tertiary-fixed-dim: 255 186 56;
    --md-tertiary-container: 255 199 105;
    --md-error-container: 147 0 10;
    --md-on-error-container: 255 218 214;
}
/* ── WHITE MODE : blanc sale chaud, jamais de blanc pur en fond ──
   Fond = lin sale #F2F0E9 / #EFEDE5, cartes = blanc cassé #FDFCF9 → vrai blanc #FFFFFF
   pour que chaque card ressorte avec bordure + ombre douce. */
html.light {
    --md-surface: 245 244 241;
    --md-background: 239 238 234;
    --md-surface-container-lowest: 255 255 255;
    --md-nav: 252 252 250;
    --md-surface-container-low: 252 252 250;
    --md-surface-container: 247 246 243;
    --md-surface-container-high: 233 231 226;
    --md-surface-container-highest: 221 219 212;
    --md-on-surface: 24 30 42;
    --md-on-surface-variant: 88 99 117;
    --md-on-background: 24 30 42;
    --md-outline: 148 158 172;
    --md-outline-variant: 218 216 208;
    /* Bleu pro assombri : azure acier #1B77BA, texte blanc dessus */
    --md-primary: 27 119 186;
    --md-primary-container: 27 119 186;
    --md-on-primary-container: 255 255 255;
    --md-on-primary: 255 255 255;
    --md-surface-tint: 27 119 186;
    --md-tertiary-fixed: 154 66 8;
    --md-tertiary-fixed-dim: 202 101 14;
    --md-tertiary-container: 234 149 36;
    --md-error-container: 253 232 230;
    --md-on-error-container: 154 22 22;
}
/* Navbar */
header.fixed {
    border: 1px solid rgba(255, 255, 255, 0.09);
    box-shadow:
        inset 0 1px 0 rgba(255, 255, 255, 0.06),
        0 18px 40px -20px rgba(2, 6, 23, 0.9) !important;
}
html.light header.fixed {
    border: 1px solid rgb(26 32 44 / 0.08);
    background: rgb(var(--md-nav) / 0.92) !important;
    box-shadow: 0 1px 2px rgba(26,32,44,0.05), 0 12px 28px -14px rgba(26,32,44,0.22) !important;
}
html.light footer { box-shadow: 0 -1px 12px rgba(26, 32, 44, 0.07) !important; }
/* ── Neutralisation des glows néon cyan en mode clair ── */
html.light [class*="rgba(0,229,255"],
html.light [class*="rgba(0,218,243"],
html.light [class*="0,229,255"],
html.light [class*="#00e5ff"],
html.light [class*="#00E5FF"] {
    box-shadow: 0 1px 2px rgba(26,32,44,0.06), 0 8px 20px -10px rgba(27,119,186,0.35) !important;
}
/* Boutons bleus en clair : dégradé pro + relief, hover foncé, plus de glow */
html.light .bg-primary-container {
    background: linear-gradient(180deg, rgb(33 130 198), rgb(22 104 163)) !important;
    color: #fff !important;
    box-shadow: inset 0 1px 0 rgba(255,255,255,0.22), 0 1px 2px rgba(24,30,42,0.18), 0 8px 18px -10px rgba(27,119,186,0.55) !important;
}
html.light a.bg-primary-container:hover,
html.light button.bg-primary-container:hover {
    background: linear-gradient(180deg, rgb(27 119 186), rgb(18 90 142)) !important;
}
html.light .bg-primary-container:focus-visible,
html.light button.bg-primary-container:focus-visible,
html.light a.bg-primary-container:focus-visible {
    outline: 2px solid rgb(27 119 186 / 0.55);
    outline-offset: 2px;
}
html.light .text-primary { color: rgb(22 104 163) !important; }
html.light .text-primary-container { color: rgb(22 104 163) !important; }
/* Textes bleu pâle pensés pour le sombre → bleu profond lisible en clair */
html.light .text-primary-fixed-dim { color: rgb(14 92 140) !important; }
html.light .text-primary-fixed { color: rgb(14 92 140) !important; }
html.light .text-secondary { color: rgb(47 91 179) !important; }
html.light .bg-secondary\/10 { background-color: rgb(5 102 217 / 0.1) !important; }
/* Pastille bleue pleine (ex. badge température) : texte blanc, pas noir */
html.light .text-on-primary-fixed { color: #fff !important; }
/* Badges gris sombre codés en dur (#313540) → pastille claire en white mode */
html.light .bg-surface-variant { background-color: rgb(228 234 242) !important; }
html.light .bg-surface-bright { background-color: rgb(233 231 226) !important; }
html.light .hover\:bg-surface-bright:hover { background-color: rgb(229 227 220) !important; }
html.light .hover\:bg-surface-variant:hover { background-color: rgb(219 226 235) !important; }
/* Pastilles / badges bleutés en clair : fond bleu très pâle + texte bleu foncé */
html.light .bg-primary-container\/10 { background-color: rgb(27 119 186 / 0.09) !important; }
html.light .bg-primary-container\/15 { background-color: rgb(27 119 186 / 0.12) !important; }
html.light .bg-primary-container\/20 { background-color: rgb(27 119 186 / 0.14) !important; }
/* ── Badges : le texte ne doit JAMAIS être du même ton que le fond ──
   Bandes teintées (bleu pâle / gris) → texte encre marine bien distinct. */
html.light [class*="bg-primary-container/"].text-primary,
html.light [class*="bg-primary-container/"].text-primary-container,
html.light [class*="bg-primary/"].text-primary,
html.light [class*="bg-primary/"].text-primary-container,
html.light .bg-surface-variant.text-primary,
html.light .bg-surface-variant.text-primary-container,
html.light [class*="bg-surface-container-high"].text-primary,
html.light [class*="bg-surface-container-high"].text-primary-container,
html.light [class*="bg-surface-container-highest"].text-primary,
html.light [class*="bg-surface-container-highest"].text-primary-container {
    color: rgb(11 59 88) !important;
}
html.dark [class*="bg-primary-container/"].text-primary,
html.dark [class*="bg-primary-container/"].text-primary-container,
html.dark [class*="bg-primary/"].text-primary,
html.dark [class*="bg-primary/"].text-primary-container {
    color: rgb(224 243 253) !important;
}
/* ── Badges sémantiques rouge / orange : texte soutenu, distinct du fond teinté ── */
html.light [class*="bg-error/"].text-error { color: rgb(127 29 29) !important; }
html.light [class*="bg-tertiary-container/"].text-tertiary,
html.light [class*="bg-tertiary/"].text-tertiary { color: rgb(146 64 14) !important; }
/* Boutons rouge plein : texte blanc, jamais de marron sur rouge */
.bg-error.text-on-error { color: #fff !important; }
.hover\:bg-error:hover { color: #fff !important; }
html.light .hover\:text-on-error:hover { color: #fff !important; }
html.dark .hover\:text-on-error:hover { color: #fff !important; }
html.dark [class*="bg-error/"].text-error { color: rgb(252 165 165) !important; }
html.dark [class*="bg-tertiary-container/"].text-tertiary { color: rgb(253 230 138) !important; }
html.dark [class*="bg-tertiary-container/"].text-tertiary-fixed { color: rgb(255 237 213) !important; }
/* ── Cards & conteneurs en clair : vraies cartes blanches détachées du fond sale ── */
html.light .bg-surface-container-low,
html.light .bg-surface-container-lowest\/80,
html.light main .rounded-xl,
html.light main .rounded-2xl {
    border-color: rgb(26 32 44 / 0.08);
}
html.light main .rounded-xl.bg-surface-container-low,
html.light main .rounded-xl.bg-surface-container,
html.light main .rounded-2xl {
    box-shadow: inset 0 1px 0 rgba(255,255,255,0.9), 0 1px 2px rgba(24,30,42,0.06), 0 14px 30px -20px rgba(24,30,42,0.28);
}
/* Hiérarchie : titres bien ancrés, libellés secondaires adoucis */
html.light main h1 { color: rgb(24 30 42); letter-spacing: -0.01em; }
html.light main h2 { color: rgb(24 30 42); letter-spacing: -0.008em; }
html.light .border-primary-container\/30,
html.light .border-primary-container\/40 {
    border-color: rgb(27 119 186 / 0.28) !important;
}
html.light .focus\:border-primary-container:focus { border-color: rgb(27 119 186) !important; }
/* Champs de formulaire en clair */
html.light input:not([type="checkbox"]):not([type="radio"]),
html.light select,
html.light textarea {
    background-color: #fff !important;
    border-color: rgb(26 32 44 / 0.14) !important;
    color: rgb(26 32 44) !important;
}
html.light input::placeholder,
html.light textarea::placeholder { color: rgb(99 110 125) !important; }
/* Sidebar back-office en clair */
html.light aside { background: rgb(253 252 249 / 0.95) !important; }
/* Footer en clair */
html.light footer.bg-surface-container-lowest { background: rgb(253 252 249) !important; }
/* ── Accessibilité WCAG 2.2 AA ── */
/* Lien d'évitement : invisible jusqu'au focus clavier */
.skip-link {
    position: fixed; top: -100px; left: 1rem; z-index: 100;
    padding: 0.625rem 1rem; border-radius: 0.5rem;
    background: rgb(27 119 186); color: #fff;
    font-size: 0.8125rem; font-weight: 600; text-decoration: none;
    transition: top 0.15s ease-in-out;
}
.skip-link:focus { top: 1rem; color: #fff; }
html.dark .skip-link { background: rgb(56 165 220); color: rgb(8 30 42); }
html.dark .skip-link:focus { color: rgb(8 30 42); }
/* Indicateur de focus visible, identique partout (SC 2.4.7 / 2.4.13) */
:focus-visible {
    outline: 3px solid rgb(27 119 186) !important;
    outline-offset: 2px;
    border-radius: 4px;
}
html.dark :focus-visible { outline-color: rgb(125 200 235) !important; }
/* Cartes-radio (inscription) : l'input est visuellement masqué → le focus
   clavier est reporté sur la carte visible (SC 2.4.7). */
label:has(> input.sr-only:focus-visible) {
    outline: 3px solid rgb(27 119 186);
    outline-offset: 2px;
}
html.dark label:has(> input.sr-only:focus-visible) { outline-color: rgb(125 200 235); }
/* Respect des préférences de mouvement (SC 2.3.3) */
@media (prefers-reduced-motion: reduce) {
    html { scroll-behavior: auto; }
    *, *::before, *::after {
        animation-duration: 0.01ms !important;
        animation-iteration-count: 1 !important;
        transition-duration: 0.01ms !important;
    }
}
/* ── Correctifs de contraste AA ── */
/* Rouge sémantique fixe trop sombre sur fond sombre */
html.dark .text-error { color: rgb(248 113 113) !important; }
@layer base{html,body{margin:0;padding:0;}html{scroll-behavior:smooth;}body{overscroll-behavior:none;}main>:first-child{margin-top:0!important;}main>:last-child{margin-bottom:0!important;}}::-webkit-scrollbar{display:none;}[x-cloak]{display:none!important;}
</style>
<script src="https://cdn.tailwindcss.com"></script>
<script id="tailwind-config">tailwind.config={darkMode:"class",theme:{extend:{colors:{"surface-variant":"#313540","on-tertiary-fixed-variant":"#604100","on-surface":"rgb(var(--md-on-surface) / <alpha-value>)","surface-bright":"#353944","surface-dim":"#0f131d","background":"rgb(var(--md-background) / <alpha-value>)","nav-surface":"rgb(var(--md-nav) / <alpha-value>)","on-error":"#690005","on-primary-container":"rgb(var(--md-on-primary-container) / <alpha-value>)","tertiary":"#ffe9cd","inverse-surface":"#dfe2f1","primary-fixed":"#9cf0ff","inverse-on-surface":"#2c303b","primary-container":"rgb(var(--md-primary-container) / <alpha-value>)","secondary-container":"#0566d9","on-primary-fixed":"#001f24","on-tertiary":"#432c00","surface-container":"rgb(var(--md-surface-container) / <alpha-value>)","surface":"rgb(var(--md-surface) / <alpha-value>)","primary-fixed-dim":"#7cc6e8","on-background":"rgb(var(--md-on-background) / <alpha-value>)","secondary-fixed":"#d8e2ff","on-secondary":"#002e6a","outline-variant":"rgb(var(--md-outline-variant) / <alpha-value>)","surface-container-highest":"rgb(var(--md-surface-container-highest) / <alpha-value>)","inverse-primary":"#006875","on-tertiary-container":"#775200","on-surface-variant":"rgb(var(--md-on-surface-variant) / <alpha-value>)","tertiary-container":"rgb(var(--md-tertiary-container) / <alpha-value>)","secondary-fixed-dim":"#adc6ff","on-primary":"rgb(var(--md-on-primary) / <alpha-value>)","surface-container-low":"rgb(var(--md-surface-container-low) / <alpha-value>)","secondary":"#adc6ff","on-secondary-container":"#e6ecff","on-tertiary-fixed":"#281900","surface-tint":"rgb(var(--md-surface-tint) / <alpha-value>)","surface-container-high":"rgb(var(--md-surface-container-high) / <alpha-value>)","surface-container-lowest":"rgb(var(--md-surface-container-lowest) / <alpha-value>)","error-container":"rgb(var(--md-error-container) / <alpha-value>)","on-error-container":"rgb(var(--md-on-error-container) / <alpha-value>)","outline":"rgb(var(--md-outline) / <alpha-value>)","primary":"rgb(var(--md-primary) / <alpha-value>)","on-tertiary":"#432c00","tertiary-fixed":"rgb(var(--md-tertiary-fixed) / <alpha-value>)","tertiary-fixed-dim":"rgb(var(--md-tertiary-fixed-dim) / <alpha-value>)","error":"#ba1a1a","surface-tint2":"#00e5ff"},fontFamily:{"display":["Space Grotesk","Inter","sans-serif"],"body-md":["Inter","sans-serif"]},fontSize:{"display-lg":["3.5rem",{lineHeight:"1.05"}],"headline-lg":["1.75rem",{lineHeight:"2.1rem"}],"headline-sm":["1.375rem",{lineHeight:"1.75rem"}],"title-md":["1rem",{lineHeight:"1.4rem"}],"title-sm":["0.875rem",{lineHeight:"1.25rem"}],"body-md":["0.9375rem",{lineHeight:"1.5rem"}],"body-sm":["0.8125rem",{lineHeight:"1.3rem"}],"label-md":["0.8125rem",{lineHeight:"1.2rem"}],"label-sm":["0.6875rem",{lineHeight:"1rem"}]},spacing:{"margin":"1rem","margin-lg":"2rem","space-xs":"0.5rem","space-sm":"0.75rem","space-md":"1rem","space-lg":"1.5rem","space-xl":"2.5rem"}}}};</script>
