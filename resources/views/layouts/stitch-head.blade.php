@php
// Tokens du design system ChillNet (base Stitch midnight navy).
// Les tokens pilotés par le thème pointent vers des variables CSS (voir :root / html.light
// ci-dessous) afin de permettre la bascule sombre/clair de la landing page. Les variables
// stockent des canaux RGB pour rester compatibles avec les modificateurs d'opacité Tailwind
// (ex. bg-surface-container-lowest/80, bg-nav-surface/85).
@endphp
<link rel="preconnect" href="https://fonts.googleapis.com" />
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin />
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet" />
<link href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined:opsz,wght,FILL,GRAD@20..48,100..700,0..1,-50..200" rel="stylesheet" />
<script>
(function () {
    var root = document.documentElement;
    // Le thème clair n'est appliqué que sur la landing (marqueur data-theme-landing) :
    // le reste de l'application conserve son rendu sombre par défaut.
    if (!root.hasAttribute('data-theme-landing')) return;
    try {
        if (localStorage.getItem('chillnet-theme') === 'light') {
            root.classList.remove('dark');
            root.classList.add('light');
        }
    } catch (e) {}
})();
</script>
<style>
:root, html.dark {
    --md-surface: 15 19 29;
    --md-background: 15 19 29;
    --md-surface-container-lowest: 10 14 24;
    /* Couleur dédiée à la navbar : volontairement plus claire que la page (near-black)
       pour qu'elle se détache, avec une pointe de bleu cyan. */
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
    --md-primary: 195 245 255;
    --md-primary-container: 0 229 255;
    --md-on-primary-container: 0 98 110;
    --md-on-primary: 0 54 61;
    --md-surface-tint: 0 218 243;
    --md-tertiary-fixed: 255 222 172;
    --md-tertiary-fixed-dim: 255 186 56;
    --md-tertiary-container: 255 199 105;
    --md-error-container: 147 0 10;
    --md-on-error-container: 255 218 214;
}
/* Thème clair : papier bleuté (pas de blanc pur en fond) + cartes blanches.
   La navbar garde son propre token (--md-nav), ici franchement blanc. */
html.light {
    --md-surface: 246 248 251;
    --md-background: 238 242 248;
    --md-surface-container-lowest: 255 255 255;
    /* En clair, la navbar est franchement blanche au-dessus du fond bleuté. */
    --md-nav: 255 255 255;
    --md-surface-container-low: 244 247 251;
    --md-surface-container: 237 242 248;
    --md-surface-container-high: 226 233 243;
    --md-surface-container-highest: 212 222 236;
    --md-on-surface: 15 23 42;
    --md-on-surface-variant: 71 85 105;
    --md-on-background: 15 23 42;
    --md-outline: 148 163 184;
    --md-outline-variant: 205 214 227;
    --md-primary: 3 105 161;
    --md-primary-container: 2 132 199;
    --md-on-primary-container: 255 255 255;
    --md-on-primary: 255 255 255;
    --md-surface-tint: 2 132 199;
    --md-tertiary-fixed: 180 83 9;
    --md-tertiary-fixed-dim: 234 88 12;
    --md-tertiary-container: 245 158 11;
    --md-error-container: 254 242 242;
    --md-on-error-container: 185 28 28;
}
/* Navbar : elle a sa propre couleur (--md-nav), donc on la détoure dans les deux thèmes. */
header.fixed {
    border: 1px solid rgba(255, 255, 255, 0.09);
    box-shadow:
        inset 0 1px 0 rgba(255, 255, 255, 0.06),
        0 18px 40px -20px rgba(2, 6, 23, 0.9) !important;
}
html.light header.fixed {
    border: 1px solid rgb(15 23 42 / 0.07);
    box-shadow: 0 1px 2px rgba(15, 23, 42, 0.04), 0 12px 32px -12px rgba(15, 23, 42, 0.22) !important;
}
html.light footer { box-shadow: 0 -1px 12px rgba(15, 23, 42, 0.06) !important; }
@layer base{html,body{margin:0;padding:0;}html{scroll-behavior:smooth;}body{overscroll-behavior:none;}main>:first-child{margin-top:0!important;}main>:last-child{margin-bottom:0!important;}}::-webkit-scrollbar{display:none;}[x-cloak]{display:none!important;}
</style>
<script src="https://cdn.tailwindcss.com"></script>
<script id="tailwind-config">tailwind.config={darkMode:"class",theme:{extend:{colors:{"surface-variant":"#313540","on-tertiary-fixed-variant":"#604100","on-surface":"rgb(var(--md-on-surface) / <alpha-value>)","surface-bright":"#353944","surface-dim":"#0f131d","background":"rgb(var(--md-background) / <alpha-value>)","nav-surface":"rgb(var(--md-nav) / <alpha-value>)","on-error":"#690005","on-primary-container":"rgb(var(--md-on-primary-container) / <alpha-value>)","tertiary":"#ffe9cd","inverse-surface":"#dfe2f1","primary-fixed":"#9cf0ff","inverse-on-surface":"#2c303b","primary-container":"rgb(var(--md-primary-container) / <alpha-value>)","secondary-container":"#0566d9","on-primary-fixed":"#001f24","on-tertiary":"#432c00","surface-container":"rgb(var(--md-surface-container) / <alpha-value>)","surface":"rgb(var(--md-surface) / <alpha-value>)","primary-fixed-dim":"#00daf3","on-background":"rgb(var(--md-on-background) / <alpha-value>)","secondary-fixed":"#d8e2ff","on-secondary":"#002e6a","outline-variant":"rgb(var(--md-outline-variant) / <alpha-value>)","surface-container-highest":"rgb(var(--md-surface-container-highest) / <alpha-value>)","inverse-primary":"#006875","on-tertiary-container":"#775200","on-surface-variant":"rgb(var(--md-on-surface-variant) / <alpha-value>)","tertiary-container":"rgb(var(--md-tertiary-container) / <alpha-value>)","secondary-fixed-dim":"#adc6ff","on-primary":"rgb(var(--md-on-primary) / <alpha-value>)","surface-container-low":"rgb(var(--md-surface-container-low) / <alpha-value>)","secondary":"#adc6ff","on-secondary-container":"#e6ecff","on-tertiary-fixed":"#281900","surface-tint":"rgb(var(--md-surface-tint) / <alpha-value>)","surface-container-high":"rgb(var(--md-surface-container-high) / <alpha-value>)","surface-container-lowest":"rgb(var(--md-surface-container-lowest) / <alpha-value>)","error-container":"rgb(var(--md-error-container) / <alpha-value>)","on-error-container":"rgb(var(--md-on-error-container) / <alpha-value>)","outline":"rgb(var(--md-outline) / <alpha-value>)","on-secondary-fixed":"#001a42","on-primary-fixed-variant":"#004f58","primary":"rgb(var(--md-primary) / <alpha-value>)","tertiary-fixed":"rgb(var(--md-tertiary-fixed) / <alpha-value>)","on-secondary-fixed-variant":"#004395","error":"#ffb4ab","tertiary-fixed-dim":"rgb(var(--md-tertiary-fixed-dim) / <alpha-value>)","ln-bg":"rgb(var(--ln-bg) / <alpha-value>)","ln-bg-alt":"rgb(var(--ln-bg-alt) / <alpha-value>)","ln-bg-alt2":"rgb(var(--ln-bg-alt2) / <alpha-value>)","ln-bg-cta":"rgb(var(--ln-bg-cta) / <alpha-value>)","ln-heading":"rgb(var(--ln-heading) / <alpha-value>)","ln-text":"rgb(var(--ln-text) / <alpha-value>)","ln-body":"rgb(var(--ln-body) / <alpha-value>)","ln-muted":"rgb(var(--ln-muted) / <alpha-value>)","ln-faint":"rgb(var(--ln-faint) / <alpha-value>)","ln-border":"rgb(var(--ln-border) / <alpha-value>)","ln-border-grid":"rgb(var(--ln-border-grid) / <alpha-value>)","ln-accent":"rgb(var(--ln-accent) / <alpha-value>)","ln-accent-strong":"rgb(var(--ln-accent-strong) / <alpha-value>)","ln-warn":"rgb(var(--ln-warn) / <alpha-value>)","ln-chip":"rgb(var(--ln-chip) / <alpha-value>)","ln-glass-hover":"rgb(var(--ln-glass-hover) / <alpha-value>)","ln-row-hover":"rgb(var(--ln-row-hover) / <alpha-value>)","ln-hero":"rgb(var(--ln-hero) / <alpha-value>)"},borderRadius:{DEFAULT:"0.25rem",lg:"0.5rem",xl:"0.75rem",full:"9999px"},spacing:{"gutter":"1rem","margin-md":"1.5rem","space-xl":"2.5rem","space-lg":"1.5rem","gutter-lg":"1.5rem","margin":"1rem","space-md":"1rem","space-xs":"0.25rem","margin-lg":"2.5rem","space-sm":"0.5rem"},fontFamily:{"headline-sm":["Inter"],"label-md":["Inter"],"body-lg":["Inter"],"body-sm":["Inter"],"label-sm":["Inter"],"headline-lg":["Inter"],"body-md":["Inter"],"title-md":["Inter"],"display-lg":["Inter"]},fontSize:{"headline-sm":["20px",{lineHeight:"28px",fontWeight:"600"}],"label-md":["13px",{lineHeight:"18px",fontWeight:"500"}],"body-lg":["16px",{lineHeight:"24px",fontWeight:"400"}],"body-sm":["12px",{lineHeight:"16px",fontWeight:"400"}],"label-sm":["11px",{lineHeight:"14px",fontWeight:"600"}],"headline-lg":["32px",{lineHeight:"40px",fontWeight:"600"}],"body-md":["14px",{lineHeight:"20px",fontWeight:"400"}],"title-md":["16px",{lineHeight:"24px",fontWeight:"600"}],"display-lg":["48px",{lineHeight:"56px",fontWeight:"700"}]}}}};</script>
